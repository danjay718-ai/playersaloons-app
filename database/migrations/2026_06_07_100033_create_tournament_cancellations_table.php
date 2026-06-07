<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->unsignedInteger('affected_participant_count')->default(0);
            $table->boolean('refund_required')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_cancellations');
    }
};
