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
        Schema::table('payment_checkouts', function (Blueprint $table): void {
            $table->string('status', 24)->default('pending')->after('total');
            $table->timestamp('failed_at')->nullable()->after('completed_at');
            $table->index(['status', 'provider', 'created_at'], 'payment_checkouts_status_provider_created_idx');
        });

        DB::table('payment_checkouts')
            ->whereNotNull('completed_at')
            ->update(['status' => 'completed']);
    }

    public function down(): void
    {
        Schema::table('payment_checkouts', function (Blueprint $table): void {
            $table->dropIndex('payment_checkouts_status_provider_created_idx');
            $table->dropColumn(['status', 'failed_at']);
        });
    }
};
