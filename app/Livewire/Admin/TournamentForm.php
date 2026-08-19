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
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\TournamentStatus;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;

class TournamentForm extends AdminComponent
{
    use WithFileUploads;

    public bool $isEditMode = false;

    public bool $isLocked = false;

    public ?int $tournamentId = null;

    public int $step = 1;

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

    public string $description = '';

    public string $rules = '';

    public ?int $platform_id = null;

    public string $frequency = 'daily';

    public string $timezone = 'UTC';

    public bool $is_auto_cancel_underfilled = false;

    public bool $is_featured = false;

    public ?int $waiting_time = null;

    public ?int $waiting_result_time = null;

    public int $team_size = 1;

    public ?string $prize_1st = null;

    public ?string $prize_2nd = null;

    public ?string $prize_3rd = null;

    public ?int $winning_points = null;

    public ?string $youtube_stream_url = null;

    public ?string $twitch_stream_url = null;

    public ?string $facebook_stream_url = null;

    public $banner;

    public function mount(?int $id = null): void
    {
        $this->rules = $this->getDefaultRules();
        $this->waiting_result_time = (int) (SystemSetting::query()->where('key', 'tournament.waiting_result_time_default')->value('value') ?? 30);

        if ($id) {
            $this->isEditMode = true;
            $this->tournamentId = $id;
            $tournament = Tournament::findOrFail($id);

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
            $this->prize_pool = (string) $tournament->prize_pool;
            $this->timezone = $tournament->template?->timezone ?? 'UTC';
            $this->registration_open_at = $this->formatScheduleDate($tournament->registration_open_at);
            $this->registration_close_at = $this->formatScheduleDate($tournament->registration_close_at);
            $this->checkin_open_at = $this->formatScheduleDate($tournament->checkin_open_at);
            $this->checkin_close_at = $this->formatScheduleDate($tournament->checkin_close_at);
            $this->start_at = $this->formatScheduleDate($tournament->start_at);

            $this->description = $tournament->description ?? '';
            $this->rules = (string) ($tournament->getAttribute('rules') ?: $this->getDefaultRules());
            $this->platform_id = $tournament->platform_id;
            $this->frequency = $tournament->frequency ?? 'daily';
            $this->is_auto_cancel_underfilled = (bool) $tournament->is_auto_cancel_underfilled;
            $this->is_featured = (bool) $tournament->is_featured;
            $this->waiting_time = $tournament->waiting_time;
            $this->waiting_result_time = $tournament->waiting_result_time;
            $this->team_size = $tournament->team_size ?? 1;
            $this->prize_1st = $tournament->prize_1st !== null ? (string) $tournament->prize_1st : null;
            $this->prize_2nd = $tournament->prize_2nd !== null ? (string) $tournament->prize_2nd : null;
            $this->prize_3rd = $tournament->prize_3rd !== null ? (string) $tournament->prize_3rd : null;
            $this->winning_points = $tournament->winning_points;
            $streamChannels = $tournament->streamChannels()->get()->keyBy('provider');
            $this->youtube_stream_url = $streamChannels->get('youtube')?->source_url;
            $this->twitch_stream_url = $streamChannels->get('twitch')?->source_url;
            $this->facebook_stream_url = $streamChannels->get('facebook')?->source_url;
        } else {
            /** @var Game|null $firstGame */
            $firstGame = Game::first();
            $this->game_id = $firstGame !== null ? $firstGame->id : 0;
            $this->frequency = 'one-time';
        }
    }

    protected function getDefaultRules(): string
    {
        return '<ul><li>Respect all players and admins.</li><li>Ensure a stable internet connection.</li><li>Check-in is required 15 mins before start.</li><li>Disputes must be submitted with screenshots.</li><li>Unsportsmanlike behavior will result in disqualification.</li></ul>';
    }

    public function updatedCompetitionType(string $type): void
    {
        if ($type === CompetitionType::HEAD_TO_HEAD->value) {
            $this->min_participants = 2;
            $this->max_participants = 2;
            $this->team_size = 1;
        }
    }

    public function validateStep(int $step): bool
    {
        $rules = match ($step) {
            1 => [
                'name' => 'required|string|max:255',
                'game_id' => 'required|exists:games,id',
                'competition_type' => 'required|in:tournament,head_to_head',
                'description' => 'required|string|min:10',
                'rules' => 'required|string|min:10',
            ],
            2 => [
                'platform_id' => 'required|exists:platforms,id',
                'frequency' => 'required|string|in:daily,weekly,monthly,one-time',
                'timezone' => 'required|timezone:all',
                'team_size' => 'required|integer|min:1',
                'winning_points' => 'nullable|integer|min:0',
                'waiting_result_time' => 'required|integer|min:1',
                'youtube_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('youtube')],
                'twitch_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('twitch')],
                'facebook_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('facebook')],
            ],
            3 => [
                'registration_open_at' => 'required|date',
                'registration_close_at' => 'required|date|after:registration_open_at',
                'checkin_open_at' => 'required|date|after:registration_close_at',
                'checkin_close_at' => 'required|date|after:checkin_open_at',
                'start_at' => 'required|date|after:checkin_close_at',
            ],
            4 => [
                'entry_fee' => 'required|numeric|min:0',
                'prize_pool' => 'required|numeric|min:0',
                'min_participants' => 'required|integer|min:2',
                'max_participants' => 'required|integer|min:2|gte:min_participants',
            ],
            default => [],
        };

        if ($rules) {
            $this->validate($rules);
        }

        return true;
    }

