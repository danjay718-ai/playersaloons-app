<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_templates', function (Blueprint $table): void {
            $table->string('competition_type', 32)->default('tournament')->after('game_id');
            $table->string('recurrence_frequency', 16)->nullable()->after('is_recurring');
            $table->string('timezone', 64)->default('UTC')->after('recurrence_frequency');
            $table->timestamp('next_run_at')->nullable()->after('timezone');
            $table->timestamp('last_generated_at')->nullable()->after('next_run_at');
            $table->unsignedInteger('generation_lead_minutes')->default(1440)->after('last_generated_at');

            $table->index(['is_recurring', 'next_run_at'], 'templates_recurring_next_run_idx');
        });

        Schema::table('tournaments', function (Blueprint $table): void {
            $table->string('competition_type', 32)->default('tournament')->after('game_id');

            // Each template occurrence is immutable and may only be generated once.
            $table->unique(['template_id', 'start_at'], 'tournaments_template_start_unique');
            $table->index(['status', 'registration_open_at'], 'tournaments_status_reg_open_idx');
            $table->index(['status', 'registration_close_at'], 'tournaments_status_reg_close_idx');
            $table->index(['status', 'checkin_open_at'], 'tournaments_status_checkin_open_idx');
            $table->index(['status', 'checkin_close_at'], 'tournaments_status_checkin_close_idx');
            $table->index(['status', 'start_at'], 'tournaments_status_start_idx');
            $table->index(['competition_type', 'status'], 'tournaments_type_status_idx');
        });

        Schema::table('tournament_registrations', function (Blueprint $table): void {
            $table->index(['status', 'registered_at'], 'registrations_status_registered_idx');
            $table->index(['tournament_id', 'status', 'payment_status'], 'registrations_tournament_state_idx');
            $table->index(['user_id', 'status'], 'registrations_user_status_idx');
        });

        Schema::table('tournament_checkins', function (Blueprint $table): void {
            $table->index(['registration_id', 'status'], 'checkins_registration_status_idx');
        });

        Schema::table('tournament_participants', function (Blueprint $table): void {
            $table->unique(['tournament_id', 'user_id'], 'participants_tournament_user_unique');
            $table->unique(['tournament_id', 'registration_id'], 'participants_tournament_registration_unique');
        });

        Schema::table('matches', function (Blueprint $table): void {
            $table->index(['tournament_id', 'status'], 'matches_tournament_status_idx');
            $table->index(['player_a_registration_id', 'status'], 'matches_player_a_status_idx');
            $table->index(['player_b_registration_id', 'status'], 'matches_player_b_status_idx');
            $table->index(['winner_registration_id', 'status'], 'matches_winner_status_idx');
        });

        // Repair legacy rows that bypassed the state machine and therefore have
        // no completion timestamp. The original updated_at is the safest audit
        // approximation available for those records.
        DB::table('tournaments')
            ->where('status', 'COMPLETED')
            ->whereNull('completed_at')
            ->update(['completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table): void {
            $table->dropIndex('matches_tournament_status_idx');
            $table->dropIndex('matches_player_a_status_idx');
            $table->dropIndex('matches_player_b_status_idx');
            $table->dropIndex('matches_winner_status_idx');
        });

        Schema::table('tournament_participants', function (Blueprint $table): void {
            $table->dropUnique('participants_tournament_user_unique');
            $table->dropUnique('participants_tournament_registration_unique');
        });

        Schema::table('tournament_checkins', function (Blueprint $table): void {
            $table->dropIndex('checkins_registration_status_idx');
        });

        Schema::table('tournament_registrations', function (Blueprint $table): void {
            $table->dropIndex('registrations_status_registered_idx');
            $table->dropIndex('registrations_tournament_state_idx');
            $table->dropIndex('registrations_user_status_idx');
        });

        Schema::table('tournaments', function (Blueprint $table): void {
            $table->dropUnique('tournaments_template_start_unique');
            $table->dropIndex('tournaments_status_reg_open_idx');
            $table->dropIndex('tournaments_status_reg_close_idx');
            $table->dropIndex('tournaments_status_checkin_open_idx');
            $table->dropIndex('tournaments_status_checkin_close_idx');
            $table->dropIndex('tournaments_status_start_idx');
            $table->dropIndex('tournaments_type_status_idx');
            $table->dropColumn('competition_type');
        });

        Schema::table('tournament_templates', function (Blueprint $table): void {
            $table->dropIndex('templates_recurring_next_run_idx');
            $table->dropColumn([
                'competition_type',
                'recurrence_frequency',
                'timezone',
                'next_run_at',
                'last_generated_at',
                'generation_lead_minutes',
            ]);
        });
    }
};
