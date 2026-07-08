<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Modules\Community\Events\ChatMessageSent;
use App\Modules\Community\Actions\ChatService;
use App\Modules\Community\Models\ChatConversation;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Community\Models\PlayerFollow;
use App\Modules\Identity\Models\User;
use App\Modules\Team\Actions\CreateTeamAction;
use App\Modules\Team\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_page_no_longer_renders_mock_only_notice(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/chat')
            ->assertOk()
            ->assertSee('Comms Hub')
            ->assertDontSee('Prototype only');
    }

    public function test_global_chat_persists_messages_and_broadcasts(): void
    {
        Event::fake([ChatMessageSent::class]);
        $user = User::factory()->create(['username' => 'global_player']);
        $otherPlayer = User::factory()->create(['username' => 'global_reader']);

        $conversationUuid = $this->actingAs($user)
            ->getJson(route('chat.conversations'))
            ->assertOk()
            ->json('conversations.0.uuid');

        $this->actingAs($user)
            ->postJson(route('chat.messages.send', ['uuid' => $conversationUuid]), [
                'message' => 'Queue for finals starts in five.',
            ])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Queue for finals starts in five.')
            ->assertJsonPath('message.user.username', 'global_player');

        $this->assertDatabaseHas('chat_messages', [
            'body' => 'Queue for finals starts in five.',
            'user_id' => $user->id,
        ]);

        $this->actingAs($otherPlayer)
            ->getJson(route('chat.messages', ['uuid' => $conversationUuid]))
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Queue for finals starts in five.');

        Event::assertDispatched(ChatMessageSent::class);
    }

    public function test_global_chat_keeps_only_latest_100_messages(): void
    {
        Event::fake([ChatMessageSent::class]);
        $user = User::factory()->create(['username' => 'global_sender']);
        $chat = app(ChatService::class);
        $conversation = $chat->globalConversation($user);

        for ($i = 1; $i <= 100; $i++) {
            ChatMessage::query()->create([
                'uuid' => Str::uuid()->toString(),
                'chat_conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'body' => 'Global message '.$i,
            ]);
        }

        $conversation->forceFill(['last_message_at' => now()])->save();
        $chat->sendMessage($conversation, $user, 'Global message 101');

        $this->assertSame(100, ChatMessage::query()->count());
        $this->assertDatabaseMissing('chat_messages', [
            'body' => 'Global message 1',
            'deleted_at' => null,
        ]);

        $this->actingAs($user)
            ->getJson(route('chat.messages', ['uuid' => $conversation->uuid]))
            ->assertOk()
            ->assertJsonMissing(['body' => 'Global message 1'])
            ->assertJsonPath('messages.49.body', 'Global message 101');
    }

    public function test_direct_chat_is_limited_to_participants(): void
    {
        $playerA = User::factory()->create(['username' => 'alpha']);
        $playerB = User::factory()->create(['username' => 'bravo']);
        $outsider = User::factory()->create(['username' => 'charlie']);

        $conversationUuid = $this->actingAs($playerA)
            ->postJson(route('chat.direct.open'), ['username' => 'bravo'])
            ->assertCreated()
            ->json('conversation.uuid');

        $this->actingAs($playerA)
            ->postJson(route('chat.messages.send', ['uuid' => $conversationUuid]), [
                'message' => 'Ready for our duel?',
            ])
            ->assertCreated();

        $this->actingAs($playerB)
            ->getJson(route('chat.messages', ['uuid' => $conversationUuid]))
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Ready for our duel?');

        $this->actingAs($outsider)
            ->getJson(route('chat.messages', ['uuid' => $conversationUuid]))
            ->assertForbidden();
    }

    public function test_conversations_mark_unread_until_messages_are_opened(): void
    {
        $playerA = User::factory()->create(['username' => 'sender']);
        $playerB = User::factory()->create(['username' => 'receiver']);

        $conversationUuid = $this->actingAs($playerA)
            ->postJson(route('chat.direct.open'), ['username' => 'receiver'])
            ->assertCreated()
            ->json('conversation.uuid');

        $this->actingAs($playerA)
            ->postJson(route('chat.messages.send', ['uuid' => $conversationUuid]), [
                'message' => 'Unread badge check.',
            ])
            ->assertCreated();

        $this->actingAs($playerB)
            ->getJson(route('chat.conversations'))
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $conversationUuid,
                'is_unread' => true,
            ]);

        $this->actingAs($playerB)
            ->getJson(route('chat.messages', ['uuid' => $conversationUuid]))
            ->assertOk();

        $this->actingAs($playerB)
            ->getJson(route('chat.conversations'))
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $conversationUuid,
                'is_unread' => false,
            ]);
    }

    public function test_team_chat_requires_active_membership(): void
    {
        $captain = User::factory()->create(['username' => 'captain']);
        $member = User::factory()->create(['username' => 'member']);
        $outsider = User::factory()->create(['username' => 'outsider']);
        $team = (new CreateTeamAction)->execute(['name' => 'Neon Team'], $captain);

        TeamMember::query()->create([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $conversationUuid = $this->actingAs($member)
            ->postJson(route('chat.teams.open', ['uuid' => $team->uuid]))
            ->assertCreated()
            ->assertJsonPath('conversation.type', ChatConversation::TYPE_TEAM)
            ->json('conversation.uuid');

        $this->actingAs($member)
            ->postJson(route('chat.messages.send', ['uuid' => $conversationUuid]), [
                'message' => 'Scrim starts after check-in.',
            ])
            ->assertCreated();

        $this->actingAs($captain)
            ->getJson(route('chat.messages', ['uuid' => $conversationUuid]))
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Scrim starts after check-in.');

        $this->actingAs($outsider)
            ->postJson(route('chat.teams.open', ['uuid' => $team->uuid]))
            ->assertForbidden();
    }

    public function test_joining_team_channel_switches_existing_team_membership(): void
    {
        $captainA = User::factory()->create(['username' => 'captain_a']);
        $captainB = User::factory()->create(['username' => 'captain_b']);
        $player = User::factory()->create(['username' => 'switcher']);
        $teamA = (new CreateTeamAction)->execute(['name' => 'Alpha Team'], $captainA);
        $teamB = (new CreateTeamAction)->execute(['name' => 'Bravo Team'], $captainB);

        TeamMember::query()->create([
            'team_id' => $teamA->id,
            'user_id' => $player->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $oldConversationUuid = $this->actingAs($player)
            ->postJson(route('chat.teams.open', ['uuid' => $teamA->uuid]))
            ->assertCreated()
            ->json('conversation.uuid');

        $newConversationUuid = $this->actingAs($player)
            ->postJson(route('chat.teams.join', ['uuid' => $teamB->uuid]))
            ->assertCreated()
            ->assertJsonPath('conversation.type', ChatConversation::TYPE_TEAM)
            ->json('conversation.uuid');

        $this->assertNotSame($oldConversationUuid, $newConversationUuid);
        $this->assertDatabaseMissing('team_members', [
            'team_id' => $teamA->id,
            'user_id' => $player->id,
        ]);
        $this->assertDatabaseHas('team_members', [
            'team_id' => $teamB->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_user_search_does_not_expose_email_addresses(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        User::factory()->create(['username' => 'target_player', 'email' => 'secret@example.com']);

        $this->actingAs($viewer)
            ->getJson(route('chat.users', ['search' => 'target']))
            ->assertOk()
            ->assertJsonPath('users.0.username', 'target_player')
            ->assertJsonMissing(['email' => 'secret@example.com']);

        $this->assertSame(0, ChatMessage::query()->count());
    }

    public function test_player_profile_modal_payload_and_follow_action(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $target = User::factory()->create(['username' => 'target_player']);

        $this->actingAs($viewer)
            ->getJson(route('chat.players.show', ['uuid' => $target->uuid]))
            ->assertOk()
            ->assertJsonPath('player.username', 'target_player')
            ->assertJsonPath('player.is_following', false)
            ->assertJsonStructure([
                'player' => [
                    'uuid',
                    'username',
                    'display_name',
                    'initials',
                    'followers',
                    'stats' => ['wins', 'losses', 'matches', 'win_rate', 'prize_total'],
                ],
            ]);

        $this->actingAs($viewer)
            ->postJson(route('chat.players.follow', ['uuid' => $target->uuid]))
            ->assertOk()
            ->assertJsonPath('player.is_following', true)
            ->assertJsonPath('player.followers', 1);

        $this->assertDatabaseHas('player_follows', [
            'follower_user_id' => $viewer->id,
            'followed_user_id' => $target->id,
        ]);
        $this->assertSame(1, PlayerFollow::query()->count());
    }
}
