<?php

declare(strict_types=1);

namespace App\Modules\Operations\Services;

use App\Modules\CMS\Models\CmsPage;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\LandingSectionItem;
use App\Modules\CMS\Models\Platform;
use App\Modules\CMS\Models\PolicyPage;
use App\Modules\CMS\Models\PublicNavigationItem;
use App\Modules\Community\Models\Advertisement;
use App\Modules\Community\Models\BroadcastMessage;
use App\Modules\Community\Models\ContactInquiry;
use App\Modules\Community\Models\NewsletterCampaign;
use App\Modules\Community\Models\PlayerReview;
use App\Modules\Compliance\Models\BlockedCountry;
use App\Modules\Compliance\Services\CountryEligibilityService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Localization\Models\TranslationString;
use App\Modules\Operations\Models\ErrorIncident;
use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentScheduleSlot;
use App\Modules\Tournament\Models\TournamentTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminDeletionService
{
    public const RESOURCES = [
        'games' => [Game::class, 'games.delete', 'slug', 'Games'],
        'platforms' => [Platform::class, 'platforms.delete', 'name', 'Platforms'],
        'tournaments' => [Tournament::class, 'tournaments.delete', 'name', 'Tournaments'],
        'tournament_schedules' => [TournamentTemplate::class, 'tournaments.delete', 'name', 'Tournament schedules'],
        'head_to_head_schedules' => [TournamentTemplate::class, 'tournaments.delete', 'name', 'Head-to-Head schedules'],
        'schedule_slots' => [TournamentScheduleSlot::class, 'tournaments.delete', 'label', 'Schedule slots'],
        'policies' => [PolicyPage::class, 'policies.delete', 'title', 'Policies'],
        'content' => [CmsPage::class, 'cms.delete', 'slug', 'Blog & news'],
        'navigation' => [PublicNavigationItem::class, 'navigation.delete', 'label', 'Navigation'],
        'landing_items' => [LandingSectionItem::class, 'cms.delete', 'item_key', 'Landing items'],
        'advertisements' => [Advertisement::class, 'advertisements.delete', 'title', 'Advertisements'],
        'broadcasts' => [BroadcastMessage::class, 'broadcast_messages.delete', 'title', 'Notifications'],
        'inquiries' => [ContactInquiry::class, 'contact_inquiries.delete', 'subject', 'Contact inquiries'],
        'campaigns' => [NewsletterCampaign::class, 'newsletters.delete', 'subject', 'Newsletter campaigns'],
        'reviews' => [PlayerReview::class, 'player_reviews.delete', 'id', 'Player reviews'],
        'errors' => [ErrorIncident::class, 'error_incidents.delete', 'id', 'Error incidents'],
        'streams' => [StreamChannel::class, 'streams.delete', 'title', 'Streams'],
        'users' => [User::class, 'users.delete', 'username', 'Users'],
        'roles' => [Role::class, 'roles.delete', 'name', 'Custom roles'],
        'translations' => [TranslationString::class, 'translations.delete', 'key', 'Translation keys'],
        'countries' => [BlockedCountry::class, 'geo_blocking.delete', 'country_name', 'Blocked countries'],
    ];

    public const SYSTEM_ROLES = ['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER', 'SUPPORT_AGENT', 'FINANCE_OPERATOR', 'KYC_REVIEWER', 'PLAYER', 'TEAM_CAPTAIN'];

    public function definition(string $resource): array
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);

        return self::RESOURCES[$resource];
    }

    public function query(string $resource, ?int $parentId = null): Builder
    {
        [$model] = $this->definition($resource);
        $query = $model::query();
        if (in_array($resource, ['tournament_schedules', 'head_to_head_schedules'], true)) {
            $query->where('competition_type', $resource === 'head_to_head_schedules' ? 'head_to_head' : 'tournament');
        }
        if ($resource === 'schedule_slots') {
            abort_unless($parentId !== null, 422);
            $query->where('tournament_template_id', $parentId);
        }
        if ($resource === 'translations') {
            $query->where('locale', 'en');
        }
        if ($resource === 'roles') {
            $query->whereNotIn('name', self::SYSTEM_ROLES);
        }

        return $query;
    }

    public function label(string $resource, Model $record): string
    {
        if ($record instanceof Game) {
            return $record->localizedName('en');
        }
        $column = $this->definition($resource)[2];

        return (string) ($record->getAttribute($column) ?: '#'.$record->getKey());
    }

    public function impact(Model $record): string
    {
        return match (true) {
            $record instanceof Game => 'Removed from game catalogs and new selections; future generation for this game stops. Preserved: '.Tournament::withTrashed()->where('game_id', $record->id)->count().' tournaments, '.DB::table('user_game_accounts')->where('game_id', $record->id)->count().' player game accounts, and all matches, streams and transactions.',
            $record instanceof Platform => 'Removed from platform selections; future generation using this platform stops. Existing tournament platform names, player accounts and game assignments remain intact. Active competitions block deletion.',
            $record instanceof Tournament => 'Hidden from tournament lists. Registrations, matches, results, prizes and transactions remain intact. Only empty draft tournaments can be deleted.',
            $record instanceof TournamentTemplate => 'This schedule stops generating new competitions. Existing slots, occurrences, registrations, matches and transactions remain intact.',
            $record instanceof TournamentScheduleSlot => 'This slot stops generating competitions. Existing occurrences and their history remain intact. Active competitions block deletion.',
            $record instanceof User => 'The account loses access and disappears from user lists. Wallets, transactions, match history, messages and verification records remain intact. Active participation and pending withdrawals block deletion.',
            $record instanceof Role => 'Hidden from role lists. Permission assignments remain stored. System roles and roles assigned to users cannot be deleted.',
            $record instanceof NewsletterCampaign => 'Hidden from campaign history; delivery counts and content remain stored. Sent emails cannot be recalled. Sending campaigns cannot be deleted.',
            $record instanceof ContactInquiry => 'Hidden from the inquiry inbox. The message, internal notes and resolution history remain stored.',
            $record instanceof PlayerReview => 'Hidden from management and public reviews. Review content and moderation history remain stored.',
            $record instanceof StreamChannel => 'Hidden from stream lists and unavailable to watch. Chat messages, viewer records and linked competition history remain stored.',
            $record instanceof ErrorIncident => 'Hidden from error management. Diagnostic details and resolution history remain stored.',
            $record instanceof TranslationString => 'Hidden from translation management and future exports for all languages. Existing language files keep their current text until exported. Stored translations remain recoverable.',
            $record instanceof BlockedCountry => 'This country becomes accessible again. The previous blocking rule remains stored.',
            $record instanceof PolicyPage => 'The published policy page becomes unavailable. Its content and update history remain stored.',
            $record instanceof Advertisement => 'The advertisement stops displaying. Content, impression and click counts remain stored.',
            $record instanceof BroadcastMessage => 'The announcement stops displaying. Previously delivered notifications and stored announcement content remain intact.',
            default => 'Hidden from management and public display. Content and related records remain stored.',
        };
    }

    public function blockedReason(Model $record, User $actor): ?string
    {
        if ($record instanceof Game || $record instanceof Platform) {
            $competitions = Tournament::query()->whereNotIn('status', ['DRAFT', 'COMPLETED', 'CANCELLED', 'REFUNDED']);
            if ($record instanceof Game) {
                $competitions->where('game_id', $record->id);
            } else {
                $competitions->where(fn ($query) => $query->where('platform_id', $record->id)->orWhereJsonContains('platform_ids', (int) $record->id));
            }
            $column = $record instanceof Game ? 'game_id' : 'platform_id';
            $tournamentCount = $competitions->count();
            $matchCount = DB::table('head_to_head_matches')->where($column, $record->id)->whereNotIn('status', ['completed', 'cancelled', 'expired'])->count();
            $challengeCount = DB::table('head_to_head_challenges')->where($column, $record->id)->where('status', 'waiting')->count();
            $dependencies = [];
            if ($tournamentCount > 0) {
                $names = $competitions->orderBy('id')->limit(3)->pluck('name')->implode(', ');
                $dependencies[] = $tournamentCount.' active tournament(s): '.$names.($tournamentCount > 3 ? ' and others' : '');
            }
            if ($matchCount > 0) {
                $dependencies[] = $matchCount.' active Head-to-Head match(es)';
            }
            if ($challengeCount > 0) {
                $dependencies[] = $challengeCount.' waiting Head-to-Head challenge(s)';
            }
            if ($dependencies !== []) {
                return 'This catalog entry is used by '.implode('; ', $dependencies).'. Finish or cancel these competitions before deleting it.';
            }
        }
        if ($record instanceof Tournament && ($record->status->value !== 'DRAFT' || $record->registrations()->exists())) {
            return 'Only draft tournaments with no registrations can be deleted.';
        }
        if ($record instanceof TournamentTemplate || $record instanceof TournamentScheduleSlot) {
            $occurrences = Tournament::query()->where($record instanceof TournamentTemplate ? 'template_id' : 'schedule_slot_id', $record->id);
            if ($occurrences->whereNotIn('status', ['DRAFT', 'COMPLETED', 'CANCELLED', 'REFUNDED'])->exists()) {
                return 'Cancel or finish active competitions before deleting their schedule.';
            }
        }
        if ($record instanceof NewsletterCampaign && $record->status === 'sending') {
            return 'This campaign is still sending.';
        }
        if ($record instanceof Role && (in_array($record->name, self::SYSTEM_ROLES, true) || $record->users()->withTrashed()->exists())) {
            return 'System roles and roles assigned to users cannot be deleted.';
        }
        if ($record instanceof User) {
            if ($record->id === $actor->id || $record->hasRole('SUPER_ADMIN')) {
                return 'Your own account and Super Admin accounts cannot be deleted. Transfer ownership first.';
            }
            if (DB::table('withdrawals')->where('user_id', $record->id)->whereIn('status', ['pending', 'under_review', 'approved'])->exists()) {
                return 'This user has a pending withdrawal.';
            }
            if (DB::table('tournament_registrations')->join('tournaments', 'tournaments.id', '=', 'tournament_registrations.tournament_id')->where('tournament_registrations.user_id', $record->id)->whereIn('tournament_registrations.status', ['pending', 'confirmed'])->whereNull('tournaments.deleted_at')->whereNotIn('tournaments.status', ['COMPLETED', 'CANCELLED', 'REFUNDED'])->exists()) {
                return 'This user is participating in an active competition.';
            }
            if (DB::table('tournament_registration_members')->join('tournament_registrations', 'tournament_registrations.id', '=', 'tournament_registration_members.registration_id')->join('tournaments', 'tournaments.id', '=', 'tournament_registrations.tournament_id')->where('tournament_registration_members.user_id', $record->id)->whereIn('tournament_registrations.status', ['pending', 'confirmed'])->whereNull('tournaments.deleted_at')->whereNotIn('tournaments.status', ['COMPLETED', 'CANCELLED', 'REFUNDED'])->exists()) {
                return 'This user belongs to a roster in an active competition.';
            }
            if (DB::table('head_to_head_challenges')->where('creator_user_id', $record->id)->where('status', 'waiting')->exists()) {
                return 'Cancel this user’s open Head-to-Head challenge first.';
            }
            if (DB::table('head_to_head_matches')->where(fn ($q) => $q->where('creator_user_id', $record->id)->orWhere('opponent_user_id', $record->id))->whereNotIn('status', ['completed', 'cancelled', 'expired'])->exists()) {
                return 'This user has an active Head-to-Head match.';
            }
        }

        return null;
    }

    public function delete(string $resource, array $ids, User $actor, ?int $parentId = null): int
    {
        abort_unless($actor->can($this->definition($resource)[1]), 403);
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === [] || count($ids) > 100 || min($ids) < 1) {
            throw ValidationException::withMessages(['selectedIds' => 'Select between 1 and 100 records.']);
        }

        return DB::transaction(function () use ($resource, $ids, $actor, $parentId): int {
            $records = $this->query($resource, $parentId)->whereKey($ids)->lockForUpdate()->get();
            if ($records->count() !== count($ids)) {
                throw ValidationException::withMessages(['selectedIds' => 'A selected record is unavailable. Refresh the selection.']);
            }
            foreach ($records as $record) {
                if ($reason = $this->blockedReason($record, $actor)) {
                    throw ValidationException::withMessages(['selectedIds' => $this->label($resource, $record).': '.$reason]);
                }
            }
            foreach ($records as $record) {
                if ($record instanceof Game || $record instanceof Platform || $record instanceof Advertisement || $record instanceof TournamentScheduleSlot) {
                    $record->update(['is_active' => false]);
                }
                if ($record instanceof StreamChannel) {
                    $record->update(['is_live' => false, 'is_public' => false]);
                }
                if ($record instanceof TranslationString) {
                    TranslationString::query()->where('key', $record->key)->delete();
                } else {
                    $record->delete();
                }
                activity()->causedBy($actor)->performedOn($record)->withProperties(['resource' => $resource, 'recoverable' => true])->log('admin_record_deleted');
            }

            if ($resource === 'countries') {
                app(CountryEligibilityService::class)->forget();
            }

            return $records->count();
        });
    }
}
