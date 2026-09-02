<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_head_to_head_defaults', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->unique()->constrained('games')->cascadeOnDelete();
            $table->foreignId('default_platform_id')->nullable()->constrained('platforms')->nullOnDelete();
            $table->string('head_to_head_banner_path')->nullable();
            $table->text('description')->nullable();
            $table->text('rules')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_head_to_head_defaults');
    }
};
