<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Modules\Tournament\Services\TournamentTimezone;
use App\Modules\Tournament\Support\CompetitionPlatforms;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

final class StoreV2TournamentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tournaments.create') === true
            || $this->user()?->can('tournaments.manage') === true;
    }

    public function rules(): array
    {
        return [
            'competition_type' => ['nullable', 'in:tournament,head_to_head'],
            'game_id' => ['required', 'integer', 'exists:games,id,deleted_at,NULL,is_active,1'],
            ...CompetitionPlatforms::rules($this->integer('game_id')),
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:10000'],
            'rules' => ['nullable', 'string', 'max:20000'],
            'banner' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'frequency' => ['required', 'in:one_time,daily,weekly,monthly'],
            'max_teams' => ['required', 'integer', 'min:2', 'max:128'],
            'entry_fee' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'winning_points' => ['required', 'integer', 'min:0', 'max:100000'],
            'waiting_result_time' => ['required', 'integer', 'min:1', 'max:120'],
            'is_featured' => ['nullable', 'boolean'],
            'round_duration_value' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'round_duration_unit' => ['nullable', 'required_with:round_duration_value', 'in:minutes,hours,days'],
            'full_first_percent' => ['required', 'numeric', 'min:0', 'max:90'],
            'full_second_percent' => ['required', 'numeric', 'min:0', 'max:90'],
            'slots' => ['required', 'array', 'min:1', 'max:50'],
            'slots.*.label' => ['nullable', 'string', 'max:100'],
            'slots.*.local_start_time' => ['required', 'date_format:H:i'],
            'slots.*.schedule_start_at' => ['required', 'date'],
            'slots.*.schedule_end_at' => ['required', 'date', 'after:slots.*.schedule_start_at'],
            'slots.*.day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'slots.*.day_of_month' => ['nullable', 'integer', 'between:1,31'],
            'slots.*.platform_id' => ['nullable', 'integer', 'exists:platforms,id,deleted_at,NULL'],
            'slots.*.name' => ['nullable', 'string', 'max:191'],
            'slots.*.max_teams' => ['nullable', 'integer', 'min:2', 'max:128'],
            'slots.*.entry_fee' => ['nullable', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'slots.*.winning_points' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'slots.*.waiting_result_time' => ['nullable', 'integer', 'min:1', 'max:120'],
            'slots.*.round_duration_value' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'slots.*.round_duration_unit' => ['nullable', 'required_with:slots.*.round_duration_value', 'in:minutes,hours,days'],
            'slots.*.full_first_percent' => ['nullable', 'numeric', 'min:0', 'max:90'],
            'slots.*.full_second_percent' => ['nullable', 'numeric', 'min:0', 'max:90'],
            'slots.*.description' => ['nullable', 'string', 'max:10000'],
            'slots.*.rules' => ['nullable', 'string', 'max:20000'],
            'slots.*.banner' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('platform_ids') && $this->filled('platform_id')) {
            $this->merge(['platform_ids' => [$this->input('platform_id')]]);
        }
        // The dedicated platform H2H form posts this value as a hidden field.
        // Keeping the invariant here makes direct HTTP requests fail safely too.
        if ($this->input('competition_type') === 'head_to_head') {
            $this->merge(['max_teams' => 2]);
        }

        $this->merge([
            'full_first_percent' => $this->input('max_teams') <= 4 ? 90 : $this->input('full_first_percent', 75),
            'full_second_percent' => $this->input('max_teams') <= 4 ? 0 : $this->input('full_second_percent', 15),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $identities = [];
            if ((float) $this->input('full_first_percent') + (float) $this->input('full_second_percent') !== 90.0) {
                $validator->errors()->add('full_first_percent', 'First and Second Prize must total 90%.');
            }
            if ($this->integer('max_teams') % 2 !== 0) {
                $validator->errors()->add('max_teams', 'Maximum teams must be an even number.');
            }
            if ($this->input('competition_type') === 'head_to_head' && $this->integer('max_teams') !== 2) {
                $validator->errors()->add('max_teams', 'A Head-to-Head schedule always has exactly two player slots.');
            }
            foreach ((array) $this->input('slots', []) as $index => $slot) {
                $timezone = app(TournamentTimezone::class)->value();
                $start = isset($slot['schedule_start_at'])
                    ? CarbonImmutable::parse($slot['schedule_start_at'], $timezone)
                    : null;
                $now = CarbonImmutable::now($timezone);
                if ($start !== null && $start->startOfDay()->lessThan($now->startOfDay())) {
                    $validator->errors()->add("slots.{$index}.schedule_start_at", 'Start date cannot be in the past.');
                }
                if ($this->input('frequency') === 'one_time' && $start !== null && $start->lessThan($now)) {
                    $validator->errors()->add("slots.{$index}.schedule_start_at", 'A one-time tournament must start in the future.');
                }
                if (isset($slot['platform_id'])) {
                    $belongsToGame = DB::table('game_platform')
                        ->where('game_id', $this->integer('game_id'))
                        ->where('platform_id', (int) $slot['platform_id'])->exists();
                    if (! $belongsToGame) {
                        $validator->errors()->add("slots.{$index}.platform_id", 'The slot platform must belong to the selected game.');
                    }
                }
                if (isset($slot['full_first_percent']) || isset($slot['full_second_percent'])) {
                    if ((float) ($slot['full_first_percent'] ?? 0) + (float) ($slot['full_second_percent'] ?? 0) !== 90.0) {
                        $validator->errors()->add("slots.{$index}.full_first_percent", 'Slot First and Second Prize percentages must total 90%.');
                    }
                }
                if (isset($slot['max_teams']) && (int) $slot['max_teams'] % 2 !== 0) {
                    $validator->errors()->add("slots.{$index}.max_teams", 'Maximum teams must be an even number.');
                }
                if ($this->input('competition_type') === 'head_to_head' && isset($slot['max_teams']) && (int) $slot['max_teams'] !== 2) {
                    $validator->errors()->add("slots.{$index}.max_teams", 'A Head-to-Head slot cannot override the two-player limit.');
                }
                $day = match ($this->input('frequency')) {
                    'weekly' => 'w'.($slot['day_of_week'] ?? ''),
                    'monthly' => 'm'.($slot['day_of_month'] ?? ''),
                    'one_time' => 'once',
                    default => 'd',
                };
                $identity = $day.':'.($slot['local_start_time'] ?? '');
                if (in_array($identity, $identities, true)) {
                    $validator->errors()->add("slots.{$index}.local_start_time", 'Schedule rows must have a unique day and time.');
                }
                $identities[] = $identity;
            }
        });
    }
}
