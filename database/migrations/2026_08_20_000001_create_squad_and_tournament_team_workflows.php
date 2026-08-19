<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_join_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('pending');
            $table->string('message', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'user_id', 'status'], 'team_join_request_user_status_idx');
            $table->index(['team_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('tournament_teams', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('source_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('leader_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('status', 24)->default('forming');
            $table->timestamps();

            $table->index(['tournament_id', 'status']);
            $table->unique(['tournament_id', 'leader_user_id'], 'tournament_team_unique_leader');
        });

        Schema::create('tournament_team_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('tournament_team_id')->constrained('tournament_teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 24)->default('member');
            $table->string('game_id_value', 191)->nullable();
            $table->string('ready_mode', 24)->default('auto');
            $table->timestamps();

            $table->unique(['tournament_id', 'user_id'], 'tournament_team_unique_player');
            $table->unique(['tournament_team_id', 'user_id'], 'tournament_team_unique_member');
        });

        Schema::create('tournament_team_search_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms')->restrictOnDelete();
            $table->string('game_id_value', 191);
            $table->string('ready_mode', 24)->default('auto');
            $table->string('status', 24)->default('searching');
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'user_id'], 'tournament_team_search_unique_player');
            $table->index(['tournament_id', 'platform_id', 'status'], 'tournament_team_search_pool_idx');
        });

        Schema::table('tournament_registrations', function (Blueprint $table): void {
            $table->foreignId('tournament_team_id')->nullable()->after('team_id')->constrained('tournament_teams')->nullOnDelete();
            $table->index(['tournament_id', 'tournament_team_id']);
        });

        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->foreignId('tournament_team_id')->nullable()->after('team_id')->constrained('tournament_teams')->cascadeOnDelete();
            $table->index('tournament_team_id');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropForeign(['tournament_team_id']);
            $table->dropIndex(['tournament_team_id']);
            $table->dropColumn('tournament_team_id');
        });
        Schema::table('tournament_registrations', function (Blueprint $table): void {
            $table->dropForeign(['tournament_team_id']);
            $table->dropIndex(['tournament_id', 'tournament_team_id']);
            $table->dropColumn('tournament_team_id');
        });
        Schema::dropIfExists('tournament_team_search_entries');
        Schema::dropIfExists('tournament_team_members');
        Schema::dropIfExists('tournament_teams');
        Schema::dropIfExists('team_join_requests');
    }
};
