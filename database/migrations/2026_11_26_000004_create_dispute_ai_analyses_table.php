<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispute_ai_analyses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('match_dispute_id')->constrained('match_disputes')->cascadeOnDelete();
            $table->unsignedSmallInteger('analysis_version');
            $table->unsignedTinyInteger('evidence_count')->default(0);
            $table->string('provider', 40)->default('gemini');
            $table->string('model', 80);
            $table->string('status', 30)->default('pending');
            $table->string('suggested_resolution', 30)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->text('summary')->nullable();
            $table->json('evidence_findings_json')->nullable();
            $table->json('red_flags_json')->nullable();
            $table->string('prompt_hash', 64)->nullable();
            $table->json('raw_response_json')->nullable();
            $table->boolean('auto_resolve_eligible')->default(false);
            $table->string('auto_resolve_blocked_reason', 191)->nullable();
            $table->timestamp('auto_resolved_at')->nullable();
            $table->boolean('admin_agreed')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['match_dispute_id', 'analysis_version'], 'dispute_ai_analyses_dispute_version_unique');
            $table->index(['match_dispute_id', 'status'], 'dispute_ai_analyses_dispute_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_ai_analyses');
    }
};
