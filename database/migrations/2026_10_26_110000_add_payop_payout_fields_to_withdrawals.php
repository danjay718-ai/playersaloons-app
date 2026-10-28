<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('payout_provider')->default('manual');
            $table->char('payout_currency', 3)->nullable();
            $table->text('payout_recipient')->nullable();
            $table->string('payout_reference')->nullable()->unique();
            $table->string('payout_status')->nullable();
            $table->timestamp('payout_submitted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropUnique(['payout_reference']);
            $table->dropColumn(['payout_provider', 'payout_currency', 'payout_recipient', 'payout_reference', 'payout_status', 'payout_submitted_at']);
        });
    }
};
