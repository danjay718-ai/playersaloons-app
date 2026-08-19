<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_progressions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('experience_points')->default(0);
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('tournaments_completed')->default(0);
            $table->timestamps();
        });

        Schema::create('player_experience_awards', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->string('reason', 64);
            $table->unsignedInteger('amount');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'source_type', 'source_id', 'reason'], 'player_xp_awards_unique_source');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_experience_awards');
        Schema::dropIfExists('player_progressions');
    }
};
