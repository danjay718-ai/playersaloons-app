<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Livewire\Notification\NotificationBell;
use App\Modules\CMS\Models\Game;
use App\Modules\Community\Events\ChatInboxUpdated;
use App\Modules\Community\Events\ChatMessageSent;
use App\Modules\Community\Models\ChatParticipant;
use App\Modules\Community\Models\Notification;
use App\Modules\Community\Models\NotificationPreference;
use App\Modules\Community\Services\ChatInboxService;
use App\Modules\Community\Services\ChatService;
use App\Modules\Identity\Models\User;
use App\Modules\Team\Actions\CreateTeamAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentTeam;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ChatInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ChatInboxUpdated::class, ChatMessageSent::class]);
        Mail::fake();
    }

    private function players(): array
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        return [$sender, $recipient, app(ChatService::class)->directConversation($sender, $recipient)];
    }

    public function test_first_direct_messages_update_recipient_inbox_and_group_bell_alerts_without_email(): void
    {
        [$sender, $recipient, $conversation] = $this->players();
        $chat = app(ChatService::class);
        $first = $chat->sendMessage($conversation, $sender, 'First message');
        $uuid = Notification::query()->firstOrFail()->uuid;
        $last = $chat->sendMessage($conversation, $sender, 'Second message');
        self::assertDatabaseCount('notifications', 1);
        self::assertSame($uuid, Notification::query()->firstOrFail()->uuid);
        self::assertSame($last->id, Notification::query()->firstOrFail()->chat_message_id);
        $this->actingAs($recipient)->getJson(route('chat.inbox'))->assertOk()
            ->assertJsonPath('unread_count', 1)->assertJsonPath('conversations.0.uuid', $conversation->uuid);
        $this->actingAs($sender)->getJson(route('chat.inbox'))->assertJsonPath('unread_count', 0);
        Event::assertDispatched(ChatInboxUpdated::class, fn ($event) => $event->userUuid === $recipient->uuid && $event->messageId === $first->id && $event->alert);
        Mail::assertNothingOutgoing();
    }

    public function test_reading_uses_delivered_ids_and_does_not_hide_same_second_or_later_messages(): void
    {
        [$sender, $recipient, $conversation] = $this->players();
        $this->freezeTime();
        $chat = app(ChatService::class);
        $first = $chat->sendMessage($conversation, $sender, 'Loaded message');
        $this->actingAs($recipient)->getJson(route('chat.messages', $conversation->uuid))->assertOk();
        $this->getJson(route('chat.inbox'))->assertJsonPath('unread_count', 1);
        $second = $chat->sendMessage($conversation, $sender, 'Concurrent message');
        self::assertTrue($first->created_at->equalTo($second->created_at));
        $this->postJson(route('chat.read', $conversation->uuid), ['message_id' => $first->id])->assertOk()->assertJsonPath('unread_count', 1);
        self::assertNull(Notification::query()->firstOrFail()->read_at);
        $this->postJson(route('chat.read', $conversation->uuid), ['message_id' => $second->id])->assertOk()->assertJsonPath('unread_count', 0);
        self::assertNotNull(Notification::query()->firstOrFail()->read_at);
        $this->postJson(route('chat.read', $conversation->uuid), ['message_id' => $first->id])->assertOk();
        self::assertSame($second->id, ChatParticipant::query()->where('user_id', $recipient->id)->firstOrFail()->last_read_message_id);
    }

    public function test_mute_is_persistent_personal_and_keeps_unread_messages_and_sending_available(): void
    {
        [$sender, $recipient, $conversation] = $this->players();
        $chat = app(ChatService::class);
        $message = $chat->sendMessage($conversation, $sender, 'Before mute');
        $this->actingAs($recipient)->putJson(route('chat.mute', $conversation->uuid), ['muted' => true])
            ->assertOk()->assertJsonPath('conversation.notifications_muted', true)->assertJsonPath('conversation.is_unread', true);
        $this->getJson(route('chat.inbox'))->assertJsonPath('unread_count', 0);
        self::assertNotNull(Notification::query()->firstOrFail()->dismissed_at);
        self::assertNull(Notification::query()->firstOrFail()->read_at);
        $chat->sendMessage($conversation, $sender, 'While muted');
        self::assertSame($message->id, Notification::query()->firstOrFail()->chat_message_id);
        $this->postJson(route('chat.messages.send', $conversation->uuid), ['message' => 'I can still reply'])->assertCreated();
        $this->putJson(route('chat.mute', $conversation->uuid), ['muted' => false])->assertOk();
        $this->getJson(route('chat.inbox'))->assertJsonPath('unread_count', 1);
        self::assertFalse(ChatParticipant::query()->where('user_id', $sender->id)->firstOrFail()->notifications_muted);
        $chat->sendMessage($conversation, $sender, 'After unmute');
        self::assertNull($recipient->notifications()->firstOrFail()->dismissed_at);
        self::assertDatabaseHas('chat_messages', ['id' => $message->id, 'deleted_at' => null]);
    }

    public function test_global_and_team_chats_can_be_muted_but_do_not_create_account_alerts(): void
    {
        [$sender, $recipient] = $this->players();
        $chat = app(ChatService::class);
        $global = $chat->globalConversation($recipient);
        $team = app(CreateTeamAction::class)->execute(['name' => 'Inbox Team', 'tag' => 'INBOX'], $recipient);
        $team->members()->create(['user_id' => $sender->id, 'role' => 'member', 'status' => 'active', 'joined_at' => now()]);
        $teamChat = $chat->teamConversation($team, $recipient);
        foreach ([$global, $teamChat] as $conversation) {
            $this->actingAs($recipient)->putJson(route('chat.mute', $conversation->uuid), ['muted' => true])
                ->assertOk()->assertJsonPath('conversation.notifications_muted', true);
            $chat->sendMessage($conversation, $recipient, 'Mute does not block sending');
        }
        $chat->sendMessage($global, $sender, 'Global update');
        $chat->sendMessage($teamChat, $sender, 'Squad update');
        self::assertDatabaseCount('notifications', 0);
        $this->getJson(route('chat.inbox'))->assertJsonPath('unread_count', 0);
    }

    public function test_tournament_team_chat_has_personal_mute_controls_and_keeps_membership_authorization(): void
    {
        [$sender, $recipient] = $this->players();
        $game = Game::query()->create(['uuid' => Str::uuid(), 'slug' => 'chat-game', 'is_active' => true]);
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid(), 'name' => 'Team Chat Cup', 'slug' => 'team-chat-cup', 'game_id' => $game->id,
            'created_by' => $recipient->id, 'status' => TournamentStatus::REGISTRATION_OPEN, 'max_participants' => 4, 'min_participants' => 2,
        ]);
        $team = TournamentTeam::query()->create(['uuid' => Str::uuid(), 'tournament_id' => $tournament->id, 'leader_user_id' => $recipient->id, 'name' => 'Chat entrants']);
        $team->members()->create(['tournament_id' => $tournament->id, 'user_id' => $recipient->id, 'role' => 'leader']);
        $conversation = app(ChatService::class)->tournamentTeamConversation($team, $recipient);
        $this->actingAs($recipient)->putJson(route('chat.mute', $conversation->uuid), ['muted' => true])
            ->assertOk()->assertJsonPath('conversation.notifications_muted', true);
        $this->postJson(route('chat.messages.send', $conversation->uuid), ['message' => 'Still participating'])->assertCreated();
        $this->actingAs($sender)->putJson(route('chat.mute', $conversation->uuid), ['muted' => true])->assertForbidden();
        self::assertDatabaseCount('notifications', 0);
    }

    public function test_personal_mute_controls_do_not_remove_an_existing_moderation_mute(): void
    {
        [$sender, $recipient, $conversation] = $this->players();
        ChatParticipant::query()->where('user_id', $recipient->id)->update(['muted_until' => now()->addHour()]);
        $this->actingAs($recipient)->putJson(route('chat.mute', $conversation->uuid), ['muted' => true])->assertOk();
        $this->putJson(route('chat.mute', $conversation->uuid), ['muted' => false])->assertOk();
        $this->postJson(route('chat.messages.send', $conversation->uuid), ['message' => 'Should still be blocked'])->assertUnprocessable();
        self::assertNotNull(ChatParticipant::query()->where('user_id', $recipient->id)->firstOrFail()->muted_until);
    }

    public function test_preferences_disable_chat_bell_and_realtime_alerts_without_hiding_inbox_count(): void
    {
        [$sender, $recipient, $conversation] = $this->players();
        NotificationPreference::query()->create(['user_id' => $recipient->id, 'in_app_enabled' => false, 'realtime_enabled' => false, 'email_enabled' => true]);
        app(ChatService::class)->sendMessage($conversation, $sender, 'Quiet message');
        self::assertDatabaseCount('notifications', 0);
        Event::assertDispatched(ChatInboxUpdated::class, fn ($event) => $event->userUuid === $recipient->uuid && ! $event->alert);
        self::assertSame(1, app(ChatInboxService::class)->summary($recipient)['unread_count']);
        Mail::assertNothingOutgoing();
    }

    public function test_marking_and_clearing_bell_alerts_does_not_read_chat_or_modify_other_users(): void
    {
        [$sender, $recipient, $conversation] = $this->players();
        app(ChatService::class)->sendMessage($conversation, $sender, 'Still unread in Chat');
        $other = Notification::factory()->create(['user_id' => $sender->id, 'read_at' => now()]);
        Notification::factory()->count(15)->create(['user_id' => $recipient->id, 'read_at' => now()]);
        $unread = Notification::factory()->create(['user_id' => $recipient->id]);
        $bell = Livewire::actingAs($recipient)->test(NotificationBell::class)->assertSet('hasReadNotifications', true)
            ->call('clearRead')->assertSet('unreadCount', 2)->assertSet('hasReadNotifications', false);
        self::assertSame(15, $recipient->notifications()->whereNotNull('dismissed_at')->count());
        self::assertNull($other->fresh()->dismissed_at);
        self::assertNull($unread->fresh()->dismissed_at);
        $bell->call('markAllRead')->call('clearRead')->assertSet('unreadCount', 0);
        self::assertSame(1, app(ChatInboxService::class)->summary($recipient)['unread_count']);
        self::assertDatabaseCount('chat_messages', 1);
    }

    public function test_unread_count_counts_conversations_and_chat_links_target_the_correct_conversation(): void
    {
        [$sender, $recipient, $conversation] = $this->players();
        $other = User::factory()->create();
        $chat = app(ChatService::class);
        $chat->sendMessage($conversation, $sender, 'One');
        $chat->sendMessage($conversation, $sender, 'Two');
        $second = $chat->directConversation($other, $recipient);
        $chat->sendMessage($second, $other, 'Three');
        self::assertSame(2, app(ChatInboxService::class)->summary($recipient)['unread_count']);
        $notification = $recipient->notifications()->where('chat_conversation_id', $conversation->id)->firstOrFail();
        Livewire::actingAs($recipient)->test(NotificationBell::class)->call('openNotification', $notification->id)
            ->assertRedirect('/chat?conversation='.$conversation->uuid);
        self::assertSame(2, app(ChatInboxService::class)->summary($recipient)['unread_count']);
        $this->actingAs($recipient)->get('/chat?conversation='.$conversation->uuid)->assertOk()
            ->assertSee('sidebarExpanded: true', false)->assertSee('Mute notifications')->assertSee('unread direct conversations');
    }

    public function test_cross_user_reads_mutes_and_cross_conversation_checkpoints_are_rejected(): void
    {
        [$sender, $recipient, $conversation] = $this->players();
        $outsider = User::factory()->create();
        $message = app(ChatService::class)->sendMessage($conversation, $sender, 'Private');
        $this->actingAs($outsider)->postJson(route('chat.read', $conversation->uuid), ['message_id' => $message->id])->assertForbidden();
        $this->putJson(route('chat.mute', $conversation->uuid), ['muted' => true])->assertForbidden();
        $this->getJson(route('chat.inbox'))->assertJsonPath('unread_count', 0);
        $global = app(ChatService::class)->globalConversation($sender);
        $other = app(ChatService::class)->sendMessage($global, $sender, 'Different conversation');
        $this->actingAs($recipient)->postJson(route('chat.read', $conversation->uuid), ['message_id' => $other->id])->assertUnprocessable();
        $this->putJson(route('chat.mute', $conversation->uuid), ['muted' => 'invalid'])->assertUnprocessable();
    }

    public function test_chat_events_are_delivered_only_after_the_sending_transaction_commits(): void
    {
        [$sender, $recipient, $conversation] = $this->players();
        DB::beginTransaction();
        app(ChatService::class)->sendMessage($conversation, $sender, 'Commit first');
        Event::assertNotDispatched(ChatInboxUpdated::class);
        Event::assertNotDispatched(ChatMessageSent::class);
        DB::commit();
        Event::assertDispatched(ChatInboxUpdated::class);
        Event::assertDispatched(ChatMessageSent::class);
    }

    public function test_dismissed_read_notifications_stay_out_of_api_results(): void
    {
        [, $recipient] = $this->players();
        $read = Notification::factory()->create(['user_id' => $recipient->id, 'read_at' => now()]);
        $unread = Notification::factory()->create(['user_id' => $recipient->id]);
        Livewire::actingAs($recipient)->test(NotificationBell::class)->call('clearRead');
        $this->actingAs($recipient)->getJson(route('api.v1.notifications.index'))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $unread->uuid);
        $this->postJson(route('api.v1.notifications.read', $read->uuid))->assertNotFound();
    }

    public function test_rolled_back_messages_do_not_create_or_broadcast_inbox_alerts(): void
    {
        [$sender, , $conversation] = $this->players();
        DB::beginTransaction();
        app(ChatService::class)->sendMessage($conversation, $sender, 'Rolled back');
        DB::rollBack();
        Event::assertNotDispatched(ChatInboxUpdated::class);
        Event::assertNotDispatched(ChatMessageSent::class);
        self::assertDatabaseCount('notifications', 0);
        self::assertDatabaseCount('chat_messages', 0);
    }

    public function test_migration_backfills_read_checkpoints_without_losing_existing_moderation_mutes(): void
    {
        [$sender, $recipient, $conversation] = $this->players();
        $this->freezeTime();
        $old = app(ChatService::class)->sendMessage($conversation, $sender, 'Already read');
        ChatParticipant::query()->where('user_id', $recipient->id)->update(['last_read_at' => now(), 'muted_until' => now()->addHour()]);
        $this->travel(1)->seconds();
        app(ChatService::class)->sendMessage($conversation, $sender, 'Unread after migration');
        $migration = require database_path('migrations/2026_10_18_000001_add_chat_inbox_preferences.php');
        $migration->down();
        $migration->up();
        $participant = ChatParticipant::query()->where('user_id', $recipient->id)->firstOrFail();
        self::assertSame($old->id, $participant->last_read_message_id);
        self::assertNotNull($participant->muted_until);
        self::assertFalse($participant->notifications_muted);
        self::assertSame(1, app(ChatInboxService::class)->summary($recipient)['unread_count']);
    }
}
