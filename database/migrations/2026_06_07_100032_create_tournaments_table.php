<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->foreignId('game_id')->constrained('games')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('DRAFT');
            $table->decimal('entry_fee', 10, 2)->default(0.00);
            $table->decimal('prize_pool', 10, 2)->nullable();
            $table->unsignedInteger('max_participants');
            $table->unsignedInteger('min_participants');
            $table->timestamp('registration_open_at')->nullable();
            $table->timestamp('registration_close_at')->nullable();
            $table->timestamp('checkin_open_at')->nullable();
            $table->timestamp('checkin_close_at')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('template_id')->references('id')->on('tournament_templates')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index('status');
            $table->index('registration_open_at');
            $table->index('start_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
