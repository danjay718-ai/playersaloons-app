<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->nullable()->constrained('games')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('source_url');
            $table->string('title')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamp('taken_down_at')->nullable();
            $table->foreignId('taken_down_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('takedown_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'provider']);
            $table->index(['tournament_id', 'provider']);
            $table->index(['game_id', 'provider']);
            $table->index(['provider', 'is_public', 'taken_down_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_channels');
    }
};
