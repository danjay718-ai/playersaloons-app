<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ResetLocalTournamentData extends Command
{
    protected $signature = 'tournaments:reset-local
                            {--keep-templates : Keep tournament templates and V2 schedule slots}
                            {--force : Skip the interactive confirmation}';

    protected $description = 'Permanently remove local tournament instances and their cascaded match data.';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('This destructive reset command is available only when APP_ENV=local.');

            return self::FAILURE;
        }

        $tournamentIds = DB::table('tournaments')->select('id');
        $tournaments = DB::table('tournaments')->count();
        $matches = DB::table('matches')->whereIn('tournament_id', $tournamentIds)->count();
        $prizes = DB::table('prize_distributions')->whereIn('tournament_id', DB::table('tournaments')->select('id'))->count();
        $refunds = DB::table('refunds')->whereIn('tournament_id', DB::table('tournaments')->select('id'))->count();

        if ($prizes > 0 || $refunds > 0) {
            $this->error("Reset stopped: {$prizes} prize distribution(s) and {$refunds} refund(s) exist.");
            $this->line('Their wallet effects must not be orphaned. Use a fresh local database instead.');

            return self::FAILURE;
        }

        if ($tournaments === 0) {
            $this->info('No tournament instances exist. Nothing to reset.');

            return self::SUCCESS;
        }

        $templateMessage = $this->option('keep-templates')
            ? 'Templates and schedules will remain; the scheduler may generate occurrences again.'
            : 'Tournament templates and V2 schedule slots will also be removed.';

        $this->warn("This permanently deletes {$tournaments} tournament instance(s) and {$matches} tournament match(es).");
        $this->line($templateMessage);
        $this->line('Games, users, wallets, and wallet ledger balances are not changed.');

        if (! $this->option('force') && ! $this->confirm('Continue with the local tournament reset?')) {
            $this->info('Reset cancelled.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            // Direct query intentionally hard-deletes local instances, allowing
            // database foreign keys to remove tournament-only registrations,
            // brackets, matches, submissions, and related workflow records.
            DB::table('tournaments')->delete();

            if (! $this->option('keep-templates')) {
                DB::table('tournament_templates')->delete();
            }
        });

        $this->info('Local tournament reset completed.');

        return self::SUCCESS;
    }
}
