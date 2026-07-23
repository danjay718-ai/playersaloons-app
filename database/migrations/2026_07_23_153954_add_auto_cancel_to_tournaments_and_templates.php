<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_templates', function (Blueprint $table) {
            $table->boolean('is_auto_cancel_underfilled')->default(false)->after('max_participants');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->boolean('is_auto_cancel_underfilled')->default(false)->after('max_participants');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('is_auto_cancel_underfilled');
        });

        Schema::table('tournament_templates', function (Blueprint $table) {
            $table->dropColumn('is_auto_cancel_underfilled');
        });
    }
};
