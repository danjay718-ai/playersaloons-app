<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\TournamentLifecycleReconciler;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReconcileTournamentLifecycle extends Command
{
    protected $signature = 'tournaments:reconcile-lifecycle {--tournament= : Reconcile only one tournament ID}';

    protected $description = 'Advance due tournament lifecycle states safely and idempotently.';

    public function handle(TournamentLifecycleReconciler $reconciler): int
    {
        $processed = 0;
        $failures = 0;

        $this->dueQuery()
            ->when($this->option('tournament'), fn (Builder $query, mixed $id) => $query->whereKey((int) $id))
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($tournaments) use ($reconciler, &$processed, &$failures): void {
                foreach ($tournaments as $tournament) {
                    try {
                        $reconciler->reconcile((int) $tournament->getKey());
                        $processed++;
                    } catch (Throwable $exception) {
                        $failures++;
                        Log::error('Tournament lifecycle reconciliation failed.', [
                            'tournament_id' => $tournament->getKey(),
                            'exception' => $exception,
                        ]);
                    }
                }
            });

        $this->info("Reconciled {$processed} tournament(s); {$failures} failed.");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return Builder<Tournament> */
    private function dueQuery(): Builder
    {
        return Tournament::query()->where(function (Builder $query): void {
            $query
                ->where(fn (Builder $due) => $due
                    ->where('workflow_version', 2)
                    ->whereIn('status', [
                        TournamentStatus::REGISTRATION_OPEN,
                        TournamentStatus::BRACKET_GENERATED,
                    ])
                    ->where(function (Builder $clock): void {
                        $clock->where('start_at', '<=', now())
                            ->orWhere('join_closes_at', '<=', now());
                    }))
                ->orWhere(fn (Builder $legacy) => $legacy
                    ->where('workflow_version', 1)
                    ->where(fn (Builder $legacyDue) => $legacyDue
                        ->where(fn (Builder $due) => $due
                            ->where('status', TournamentStatus::PUBLISHED)
                            ->where('registration_open_at', '<=', now()))
                        ->orWhere(fn (Builder $due) => $due
                            ->where('status', TournamentStatus::REGISTRATION_OPEN)
                            ->where('registration_close_at', '<=', now()))
                        ->orWhere(fn (Builder $due) => $due
                            ->where('status', TournamentStatus::REGISTRATION_CLOSED)
                            ->where('checkin_open_at', '<=', now()))
                        ->orWhere(fn (Builder $due) => $due
                            ->where('status', TournamentStatus::CHECKIN_OPEN)
                            ->where('checkin_close_at', '<=', now()))
                        ->orWhere(fn (Builder $due) => $due
                            ->where('status', TournamentStatus::CHECKIN_CLOSED))
                        ->orWhere(fn (Builder $due) => $due
                            ->where('status', TournamentStatus::BRACKET_GENERATED)
                            ->where('start_at', '<=', now()))));
        });
    }
}
