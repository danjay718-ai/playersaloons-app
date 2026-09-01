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
        Schema::create('game_tournament_defaults', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->unique()->constrained('games')->cascadeOnDelete();
            $table->foreignId('default_platform_id')->nullable()->constrained('platforms')->nullOnDelete();
            $table->string('tournament_banner_path')->nullable();
            $table->text('description')->nullable();
            $table->text('rules')->nullable();
            $table->timestamps();
        });

        Schema::table('tournament_templates', function (Blueprint $table): void {
            $table->unsignedTinyInteger('workflow_version')->default(1)->after('uuid');
            $table->foreignId('created_by')->nullable()->after('game_id')->constrained('users')->nullOnDelete();
        });

        Schema::create('tournament_schedule_slots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tournament_template_id')->constrained('tournament_templates')->cascadeOnDelete();
            $table->string('identity_key', 64);
            $table->string('label')->nullable();
            $table->string('local_start_time', 8);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->json('overrides_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['tournament_template_id', 'local_start_time', 'day_of_week', 'day_of_month'],
                'template_slot_time_unique'
            );
            $table->unique(['tournament_template_id', 'identity_key'], 'template_slot_identity_unique');
            $table->index(['tournament_template_id', 'is_active', 'sort_order'], 'template_slots_active_idx');
        });

        Schema::table('tournaments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('workflow_version')->default(1)->after('uuid');
            $table->foreignId('schedule_slot_id')->nullable()->after('template_id')
                ->constrained('tournament_schedule_slots')->nullOnDelete();
            $table->string('occurrence_period_key', 32)->nullable()->after('schedule_slot_id');
            $table->timestamp('join_closes_at')->nullable()->after('end_at');
            $table->unsignedInteger('round_duration_seconds')->nullable()->after('waiting_result_time');
            $table->unsignedSmallInteger('full_first_bps')->default(7500)->after('prize_3rd');
            $table->unsignedSmallInteger('full_second_bps')->default(1500)->after('full_first_bps');
            $table->unsignedSmallInteger('full_platform_bps')->default(1000)->after('full_second_bps');
            $table->unsignedSmallInteger('underfilled_first_bps')->default(8500)->after('full_platform_bps');
            $table->unsignedSmallInteger('underfilled_platform_bps')->default(1500)->after('underfilled_first_bps');
            $table->unsignedSmallInteger('financial_calculation_version')->default(1)->after('underfilled_platform_bps');
            $table->unsignedInteger('finalized_joined_entries')->nullable()->after('financial_calculation_version');
            $table->decimal('finalized_gross_pool', 18, 2)->nullable()->after('finalized_joined_entries');
            $table->decimal('finalized_commission_amount', 18, 2)->nullable()->after('finalized_gross_pool');
            $table->decimal('finalized_first_prize', 18, 2)->nullable()->after('finalized_commission_amount');
            $table->decimal('finalized_second_prize', 18, 2)->nullable()->after('finalized_first_prize');
            $table->timestamp('financial_finalized_at')->nullable()->after('finalized_second_prize');
            $table->string('payout_status', 24)->default('pending')->after('financial_finalized_at');
            $table->string('completion_reason', 64)->nullable()->after('payout_status');

            $table->unique(
                ['schedule_slot_id', 'occurrence_period_key'],
                'tournament_slot_period_unique'
            );
            $table->index(['workflow_version', 'status', 'join_closes_at'], 'tournament_v2_lifecycle_idx');
        });

        Schema::create('tournament_cancellation_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('tournament_registrations')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('eligible_voter_count')->default(0);
            $table->json('eligible_voter_ids');
            $table->unsignedInteger('required_approvals')->default(0);
            $table->timestamp('requested_at');
            $table->timestamp('expires_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'status'], 'cancellation_request_active_idx');
        });

        Schema::create('tournament_cancellation_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('tournament_cancellation_requests')->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('approved');
            $table->timestamp('voted_at');
            $table->unique(['request_id', 'voter_id'], 'cancellation_vote_unique');
        });

        Schema::create('match_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 24)->default('open');
            $table->timestamp('first_submitted_at')->nullable();
            $table->timestamp('result_deadline_at')->nullable();
            $table->timestamp('stalled_deadline_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 32)->nullable();
            $table->timestamps();
            $table->unique(['match_id', 'attempt_number'], 'match_attempt_number_unique');
            $table->index(['status', 'result_deadline_at'], 'match_attempt_result_deadline_idx');
            $table->index(['status', 'stalled_deadline_at'], 'match_attempt_stalled_deadline_idx');
        });

        Schema::table('match_result_submissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('winner_registration_id')->nullable()->change();
            $table->foreignId('match_attempt_id')->nullable()->after('match_id')
                ->constrained('match_attempts')->cascadeOnDelete();
            $table->foreignId('registration_id')->nullable()->after('submitted_by')
                ->constrained('tournament_registrations')->cascadeOnDelete();
            $table->string('outcome', 16)->nullable()->after('winner_registration_id');
            $table->unique(['match_attempt_id', 'registration_id'], 'match_attempt_registration_unique');
        });

        Schema::table('matches', function (Blueprint $table): void {
            $table->unsignedInteger('active_attempt_number')->default(1)->after('status');
            $table->timestamp('stalled_deadline_at')->nullable()->after('result_submitted_at');
            $table->timestamp('round_deadline_at')->nullable()->after('stalled_deadline_at');
            $table->timestamp('final_resolution_eligible_at')->nullable()->after('round_deadline_at');
            $table->timestamp('final_resolution_notified_at')->nullable()->after('final_resolution_eligible_at');
            $table->string('resolution_reason', 64)->nullable()->after('stalled_deadline_at');
            $table->index(['status', 'round_deadline_at'], 'match_round_deadline_idx');
            $table->index(['status', 'final_resolution_eligible_at'], 'match_final_resolution_idx');
        });

        Schema::create('player_dispute_strikes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('dispute_id')->nullable()->constrained('match_disputes')->nullOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('strike_number');
            $table->text('reason');
            $table->decimal('balance_penalty', 18, 2)->default(0);
            $table->boolean('permanent_ban_applied')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'match_id'], 'player_match_strike_unique');
            $table->index(['user_id', 'strike_number'], 'player_strike_count_idx');
        });

        Schema::table('ledger_entries', function (Blueprint $table): void {
            $table->string('idempotency_key', 191)->nullable()->unique()->after('reference_id');
        });

        Schema::table('prize_distributions', function (Blueprint $table): void {
            $table->string('idempotency_key', 191)->nullable()->unique()->after('distribution_reference_uuid');
        });

        Schema::table('refunds', function (Blueprint $table): void {
            $table->foreignId('registration_id')->nullable()->after('tournament_id')
                ->constrained('tournament_registrations')->nullOnDelete();
            $table->string('idempotency_key', 191)->nullable()->unique()->after('refund_reference_uuid');
            $table->unique(['tournament_id', 'registration_id'], 'refund_tournament_registration_unique');
        });

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'tournament.timezone'],
            ['value' => config('app.tournament_timezone', 'UTC'), 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'tournament.waiting_result_time_default'],
            ['value' => '5', 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropUnique('refund_tournament_registration_unique');
            $table->dropUnique(['idempotency_key']);
            $table->dropForeign(['registration_id']);
            $table->dropColumn(['registration_id', 'idempotency_key']);
        });
        Schema::table('prize_distributions', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
        Schema::table('ledger_entries', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
        Schema::dropIfExists('player_dispute_strikes');
        Schema::table('matches', function (Blueprint $table): void {
            $table->dropIndex('match_round_deadline_idx');
            $table->dropIndex('match_final_resolution_idx');
            $table->dropColumn([
                'active_attempt_number', 'stalled_deadline_at', 'round_deadline_at',
                'final_resolution_eligible_at', 'final_resolution_notified_at', 'resolution_reason',
            ]);
        });
        Schema::table('match_result_submissions', function (Blueprint $table): void {
            $table->dropUnique('match_attempt_registration_unique');
            $table->dropForeign(['match_attempt_id']);
            $table->dropForeign(['registration_id']);
            $table->dropColumn(['match_attempt_id', 'registration_id', 'outcome']);
        });
        DB::table('match_result_submissions')->whereNull('winner_registration_id')->delete();
        Schema::table('match_result_submissions', fn (Blueprint $table) => $table->unsignedBigInteger('winner_registration_id')->nullable(false)->change());
        Schema::dropIfExists('match_attempts');
        Schema::dropIfExists('tournament_cancellation_votes');
        Schema::dropIfExists('tournament_cancellation_requests');
        Schema::table('tournaments', function (Blueprint $table): void {
            $table->dropUnique('tournament_slot_period_unique');
            $table->dropIndex('tournament_v2_lifecycle_idx');
            $table->dropForeign(['schedule_slot_id']);
            $table->dropColumn([
                'workflow_version', 'schedule_slot_id', 'occurrence_period_key', 'join_closes_at',
                'round_duration_seconds', 'full_first_bps', 'full_second_bps', 'full_platform_bps',
                'underfilled_first_bps', 'underfilled_platform_bps', 'financial_calculation_version',
                'finalized_joined_entries', 'finalized_gross_pool', 'finalized_commission_amount',
                'finalized_first_prize', 'finalized_second_prize', 'financial_finalized_at', 'payout_status', 'completion_reason',
            ]);
        });
        Schema::dropIfExists('tournament_schedule_slots');
        Schema::table('tournament_templates', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['workflow_version', 'created_by']);
        });
        Schema::dropIfExists('game_tournament_defaults');
    }
};
