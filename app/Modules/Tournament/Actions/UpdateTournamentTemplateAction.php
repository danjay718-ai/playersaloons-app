<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Tournament\Models\TournamentTemplatePrize;
use Illuminate\Support\Facades\DB;

class UpdateTournamentTemplateAction
{
    /**
     * Update an existing tournament template.
     *
     * @param  array{
     *     game_id?: int,
     *     name?: string,
     *     format?: string,
     *     max_participants?: int,
     *     min_participants?: int,
     *     entry_fee?: string|float,
     *     prize_model?: string,
     *     checkin_minutes?: int,
     *     is_recurring?: bool,
     *     settings_json?: array<string, mixed>|null,
     *     prizes?: array<int, array{
     *         position: int,
     *         amount?: string|float|null,
     *         percentage?: string|float|null,
     *     }>,
     * } $data
     */
    public function execute(TournamentTemplate $template, array $data): TournamentTemplate
    {
        return DB::transaction(function () use ($template, $data): TournamentTemplate {
            $template->fill(array_filter($data, fn ($key) => $key !== 'prizes', ARRAY_FILTER_USE_KEY));
            $template->save();

            if (isset($data['prizes'])) {
                // Remove existing prizes
                $template->prizes()->delete();

                // Add new prizes
                foreach ($data['prizes'] as $prizeData) {
                    $prize = new TournamentTemplatePrize();
                    $prize->fill([
                        'template_id' => $template->id,
                        'position'    => $prizeData['position'],
                        'amount'      => $prizeData['amount'] ?? null,
                        'percentage'  => $prizeData['percentage'] ?? null,
                        'created_at'  => now(),
                    ]);
                    $prize->save();
                }
            }

            return $template->load('prizes');
        });
    }
}
