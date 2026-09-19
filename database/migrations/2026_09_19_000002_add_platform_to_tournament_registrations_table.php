<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table): void {
            $table->foreignId('platform_id')->nullable()->after('team_id')
                ->constrained('platforms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table): void {
            $table->dropForeign(['platform_id']);
            $table->dropColumn('platform_id');
        });
    }
};
