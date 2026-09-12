<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Stream\Actions\SyncTournamentStreamChannelsAction;
use App\Modules\Stream\Support\StreamEmbedService;
use App\Modules\Tournament\Actions\CreateRecurringCompetitionAction;
use App\Modules\Tournament\Actions\CreateTournamentAction;
use App\Modules\Tournament\Actions\PublishTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Support\CompetitionPlatforms;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\TournamentStatus;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeZone;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;

/**
 * @deprecated V1 editor retained only for historical V1 occurrences. New
 * tournament schedules are created through the additive V2 Blade workflow.
 */
class TournamentForm extends AdminComponent
{
    use WithFileUploads;

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('tournaments.create') || $this->actor()->can('tournaments.manage'), 403);
    }

    public bool $isEditMode = false;

    public bool $isLocked = false;

    public ?int $tournamentId = null;

    // Form fields
    public string $name = '';

    public int $game_id = 0;

    public string $competition_type = 'tournament';

    public int $max_participants = 16;

    public int $min_participants = 4;

    public string $entry_fee = '0.00';

    public string $prize_pool = '0.00';

    public string $registration_open_at = '';

    public string $registration_close_at = '';

    public string $checkin_open_at = '';

    public string $checkin_close_at = '';

    public string $start_at = '';

    public string $tournament_end_at = '';

    public int $registration_duration_value = 10;

    public string $registration_duration_unit = 'minutes';

    public int $extra_registration_value = 30;

    public string $extra_registration_unit = 'minutes';

    public string $description = '';

    public string $rules = '';

    public ?int $platform_id = null;

    public array $platform_ids = [];

    public string $frequency = '';

    public string $timezone = 'UTC';

    public bool $is_auto_cancel_underfilled = true;

    public bool $is_featured = false;

    public bool $publishOneTimeOnCreate = false;

    public ?int $waiting_time = null;

    public int $match_ready_value = 10;

    public string $match_ready_unit = 'minutes';

    public int $match_extra_wait_value = 10;

    public string $match_extra_wait_unit = 'minutes';

    public ?int $waiting_result_time = null;

    public int $team_size = 0;

    public ?string $prize_1st = '0.00';

    public ?string $prize_2nd = '0.00';

    public ?string $prize_3rd = '0.00';

    public ?int $winning_points = null;

    public int $play_xp = 100;

    public int $winner_bonus_xp = 50;

    public ?string $youtube_stream_url = null;

    public ?string $twitch_stream_url = null;

    public ?string $facebook_stream_url = null;

    public $banner;

    public function mount(?int $id = null): void
    {
        if ($id === null && config('features.tournament_v2.enabled')) {
            $this->redirectRoute('admin.tournaments.v2.create', navigate: false);

            return;
        }

        $this->timezone = (string) config('app.tournament_timezone', 'UTC');
        $this->waiting_result_time = (int) (SystemSetting::query()->where('key', 'tournament.waiting_result_time_default')->value('value') ?? 30);

        if ($id) {
            $this->isEditMode = true;
            $this->tournamentId = $id;
            $tournament = Tournament::findOrFail($id);

            // V2 occurrences are immutable snapshots managed through their schedule definition.
            // This legacy V1 form must never reinterpret or overwrite V2 financial/lifecycle fields.
            if ((int) $tournament->workflow_version === 2) {
                session()->flash('error', 'Tournament V2 occurrences cannot be edited with the legacy form. Edit the V2 schedule before its occurrence starts.');
                $this->redirect('/admin/tournaments', navigate: true);

                return;
            }

            // If not in DRAFT, it's a Limited Edit (Locked structural fields)
            if ($tournament->status !== TournamentStatus::DRAFT) {
                $this->isLocked = true;
            }

            // Final statuses are still strictly non-editable
            if (in_array($tournament->status, [TournamentStatus::COMPLETED, TournamentStatus::CANCELLED, TournamentStatus::REFUNDED])) {
                session()->flash('error', 'Completed or cancelled tournaments cannot be edited.');
                $this->redirect('/admin/tournaments', navigate: true);

                return;
            }

            $this->name = $tournament->name;
            $this->game_id = (int) $tournament->game_id;
            $this->competition_type = $tournament->competition_type->value;
            $this->max_participants = (int) $tournament->max_participants;
            $this->min_participants = (int) $tournament->min_participants;
            $this->entry_fee = (string) $tournament->entry_fee;
            $this->prize_pool = (string) ($tournament->advertised_prize_pool ?? $tournament->prize_pool);
            $this->timezone = $tournament->timezone ?: ($tournament->template?->timezone ?? (string) config('app.tournament_timezone', 'UTC'));
            $this->registration_open_at = $this->formatScheduleDate($tournament->registration_open_at);
            $this->start_at = $this->formatScheduleDate($tournament->start_at);
            $this->tournament_end_at = $this->formatScheduleDate($tournament->end_at ?? $tournament->start_at?->copy()->addDay());
            [$this->registration_duration_value, $this->registration_duration_unit] = $this->splitDuration((int) ($tournament->registration_duration_minutes ?: 10));
            [$this->extra_registration_value, $this->extra_registration_unit] = $this->splitDuration((int) ($tournament->extra_registration_minutes ?: 30));
            [$this->match_ready_value, $this->match_ready_unit] = $this->splitDuration((int) ($tournament->match_ready_minutes ?: $tournament->waiting_time ?: 10));
            [$this->match_extra_wait_value, $this->match_extra_wait_unit] = $this->splitDuration((int) ($tournament->match_extra_wait_minutes ?: 10));

            $this->description = $tournament->description ?? '';
            $this->rules = (string) ($tournament->getAttribute('rules') ?? '');
            $this->platform_ids = $tournament->supportedPlatformIds();
            $this->frequency = $tournament->frequency ?? 'daily';
            $this->is_auto_cancel_underfilled = (bool) $tournament->is_auto_cancel_underfilled;
            $this->is_featured = (bool) $tournament->is_featured;
            $this->waiting_time = $tournament->waiting_time;
            $this->waiting_result_time = $tournament->waiting_result_time;
            $this->team_size = $tournament->team_size ?? 1;
            $this->prize_1st = $tournament->prize_1st !== null ? (string) $tournament->prize_1st : '0.00';
            $this->prize_2nd = $tournament->prize_2nd !== null ? (string) $tournament->prize_2nd : '0.00';
            $this->prize_3rd = $tournament->prize_3rd !== null ? (string) $tournament->prize_3rd : '0.00';
            $this->winning_points = $tournament->winning_points;
            $this->play_xp = (int) ($tournament->play_xp ?: 100);
            $this->winner_bonus_xp = (int) ($tournament->winner_bonus_xp ?: $tournament->winning_points ?: 50);
            $streamChannels = $tournament->streamChannels()->get()->keyBy('provider');
            $this->youtube_stream_url = $streamChannels->get('youtube')?->source_url;
            $this->twitch_stream_url = $streamChannels->get('twitch')?->source_url;
            $this->facebook_stream_url = $streamChannels->get('facebook')?->source_url;
        } else {
            // New tournaments must explicitly choose a game and frequency.
            $this->game_id = 0;
            $this->frequency = '';
            $now = CarbonImmutable::now($this->timezone)->startOfMinute();
            $this->registration_open_at = $now->format('Y-m-d\TH:i');
            $this->tournament_end_at = $now->addDay()->format('Y-m-d\TH:i');
        }
    }

    public function updatingTimezone(string $newTimezone): void
    {
        if (! in_array($newTimezone, DateTimeZone::listIdentifiers(), true)) {
            return;
        }

        $oldTimezone = $this->timezone;
        foreach (['registration_open_at', 'tournament_end_at'] as $field) {
            if ($this->{$field} === '') {
                continue;
            }

            $this->{$field} = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->{$field}, $oldTimezone)
                ->setTimezone($newTimezone)
                ->format('Y-m-d\TH:i');
        }
    }

    public function updatedCompetitionType(string $type): void
    {
        if ($type === CompetitionType::HEAD_TO_HEAD->value) {
            $this->min_participants = 2;
            $this->max_participants = 2;
            $this->team_size = 1;
        }
    }

    public function updatedGameId(): void
    {
        $this->platform_ids = [];
        $this->platform_id = null;
    }

    private function platformRules(): array
    {
        // Keep legacy programmatic callers accepting a single platform.
        if ($this->platform_ids === [] && $this->platform_id !== null) {
            $this->platform_ids = [$this->platform_id];
        }
        if ($this->isLocked || ! Game::query()->whereKey($this->game_id)->whereHas('platforms')->exists()) {
            return ['platform_ids' => ['required', 'array', 'min:1'], 'platform_ids.*' => ['integer', 'distinct', 'exists:platforms,id']];
        }

        return CompetitionPlatforms::rules($this->game_id);
    }

    public function validateStep(int $step): bool
    {
        $rules = match ($step) {
            1 => [
                'name' => 'required|string|max:255',
                'game_id' => 'required|exists:games,id',
                'competition_type' => 'required|in:tournament,head_to_head',
                ...$this->platformRules(),
                'frequency' => 'required|string|in:daily,weekly,monthly,one-time',
                'entry_fee' => 'required|numeric|min:0',
            ],
            2 => [
                'description' => 'nullable|string',
                'rules' => 'nullable|string',
            ],
            3 => [
                'team_size' => 'required|integer|min:0',
                'min_participants' => 'required|integer|min:2',
                'max_participants' => 'required|integer|min:2|gte:min_participants',
                'play_xp' => 'required|integer|min:0|max:1000000',
                'winner_bonus_xp' => 'required|integer|min:0|max:1000000',
                'match_ready_value' => 'required|integer|min:1|max:365',
                'match_ready_unit' => 'required|in:minutes,hours,days',
                'match_extra_wait_value' => 'required|integer|min:1|max:365',
                'match_extra_wait_unit' => 'required|in:minutes,hours,days',
                'waiting_result_time' => 'required|integer|min:1',
                'youtube_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('youtube')],
                'twitch_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('twitch')],
                'facebook_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('facebook')],
            ],
            4 => [
                'timezone' => 'required|timezone:all',
                'registration_open_at' => 'required|date',
                'tournament_end_at' => 'required|date|after:registration_open_at',
                'registration_duration_value' => 'required|integer|min:1|max:365',
                'registration_duration_unit' => 'required|in:minutes,hours,days',
                'extra_registration_value' => 'required|integer|min:0|max:365',
                'extra_registration_unit' => 'required|in:minutes,hours,days',
            ],
            5 => [
                'prize_pool' => 'required|numeric|min:0',
            ],
            default => [],
        };

        if ($rules) {
            $this->validate($rules);
        }

        return true;
    }

    public function chooseOneTimeDraft(): void
    {
        $this->publishOneTimeOnCreate = false;
    }

    public function chooseOneTimePublish(): void
    {
        $this->publishOneTimeOnCreate = true;
    }

    public function saveTournament(
        CreateTournamentAction $createAction,
        CreateRecurringCompetitionAction $createRecurring,
        PublishTournamentAction $publishTournament,
        SyncTournamentStreamChannelsAction $syncStreams,
    ): void {
        // Enforce H2H invariants server-side; browser-disabled fields are not a
        // security or data-integrity boundary.
        $this->updatedCompetitionType($this->competition_type);

        $this->validate([
            'name' => 'required|string|max:255',
            'game_id' => 'required|exists:games,id',
            'competition_type' => 'required|in:tournament,head_to_head',
            'max_participants' => 'required|integer|min:2',
            'min_participants' => 'required|integer|min:2|lte:max_participants',
            'entry_fee' => 'required|numeric|min:0',
            'prize_pool' => 'required|numeric|min:0',
            'registration_open_at' => 'required|date',
            'tournament_end_at' => 'required|date|after:registration_open_at',
            'description' => 'nullable|string',
            'rules' => 'nullable|string',
            ...$this->platformRules(),
            'frequency' => 'required|string|in:daily,weekly,monthly,one-time',
            'timezone' => 'required|timezone:all',
            'is_auto_cancel_underfilled' => 'boolean',
            'waiting_time' => 'nullable|integer|min:0',
            'waiting_result_time' => 'required|integer|min:1',
            'team_size' => 'required|integer|min:0',
            'registration_duration_value' => 'required|integer|min:1|max:365',
            'registration_duration_unit' => 'required|in:minutes,hours,days',
            'extra_registration_value' => 'required|integer|min:0|max:365',
            'extra_registration_unit' => 'required|in:minutes,hours,days',
            'match_ready_value' => 'required|integer|min:1|max:365',
            'match_ready_unit' => 'required|in:minutes,hours,days',
            'match_extra_wait_value' => 'required|integer|min:1|max:365',
            'match_extra_wait_unit' => 'required|in:minutes,hours,days',
            'prize_1st' => 'nullable|numeric|min:0',
            'prize_2nd' => 'nullable|numeric|min:0',
            'prize_3rd' => 'nullable|numeric|min:0',
            'winning_points' => 'nullable|integer|min:0',
            'play_xp' => 'required|integer|min:0|max:1000000',
            'winner_bonus_xp' => 'required|integer|min:0|max:1000000',
            'youtube_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('youtube')],
            'twitch_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('twitch')],
            'facebook_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('facebook')],
            'banner' => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'is_featured' => 'boolean',
        ]);

        $allocatedPrizes = (float) ($this->prize_1st ?: 0) + (float) ($this->prize_2nd ?: 0) + (float) ($this->prize_3rd ?: 0);
        if ($allocatedPrizes > (float) $this->prize_pool) {
            $this->addError('prize_1st', 'The combined place prizes cannot exceed the prize pool.');

            return;
        }

        $creator = Auth::user();
        if (! $creator) {
            return;
        }

        // Admin inputs are wall-clock values in the selected tournament timezone.
        // We convert once at the boundary and persist UTC instants in the database.
        $registrationStartsAt = $this->parseScheduleDate($this->registration_open_at);
        $registrationDuration = $this->durationInMinutes($this->registration_duration_value, $this->registration_duration_unit);
        $extraRegistration = $this->durationInMinutes($this->extra_registration_value, $this->extra_registration_unit);
        $matchReady = $this->durationInMinutes($this->match_ready_value, $this->match_ready_unit);
        $matchExtraWait = $this->durationInMinutes($this->match_extra_wait_value, $this->match_extra_wait_unit);
        $registrationClosesAt = $registrationStartsAt->addMinutes($registrationDuration);
        $firstMatchAt = $registrationClosesAt->addMinutes($matchReady);
        $tournamentEndsAt = $this->parseScheduleDate($this->tournament_end_at);
        if ($tournamentEndsAt->lessThanOrEqualTo($firstMatchAt)) {
            $this->addError('tournament_end_at', 'Tournament Ends must be after the estimated first match.');

            return;
        }

        $data = [
            'name' => $this->name,
            'game_id' => $this->game_id,
            'competition_type' => $this->competition_type,
            'max_participants' => $this->max_participants,
            'min_participants' => $this->min_participants,
            'entry_fee' => $this->entry_fee,
            'prize_pool' => $this->prize_pool,
            'advertised_prize_pool' => $this->prize_pool,
            'registration_open_at' => $registrationStartsAt,
            'registration_close_at' => $registrationClosesAt,
            // Legacy timestamps remain populated while the state machine is
            // migrated to automatic readiness; there is no player check-in UI.
            'checkin_open_at' => $registrationClosesAt,
            'checkin_close_at' => $registrationClosesAt,
            'start_at' => $firstMatchAt,
            'end_at' => $tournamentEndsAt,
            'registration_duration_minutes' => $registrationDuration,
            'extra_registration_minutes' => $extraRegistration,
            'description' => $this->nullableRichText($this->description),
            'rules' => $this->nullableRichText($this->rules),
            'platform_id' => (int) $this->platform_ids[0],
            'platform_ids' => array_map('intval', $this->platform_ids),
            'frequency' => $this->frequency,
            'timezone' => $this->timezone,
            'is_auto_cancel_underfilled' => true,
            'is_featured' => $this->is_featured,
            'waiting_time' => $this->waiting_time,
            'match_ready_minutes' => $matchReady,
            'match_extra_wait_minutes' => $matchExtraWait,
            'waiting_result_time' => $this->waiting_result_time,
            'team_size' => $this->team_size,
            'prize_1st' => filled($this->prize_1st) ? $this->prize_1st : null,
            'prize_2nd' => filled($this->prize_2nd) ? $this->prize_2nd : null,
            'prize_3rd' => filled($this->prize_3rd) ? $this->prize_3rd : null,
            'winning_points' => $this->winning_points,
            'play_xp' => $this->play_xp,
            'winner_bonus_xp' => $this->winner_bonus_xp,
            'youtube_stream_url' => $this->nullableUrl($this->youtube_stream_url),
            'twitch_stream_url' => $this->nullableUrl($this->twitch_stream_url),
            'facebook_stream_url' => $this->nullableUrl($this->facebook_stream_url),
        ];

        if ($this->banner) {
            $path = $this->banner->store('tournaments', 'public');
            $data['banner_url'] = '/storage/'.$path;
        }

        if ($this->isEditMode && $this->tournamentId) {
            $tournament = Tournament::findOrFail($this->tournamentId);

            if ((int) $tournament->workflow_version === 2) {
                session()->flash('error', 'V2 occurrence snapshots cannot be edited through the legacy workflow.');

                return;
            }

            // Re-verify strictly final statuses
            if (in_array($tournament->status, [TournamentStatus::COMPLETED, TournamentStatus::CANCELLED, TournamentStatus::REFUNDED])) {
                session()->flash('error', 'Completed or cancelled tournaments cannot be edited.');

                return;
            }

            // If locked, filter out sensitive fields to ensure they are NOT updated
            if ($tournament->status !== TournamentStatus::DRAFT) {
                unset(
                    $data['game_id'],
                    $data['competition_type'],
                    $data['entry_fee'],
                    $data['prize_pool'],
                    $data['max_participants'],
                    $data['min_participants'],
                    $data['team_size'],
                    $data['platform_id'],
                    $data['platform_ids'],
                    $data['frequency'],
                    $data['is_auto_cancel_underfilled'],
                    $data['winning_points'],
                    $data['play_xp'],
                    $data['winner_bonus_xp'],
                    $data['prize_1st'],
                    $data['prize_2nd'],
                    $data['prize_3rd']
                );
            }

            unset($data['youtube_stream_url'], $data['twitch_stream_url'], $data['facebook_stream_url']);
            $tournament->update($data);
            $syncStreams->execute($tournament, $this->streamUrls(), $creator);
            session()->flash('success', 'Tournament updated successfully.');
        } else {
            $tournament = $this->frequency === 'one-time'
                ? $createAction->execute($data, $creator)
                : $createRecurring->execute($data, $creator);

            if ($this->frequency === 'one-time' && $this->publishOneTimeOnCreate) {
                $tournament = $publishTournament->execute($tournament);
            }

            $syncStreams->execute($tournament, $this->streamUrls(), $creator);
            session()->flash(
                'success',
                $this->frequency === 'one-time'
                    ? ($this->publishOneTimeOnCreate
                        ? 'Tournament published successfully.'
                        : 'Tournament saved as a draft.')
                    : 'Recurring competition schedule created successfully.',
            );
        }

        $this->redirect('/admin/tournaments', navigate: true);
    }

    private function streamUrlRule(string $provider): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($provider): void {
            if (! app(StreamEmbedService::class)->isValidProviderUrl($provider, is_string($value) ? $value : null)) {
                $fail('The '.$attribute.' must be a valid supported '.$provider.' stream URL.');
            }
        };
    }

    private function nullableUrl(?string $url): ?string
    {
        $url = is_string($url) ? trim($url) : '';

        return $url === '' ? null : $url;
    }

    private function nullableRichText(string $content): ?string
    {
        $plainText = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plainText = preg_replace('/\\x{00A0}/u', ' ', $plainText) ?? $plainText;

        return trim($plainText) === '' ? null : $content;
    }

    private function parseScheduleDate(string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, $this->timezone)->utc();
    }

    private function formatScheduleDate(mixed $value): string
    {
        return $value === null ? '' : CarbonImmutable::instance($value)->setTimezone($this->timezone)->format('Y-m-d\TH:i');
    }

    private function durationInMinutes(int $value, string $unit): int
    {
        return $value * match ($unit) {
            'days' => 1440,
            'hours' => 60,
            default => 1,
        };
    }

    /** @return array{int, string} */
    private function splitDuration(int $minutes): array
    {
        if ($minutes > 0 && $minutes % 1440 === 0) {
            return [(int) ($minutes / 1440), 'days'];
        }

        if ($minutes > 0 && $minutes % 60 === 0) {
            return [(int) ($minutes / 60), 'hours'];
        }

        return [$minutes, 'minutes'];
    }

    /** @return array<string, string|null> */
    private function streamUrls(): array
    {
        return [
            'youtube' => $this->nullableUrl($this->youtube_stream_url),
            'twitch' => $this->nullableUrl($this->twitch_stream_url),
            'facebook' => $this->nullableUrl($this->facebook_stream_url),
        ];
    }

    public function render()
    {
        $games = Game::query()->withTrashed()
            ->with('translations')
            ->where(fn ($query) => $query->where('is_active', true)->when($this->game_id > 0, fn ($games) => $games->orWhere('id', $this->game_id)))
            ->get();
        $platforms = $this->game_id > 0
            ? Game::query()->withTrashed()->find($this->game_id)?->platforms()->where('platforms.is_active', true)->orderBy('platforms.name')->get()
            : null;

        if ($platforms === null || $platforms->isEmpty()) {
            $platforms = Platform::where('is_active', true)->orderBy('name')->get();
        }
        if ($this->isEditMode) {
            $platforms = $platforms->merge(Platform::query()->whereIn('id', $this->platform_ids)->get());
        }

        return view('livewire.admin.tournament-form', [
            'games' => $games,
            'platforms' => $platforms,
            'timezones' => DateTimeZone::listIdentifiers(),
        ])->layout('components.layouts.admin', [
            'admin_title' => $this->isEditMode ? 'Edit Competition' : 'Create Competition',
        ]);
    }
}
