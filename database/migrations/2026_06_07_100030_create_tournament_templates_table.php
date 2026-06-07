<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('name');
            $table->string('format')->default('single_elimination');
            $table->unsignedInteger('max_participants');
            $table->unsignedInteger('min_participants');
            $table->decimal('entry_fee', 10, 2)->default(0.00);
            $table->string('prize_model')->default('proportional');
            $table->unsignedInteger('checkin_minutes')->default(15);
            $table->boolean('is_recurring')->default(false);
            $table->json('settings_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_templates');
    }
};
