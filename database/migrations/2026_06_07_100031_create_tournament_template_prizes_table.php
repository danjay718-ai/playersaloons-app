<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_template_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('tournament_templates')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_template_prizes');
    }
};
