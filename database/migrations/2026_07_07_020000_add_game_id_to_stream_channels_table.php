<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('stream_channels', 'game_id')) {
            return;
        }

        Schema::table('stream_channels', function (Blueprint $table) {
            $table->foreignId('game_id')
                ->nullable()
                ->after('tournament_id')
                ->constrained('games')
                ->cascadeOnDelete();

            $table->index(['game_id', 'provider']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('stream_channels', 'game_id')) {
            return;
        }

        Schema::table('stream_channels', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'provider']);
            $table->dropConstrainedForeignId('game_id');
        });
    }
};
