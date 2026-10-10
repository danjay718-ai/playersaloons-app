<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_language_resets', function (Blueprint $table): void {
            $table->string('locale', 10)->primary();
            $table->timestamp('reset_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_language_resets');
    }
};
