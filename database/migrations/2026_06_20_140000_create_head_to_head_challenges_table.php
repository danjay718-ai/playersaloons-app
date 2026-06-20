<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('head_to_head_challenges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('platform_id')->nullable()->constrained('platforms')->nullOnDelete();
            $table->decimal('stake_amount', 18, 2);
            $table->string('status')->default('waiting');
            $table->string('creator_game_handle');
            $table->string('region')->nullable();
            $table->unsignedSmallInteger('match_timer_minutes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'game_id', 'stake_amount']);
            $table->index('creator_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('head_to_head_challenges');
    }
};
