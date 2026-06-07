<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_page_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->char('locale', 5);
            $table->string('title');
            $table->longText('content');
            $table->timestamps();

            $table->unique(['page_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_page_translations');
    }
};
