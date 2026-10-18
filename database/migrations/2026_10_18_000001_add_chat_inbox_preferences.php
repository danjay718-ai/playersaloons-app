<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_participants', function (Blueprint $table): void {
            $table->boolean('notifications_muted')->default(false);
            // A numeric checkpoint remains valid when old global messages are pruned.
            $table->unsignedBigInteger('last_read_message_id')->default(0);
        });
        DB::table('chat_participants')->whereNotNull('last_read_at')->orderBy('id')->chunkById(250, function ($participants): void {
            foreach ($participants as $participant) {
                $checkpoint = DB::table('chat_messages')->where('chat_conversation_id', $participant->chat_conversation_id)
                    ->where('created_at', '<=', $participant->last_read_at)->max('id');
                DB::table('chat_participants')->where('id', $participant->id)->update(['last_read_message_id' => $checkpoint ?? 0]);
            }
        });
        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignId('chat_conversation_id')->nullable()->constrained('chat_conversations')->cascadeOnDelete();
            $table->unsignedBigInteger('chat_message_id')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->unique(['user_id', 'chat_conversation_id'], 'notifications_chat_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropUnique('notifications_chat_recipient_unique');
            $table->dropForeign(['chat_conversation_id']);
            $table->dropColumn(['chat_conversation_id', 'chat_message_id', 'dismissed_at']);
        });
        Schema::table('chat_participants', fn (Blueprint $table) => $table->dropColumn(['notifications_muted', 'last_read_message_id']));
    }
};
