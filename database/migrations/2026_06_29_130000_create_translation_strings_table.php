<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_strings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 500);
            $table->string('locale', 10);
            $table->longText('text')->nullable();
            $table->timestamps();

            $table->unique(['key', 'locale'], 'translation_strings_key_locale_unique');
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_strings');
    }
};
