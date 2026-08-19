<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table): void {
            $table->string('timezone', 64)->default(config('app.tournament_timezone', 'UTC'))->after('frequency');
            $table->timestamp('end_at')->nullable()->after('start_at');
            $table->unsignedInteger('registration_duration_minutes')->default(10)->after('registration_close_at');
            $table->unsignedInteger('extra_registration_minutes')->default(0)->after('registration_duration_minutes');
            $table->timestamp('extra_registration_started_at')->nullable()->after('extra_registration_minutes');
            $table->timestamp('registration_locked_at')->nullable()->after('extra_registration_started_at');
            $table->unsignedInteger('match_ready_minutes')->default(10)->after('waiting_time');
            $table->unsignedInteger('match_extra_wait_minutes')->default(10)->after('match_ready_minutes');
            $table->unsignedInteger('play_xp')->default(100)->after('winning_points');
            $table->unsignedInteger('winner_bonus_xp')->default(50)->after('play_xp');

            $table->index(['status', 'registration_close_at', 'extra_registration_started_at'], 'tournaments_registration_lifecycle_idx');
            $table->index(['status', 'end_at'], 'tournaments_status_end_idx');
        });

        // Existing rows keep their historical UTC instants. Their display timezone
        // falls back to the application setting unless a recurring template has one.
        DB::table('tournaments')->whereNull('end_at')->update([
            'end_at' => DB::raw('start_at'),
        ]);
        DB::table('tournaments')
            ->whereNotNull('registration_open_at')
            ->whereNotNull('registration_close_at')
            ->select(['id', 'registration_open_at', 'registration_close_at'])
            ->orderBy('id')
            ->chunkById(500, function ($tournaments): void {
                foreach ($tournaments as $tournament) {
                    $minutes = max(1, CarbonImmutable::parse($tournament->registration_open_at, 'UTC')
                        ->diffInMinutes(CarbonImmutable::parse($tournament->registration_close_at, 'UTC')));
                    DB::table('tournaments')->where('id', $tournament->id)->update(['registration_duration_minutes' => $minutes]);
                }
            });

        Schema::table('games', function (Blueprint $table): void {
            $table->json('game_id_settings')->nullable()->after('card_image_path');
        });

        Schema::create('user_game_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained('games')->restrictOnDelete();
            $table->foreignId('platform_id')->constrained('platforms')->restrictOnDelete();
            $table->string('game_id_value', 191);
            $table->string('region', 64)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'game_id', 'platform_id'], 'user_game_account_unique');
            $table->index(['game_id', 'platform_id']);
        });

        Schema::table('tournament_registrations', function (Blueprint $table): void {
            $table->string('game_id_value', 191)->nullable()->after('team_id');
            $table->string('ready_mode', 24)->default('auto')->after('game_id_value');
            $table->timestamp('locked_at')->nullable()->after('registered_at');
            $table->index(['tournament_id', 'status', 'locked_at'], 'tournament_registrations_lock_idx');
        });

        Schema::table('matches', function (Blueprint $table): void {
            $table->timestamp('ready_started_at')->nullable()->after('scheduled_at');
            $table->timestamp('ready_deadline_at')->nullable()->after('ready_started_at');
            $table->timestamp('extra_wait_started_at')->nullable()->after('ready_deadline_at');
            $table->timestamp('extra_wait_deadline_at')->nullable()->after('extra_wait_started_at');
            $table->timestamp('player_a_ready_at')->nullable()->after('extra_wait_deadline_at');
            $table->timestamp('player_b_ready_at')->nullable()->after('player_a_ready_at');
            $table->foreignId('absence_reported_by_registration_id')->nullable()->after('player_b_ready_at')
                ->constrained('tournament_registrations')->nullOnDelete();
            $table->string('lobby_code', 191)->nullable()->after('absence_reported_by_registration_id');
            $table->text('lobby_password')->nullable()->after('lobby_code');
            $table->string('server_region', 64)->nullable()->after('lobby_password');
            $table->text('lobby_instructions')->nullable()->after('server_region');
            $table->timestamp('double_no_show_at')->nullable()->after('lobby_instructions');

            $table->index(['status', 'ready_deadline_at'], 'matches_ready_deadline_idx');
            $table->index(['status', 'extra_wait_deadline_at'], 'matches_extra_wait_deadline_idx');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table): void {
            $table->dropForeign(['absence_reported_by_registration_id']);
            $table->dropIndex('matches_ready_deadline_idx');
            $table->dropIndex('matches_extra_wait_deadline_idx');
            $table->dropColumn([
                'ready_started_at', 'ready_deadline_at', 'extra_wait_started_at', 'extra_wait_deadline_at',
                'player_a_ready_at', 'player_b_ready_at', 'absence_reported_by_registration_id', 'lobby_code',
                'lobby_password', 'server_region', 'lobby_instructions', 'double_no_show_at',
            ]);
        });

        Schema::table('tournament_registrations', function (Blueprint $table): void {
            $table->dropIndex('tournament_registrations_lock_idx');
            $table->dropColumn(['game_id_value', 'ready_mode', 'locked_at']);
        });

        Schema::dropIfExists('user_game_accounts');

        Schema::table('games', fn (Blueprint $table) => $table->dropColumn('game_id_settings'));

        Schema::table('tournaments', function (Blueprint $table): void {
            $table->dropIndex('tournaments_registration_lifecycle_idx');
            $table->dropIndex('tournaments_status_end_idx');
            $table->dropColumn([
                'timezone', 'end_at', 'registration_duration_minutes', 'extra_registration_minutes',
                'extra_registration_started_at', 'registration_locked_at', 'match_ready_minutes',
                'match_extra_wait_minutes', 'play_xp', 'winner_bonus_xp',
            ]);
        });
    }
};
