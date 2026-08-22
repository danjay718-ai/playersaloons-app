<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->index('captain_user_id');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->index('player_a_registration_id');
            $table->index('player_b_registration_id');
            $table->index('winner_registration_id');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['captain_user_id']);
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex(['player_a_registration_id']);
            $table->dropIndex(['player_b_registration_id']);
            $table->dropIndex(['winner_registration_id']);
        });
    }
};
