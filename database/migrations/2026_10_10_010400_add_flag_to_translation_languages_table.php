<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('translation_languages', function (Blueprint $table): void {
            $table->string('flag', 2)->default('un');
        });
    }

    public function down(): void
    {
        Schema::table('translation_languages', fn (Blueprint $table) => $table->dropColumn('flag'));
    }
};
