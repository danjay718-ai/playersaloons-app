<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('head_to_head_matches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('challenge_id')->unique()->constrained('head_to_head_challenges')->cascadeOnDelete();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opponent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('platform_id')->nullable()->constrained('platforms')->nullOnDelete();
            $table->decimal('stake_amount', 18, 2);
            $table->string('status')->default('in_progress');
            $table->string('creator_game_handle');
            $table->string('opponent_game_handle');
            $table->string('region')->nullable();
            $table->unsignedSmallInteger('match_timer_minutes')->nullable();
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('result_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('result_notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('result_submitted_at')->nullable();
            $table->timestamp('confirmation_due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'game_id']);
            $table->index(['creator_user_id', 'opponent_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('head_to_head_matches');
    }
};
