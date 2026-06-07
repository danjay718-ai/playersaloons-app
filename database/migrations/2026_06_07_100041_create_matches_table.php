<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->unsignedBigInteger('player_a_registration_id')->nullable();
            $table->unsignedBigInteger('player_b_registration_id')->nullable();
            $table->unsignedBigInteger('winner_registration_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('player_a_registration_id')->references('id')->on('tournament_registrations')->nullOnDelete();
            $table->foreign('player_b_registration_id')->references('id')->on('tournament_registrations')->nullOnDelete();
            $table->foreign('winner_registration_id')->references('id')->on('tournament_registrations')->nullOnDelete();

            $table->index('status');
            $table->index('tournament_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
