<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_checkouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->string('provider');
            $table->string('provider_reference')->nullable()->unique();
            $table->char('currency', 3);
            $table->decimal('amount', 18, 2);
            $table->decimal('fee_amount', 18, 2);
            $table->decimal('total', 18, 2);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_checkouts');
    }
};
