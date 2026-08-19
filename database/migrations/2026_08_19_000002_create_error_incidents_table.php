<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_id', 32)->unique();
            $table->char('fingerprint', 64)->index();
            $table->string('level', 20)->index();
            $table->string('source', 30)->index();
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->string('exception_class');
            $table->text('message')->nullable();
            $table->string('route')->nullable()->index();
            $table->string('method', 10)->nullable();
            $table->string('path', 1000)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('ip_hash', 64)->nullable();
            $table->json('context')->nullable();
            $table->longText('stack_trace')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_incidents');
    }
};
