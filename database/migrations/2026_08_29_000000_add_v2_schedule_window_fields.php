<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_schedule_slots', function (Blueprint $table): void {
            // V2-only anchors. Recurring slots use their interval as the window
            // for future occurrences; one-time slots use the exact timestamps.
            $table->timestamp('schedule_start_at')->nullable()->after('local_start_time');
            $table->timestamp('schedule_end_at')->nullable()->after('schedule_start_at');
            $table->index(['is_active', 'schedule_start_at'], 'template_slots_start_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_schedule_slots', function (Blueprint $table): void {
            $table->dropIndex('template_slots_start_idx');
            $table->dropColumn(['schedule_start_at', 'schedule_end_at']);
        });
    }
};
