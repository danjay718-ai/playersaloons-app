<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * V2 has one entry window: from the occurrence opening until its scheduled
     * first-match time. `end_at` is the schedule/occurrence boundary, not an
     * extension of registration. Align previously materialized V2 rows too.
     */
    public function up(): void
    {
        DB::table('tournaments')
            ->where('workflow_version', 2)
            ->whereNotNull('start_at')
            ->update([
                'registration_close_at' => DB::raw('start_at'),
                'join_closes_at' => DB::raw('start_at'),
            ]);
    }

    public function down(): void
    {
        // The former values depended on each schedule window and cannot be
        // reconstructed reliably. Leaving the corrected cutoff in place is
        // safer than reopening historical occurrences.
    }
};
