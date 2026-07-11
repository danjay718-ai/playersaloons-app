<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stream_channels', function (Blueprint $table): void {
            $table->string('provider_status', 30)->nullable()->index();
            $table->timestamp('provider_checked_at')->nullable();
            $table->text('provider_status_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stream_channels', function (Blueprint $table): void {
            $table->dropColumn(['provider_status', 'provider_checked_at', 'provider_status_error']);
        });
    }
};
