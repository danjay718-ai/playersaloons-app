<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_result_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('winner_registration_id')->constrained('tournament_registrations')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_result_submissions');
    }
};
