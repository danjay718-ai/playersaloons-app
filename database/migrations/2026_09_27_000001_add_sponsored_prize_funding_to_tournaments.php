<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table): void {
            $table->string('prize_funding_mode', 24)->default('entry_fees')->after('prize_3rd');
            $table->string('funding_state', 24)->default('none')->after('prize_funding_mode');
            $table->decimal('reserved_prize_amount', 18, 2)->default(0)->after('funding_state');
            $table->index(['prize_funding_mode', 'funding_state'], 'tournament_prize_funding_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table): void {
            $table->dropIndex('tournament_prize_funding_idx');
            $table->dropColumn(['prize_funding_mode', 'funding_state', 'reserved_prize_amount']);
        });
    }
};
