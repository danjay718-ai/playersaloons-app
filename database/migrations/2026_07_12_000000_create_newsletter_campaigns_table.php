<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('subject');
            $table->text('content');
            $table->string('status')->default('draft')->index();
            $table->unsignedBigInteger('recipient_count')->default(0);
            $table->unsignedBigInteger('sent_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
    }
};