    public function saveTournament(
        CreateTournamentAction $createAction,
        CreateRecurringCompetitionAction $createRecurring,
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
            'registration_close_at' => 'required|date|after:registration_open_at',
            'checkin_open_at' => 'required|date|after:registration_close_at',
            'checkin_close_at' => 'required|date|after:checkin_open_at',
            'start_at' => 'required|date|after:checkin_close_at',
            'description' => 'required|string',
            'rules' => 'required|string',
            'platform_id' => 'required|exists:platforms,id',
            'frequency' => 'required|string|in:daily,weekly,monthly,one-time',
            'timezone' => 'required|timezone:all',
            'is_auto_cancel_underfilled' => 'boolean',
            'waiting_time' => 'nullable|integer|min:0',
            'waiting_result_time' => 'required|integer|min:1',
            'team_size' => 'required|integer|min:1',
            'prize_1st' => 'nullable|numeric|min:0',
            'prize_2nd' => 'nullable|numeric|min:0',
            'prize_3rd' => 'nullable|numeric|min:0',
            'winning_points' => 'nullable|integer|min:0',
            'youtube_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('youtube')],
            'twitch_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('twitch')],
            'facebook_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('facebook')],
            'banner' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:width=1280,height=720',
            'is_featured' => 'boolean',
        ]);

        if (! $this->isEditMode) {
            $this->validate(['start_at' => 'after:now']);
        }

        $creator = Auth::user();
        if (! $creator) {
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
            'registration_open_at' => $this->parseScheduleDate($this->registration_open_at),
            'registration_close_at' => $this->parseScheduleDate($this->registration_close_at),
            'checkin_open_at' => $this->parseScheduleDate($this->checkin_open_at),
            'checkin_close_at' => $this->parseScheduleDate($this->checkin_close_at),
            'start_at' => $this->parseScheduleDate($this->start_at),
            'description' => $this->description,
            'rules' => $this->rules,
            'platform_id' => $this->platform_id,
            'frequency' => $this->frequency,
            'timezone' => $this->timezone,
            'is_auto_cancel_underfilled' => $this->is_auto_cancel_underfilled,
            'is_featured' => $this->is_featured,
            'waiting_time' => $this->waiting_time,
            'waiting_result_time' => $this->waiting_result_time,
            'team_size' => $this->team_size,
            'prize_1st' => $this->prize_1st,
            'prize_2nd' => $this->prize_2nd,
            'prize_3rd' => $this->prize_3rd,
            'winning_points' => $this->winning_points,
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
                    $data['frequency'],
                    $data['is_auto_cancel_underfilled'],
                    $data['winning_points']
                );
            }

            unset($data['timezone']);
            unset($data['youtube_stream_url'], $data['twitch_stream_url'], $data['facebook_stream_url']);
            $tournament->update($data);
            $syncStreams->execute($tournament, $this->streamUrls(), $creator);
            session()->flash('success', 'Tournament updated successfully.');
        } else {
            $tournament = $this->frequency === 'one-time'
                ? $createAction->execute($data, $creator)
                : $createRecurring->execute($data, $creator);
            $syncStreams->execute($tournament, $this->streamUrls(), $creator);
            session()->flash(
                'success',
                $this->frequency === 'one-time'
                    ? 'Competition created successfully.'
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

    private function parseScheduleDate(string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, $this->timezone)->utc();
    }

    private function formatScheduleDate(mixed $value): string
    {
        return $value === null ? '' : CarbonImmutable::instance($value)->setTimezone($this->timezone)->format('Y-m-d\TH:i');
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
        } elseif ($this->platform_id && ! $platforms->contains('id', $this->platform_id)) {
            $currentPlatform = Platform::query()->find($this->platform_id);
            if ($currentPlatform) {
                $platforms->push($currentPlatform);
            }
        }

        return view('livewire.admin.tournament-form', [
            'games' => $games,
            'platforms' => $platforms,
            'timezones' => [
                'UTC',
                'Asia/Manila',
                'Asia/Singapore',
                'Asia/Tokyo',
                'Australia/Sydney',
                'Europe/London',
                'America/Los_Angeles',
                'America/New_York',
            ],
        ])->layout('components.layouts.admin', [
            'admin_title' => $this->isEditMode ? 'Edit Competition' : 'Create Competition',
        ]);
    }
}
