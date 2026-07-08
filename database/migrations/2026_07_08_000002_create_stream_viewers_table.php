<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stream_channel_id')->constrained('stream_channels')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_token', 64)->nullable(); // For anonymous viewers
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['stream_channel_id', 'user_id']);
            $table->index(['stream_channel_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_viewers');
    }
};
