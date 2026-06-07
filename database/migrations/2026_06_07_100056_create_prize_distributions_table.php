<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prize_distributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->unsignedInteger('rank');
            $table->uuid('distribution_reference_uuid')->unique();
            $table->string('status')->default('pending');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prize_distributions');
    }
};
