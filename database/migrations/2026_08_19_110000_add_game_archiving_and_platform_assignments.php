<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::create('game_platform', function (Blueprint $table): void {
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['game_id', 'platform_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_platform');

        Schema::table('games', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
