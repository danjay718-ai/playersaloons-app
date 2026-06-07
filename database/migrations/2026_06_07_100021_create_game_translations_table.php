<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->char('locale', 5);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_translations');
    }
};
