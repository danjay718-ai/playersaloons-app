<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('head_to_head_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->unsignedInteger('rating')->default(1200);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'game_id']);
        });

        Schema::table('head_to_head_matches', function (Blueprint $table): void {
            $table->timestamp('rating_processed_at')->nullable()->index();
            $table->unsignedInteger('creator_rating_before')->nullable();
            $table->unsignedInteger('creator_rating_after')->nullable();
            $table->unsignedInteger('opponent_rating_before')->nullable();
            $table->unsignedInteger('opponent_rating_after')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('head_to_head_matches', function (Blueprint $table): void {
            $table->dropColumn(['rating_processed_at', 'creator_rating_before', 'creator_rating_after', 'opponent_rating_before', 'opponent_rating_after']);
        });
        Schema::dropIfExists('head_to_head_ratings');
    }
};
