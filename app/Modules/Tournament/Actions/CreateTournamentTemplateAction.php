<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Tournament\Models\TournamentTemplatePrize;
use App\Shared\Enums\CompetitionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTournamentTemplateAction
{
    /**
     * Create a new tournament template.
     *
     * @param  array{
     *     game_id: int,
     *     name: string,
     *     format?: string,
     *     max_participants: int,
     *     min_participants: int,
     *     entry_fee?: string|float,
     *     prize_model?: string,
     *     checkin_minutes?: int,
     *     is_recurring?: bool,
     *     settings_json?: array<string, mixed>,
     *     prizes?: array<int, array{
     *         position: int,
     *         amount?: string|float|null,
     *         percentage?: string|float|null,
     *     }>,
     * } $data
     */
    public function execute(array $data): TournamentTemplate
    {
        return DB::transaction(function () use ($data): TournamentTemplate {
            $template = new TournamentTemplate;
            $template->fill([
                'uuid' => Str::uuid()->toString(),
                'game_id' => $data['game_id'],
                'competition_type' => $data['competition_type'] ?? CompetitionType::TOURNAMENT,
                'name' => $data['name'],
                'format' => $data['format'] ?? 'single_elimination',
                'max_participants' => $data['max_participants'],
                'min_participants' => $data['min_participants'],
                'entry_fee' => $data['entry_fee'] ?? '0.00',
                'prize_model' => $data['prize_model'] ?? 'proportional',
                'checkin_minutes' => $data['checkin_minutes'] ?? 15,
                'is_recurring' => $data['is_recurring'] ?? false,
                'recurrence_frequency' => $data['recurrence_frequency'] ?? null,
                'timezone' => $data['timezone'] ?? 'UTC',
                'next_run_at' => $data['next_run_at'] ?? null,
                'generation_lead_minutes' => $data['generation_lead_minutes'] ?? 1440,
                'settings_json' => $data['settings_json'] ?? null,
                'is_auto_cancel_underfilled' => $data['is_auto_cancel_underfilled'] ?? false,
            ]);

            if ($template->competition_type === CompetitionType::HEAD_TO_HEAD) {
                $template->min_participants = 2;
                $template->max_participants = 2;
                $template->settings_json = array_merge($template->settings_json ?? [], ['team_size' => 1]);
            }
            $template->save();

            if (isset($data['prizes'])) {
                foreach ($data['prizes'] as $prizeData) {
                    $prize = new TournamentTemplatePrize;
                    $prize->fill([
                        'template_id' => $template->id,
                        'position' => $prizeData['position'],
                        'amount' => $prizeData['amount'] ?? null,
                        'percentage' => $prizeData['percentage'] ?? null,
                        'created_at' => now(),
                    ]);
                    $prize->save();
                }
            }

            return $template->load('prizes');
        });
    }
}
