<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_languages', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('english', 80);
            $table->string('native', 80);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_languages');
    }
};
