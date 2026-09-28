<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Modules\Tournament\Models\TournamentTemplate;
use App\Shared\Enums\CompetitionType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class StoreV2TournamentScheduleSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tournaments.manage') === true;
    }

    public function rules(): array
    {
        return ['label' => ['nullable', 'string', 'max:100'], 'local_start_time' => ['nullable', 'date_format:H:i'], 'schedule_start_at' => ['required', 'date'], 'schedule_end_at' => ['required', 'date', 'after:schedule_start_at'], 'day_of_week' => ['nullable', 'integer', 'between:0,6'], 'day_of_month' => ['nullable', 'integer', 'between:1,31'], 'name' => ['nullable', 'string', 'max:191'], 'max_teams' => ['nullable', 'integer', 'min:2', 'max:128'], 'entry_fee' => ['nullable', 'regex:/^\d+(?:\.\d{1,2})?$/'], 'free_prize_1st' => ['nullable', 'numeric', 'min:0'], 'free_prize_2nd' => ['nullable', 'numeric', 'min:0'], 'is_featured' => ['nullable', 'boolean']];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $template = $this->route('template');
            if (! $template instanceof TournamentTemplate) {
                return;
            }
            $start = CarbonImmutable::parse($this->input('schedule_start_at'), $template->timezone);
            if ($start->startOfDay()->lessThan(now($template->timezone)->startOfDay())) {
                $validator->errors()->add('schedule_start_at', 'Start date cannot be in the past.');
            }
            if (! $template->is_recurring && $start->lessThan(now($template->timezone))) {
                $validator->errors()->add('schedule_start_at', 'A one-time slot must start in the future.');
            }
            if ($this->filled('max_teams') && $this->integer('max_teams') % 2 !== 0) {
                $validator->errors()->add('max_teams', 'Maximum teams must be an even number.');
            }
            if ($template->competition_type === CompetitionType::HEAD_TO_HEAD && $this->filled('max_teams') && $this->integer('max_teams') !== 2) {
                $validator->errors()->add('max_teams', 'A Head-to-Head slot always has two players.');
            }
            $effectiveFee = (float) ($this->input('entry_fee') !== null ? $this->input('entry_fee') : $template->entry_fee);
            $hasSponsoredFields = $this->filled('free_prize_1st') || $this->filled('free_prize_2nd');
            if ($template->competition_type === CompetitionType::HEAD_TO_HEAD && $hasSponsoredFields) {
                $validator->errors()->add('free_prize_1st', 'Sponsored prizes are available only for free tournaments.');
            } elseif ($effectiveFee > 0 && $hasSponsoredFields) {
                $validator->errors()->add('free_prize_1st', 'Paid slots cannot define sponsored prizes.');
            } elseif ($template->competition_type !== CompetitionType::HEAD_TO_HEAD && $effectiveFee === 0.0
                && (float) $template->entry_fee > 0 && ! $this->filled('free_prize_1st')) {
                $validator->errors()->add('free_prize_1st', 'First Prize is required for a free slot.');
            }
            if ($template->recurrence_frequency?->value === 'weekly' && ! $this->filled('day_of_week')) {
                $validator->errors()->add('day_of_week', 'Select a day of week.');
            }
            if ($template->recurrence_frequency?->value === 'monthly' && ! $this->filled('day_of_month')) {
                $validator->errors()->add('day_of_month', 'Enter a day of month.');
            }
        });
    }
}
