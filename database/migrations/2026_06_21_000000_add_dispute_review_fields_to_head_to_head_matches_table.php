<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('head_to_head_matches', function (Blueprint $table) {
            $table->string('result_proof_path')->nullable()->after('result_notes');
            $table->foreignId('disputed_by')->nullable()->after('confirmation_due_at')->constrained('users')->nullOnDelete();
            $table->text('dispute_notes')->nullable()->after('disputed_by');
            $table->string('dispute_proof_path')->nullable()->after('dispute_notes');
            $table->string('dispute_resolution')->nullable()->after('dispute_proof_path');
            $table->foreignId('dispute_resolved_by')->nullable()->after('dispute_resolution')->constrained('users')->nullOnDelete();
            $table->timestamp('dispute_resolved_at')->nullable()->after('dispute_resolved_by');
        });
    }

    public function down(): void
    {
        Schema::table('head_to_head_matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dispute_resolved_by');
            $table->dropConstrainedForeignId('disputed_by');
            $table->dropColumn([
                'result_proof_path',
                'dispute_notes',
                'dispute_proof_path',
                'dispute_resolution',
                'dispute_resolved_at',
            ]);
        });
    }
};
