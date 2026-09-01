<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Actions\GenerateRecurringTournamentAction;
use App\Modules\Tournament\Actions\MaterializeV2OccurrenceAction;
use App\Modules\Tournament\Models\TournamentScheduleSlot;
use App\Modules\Tournament\Models\TournamentTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AutoGenerateRecurringTournaments extends Command
{
    protected $signature = 'tournaments:auto-generate';

    protected $description = 'Generate due competition occurrences from active recurring templates.';

    public function handle(GenerateRecurringTournamentAction $generate, MaterializeV2OccurrenceAction $materialize): int
    {
        $creator = User::role('SUPER_ADMIN')->oldest('id')->first() ?? User::query()->oldest('id')->first();

        if ($creator === null) {
            $this->error('No system user is available to own generated competitions.');

            return self::FAILURE;
        }

        $maxLeadMinutes = (int) TournamentTemplate::query()
            ->where('is_recurring', true)
            ->max('generation_lead_minutes');
        $candidateCutoff = now()->addMinutes(max(0, $maxLeadMinutes));
        $created = 0;

        if (config('features.tournament_v2.enabled')) {
            TournamentScheduleSlot::query()
                ->where('is_active', true)
                ->whereHas('template', fn ($templates) => $templates->where('workflow_version', 2)->where('is_recurring', true))
                ->orderBy('id')
                ->chunkById(100, function ($slots) use ($creator, $materialize, &$created): void {
                    foreach ($slots as $slot) {
                        try {
                            $before = $slot->occurrences()->count();
                            $materialize->execute($slot, $creator);
                            $created += $slot->occurrences()->count() > $before ? 1 : 0;
                        } catch (Throwable $exception) {
                            Log::error('V2 occurrence generation failed.', ['schedule_slot_id' => $slot->id, 'exception' => $exception]);
                        }
                    }
                });
        }

        TournamentTemplate::query()
            ->where('is_recurring', true)
            ->where('workflow_version', 1)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $candidateCutoff)
            ->orderBy('id')
            ->chunkById(100, function ($templates) use ($creator, $generate, &$created): void {
                foreach ($templates as $template) {
                    $horizon = CarbonImmutable::now()->addMinutes($template->generation_lead_minutes);

                    try {
                        // A high lead time can cover several daily occurrences.
                        // The cap prevents corrupt template data from monopolizing a run.
                        for ($i = 0; $i < 32; $i++) {
                            $occurrence = $generate->execute($template, $creator, $horizon);

                            if ($occurrence === null) {
                                break;
                            }

                            $created++;
                            $template->refresh();
                        }
                    } catch (Throwable $exception) {
                        Log::error('Recurring competition generation failed.', [
                            'template_id' => $template->getKey(),
                            'exception' => $exception,
                        ]);
                        $this->error("Failed to generate template #{$template->getKey()}.");
                    }
                }
            });

        $this->info("Generated {$created} recurring competition occurrence(s).");

        return self::SUCCESS;
    }
}
