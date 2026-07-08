<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stream_channel_id')->constrained('stream_channels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('color', 7)->default('#a78bfa'); // User's chat color
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['stream_channel_id', 'created_at']);
            $table->index(['user_id', 'stream_channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_chat_messages');
    }
};
