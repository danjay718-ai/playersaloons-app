<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table): void {
            $table->index(['status', 'provider', 'created_at'], 'deposits_status_provider_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table): void {
            $table->dropIndex('deposits_status_provider_created_idx');
        });
    }
};
