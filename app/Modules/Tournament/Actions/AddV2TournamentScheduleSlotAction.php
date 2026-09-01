<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Models\TournamentScheduleSlot;
use App\Modules\Tournament\Models\TournamentTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/** Adds a slot without changing the parent template or historical occurrences. */
final class AddV2TournamentScheduleSlotAction
{
    /** @param array<string, mixed> $data */
    public function execute(TournamentTemplate $template, array $data): TournamentScheduleSlot
    {
        return DB::transaction(function () use ($template, $data): TournamentScheduleSlot {
            $template = TournamentTemplate::query()->lockForUpdate()->findOrFail($template->id);
            $frequency = $template->is_recurring ? $template->recurrence_frequency?->value : 'one_time';
            $day = match ($frequency) {
                'weekly' => 'w'.(int) $data['day_of_week'],
                'monthly' => 'm'.(int) $data['day_of_month'],
                'one_time' => 'once',
                default => 'd',
            };
            $identity = $day.':'.$data['local_start_time'];
            if ($template->scheduleSlots()->where('identity_key', $identity)->exists()) {
                throw new LogicException('A slot with this day and time already exists on this schedule.');
            }

            $overrides = array_filter([
                'name' => $data['name'] ?? null,
                'max_teams' => $data['max_teams'] ?? null,
                'entry_fee' => $data['entry_fee'] ?? null,
            ], static fn ($value) => $value !== null && $value !== '');

            return TournamentScheduleSlot::query()->create([
                'uuid' => Str::uuid()->toString(),
                'tournament_template_id' => $template->id,
                'identity_key' => $identity,
                'label' => $data['label'] ?? null,
                'local_start_time' => $data['local_start_time'],
                'schedule_start_at' => $data['schedule_start_at'],
                'schedule_end_at' => $data['schedule_end_at'],
                'day_of_week' => $data['day_of_week'] ?? null,
                'day_of_month' => $data['day_of_month'] ?? null,
                'overrides_json' => $overrides ?: null,
                'is_active' => true,
                'sort_order' => (int) $template->scheduleSlots()->max('sort_order') + 1,
            ]);
        }, 3);
    }
}
