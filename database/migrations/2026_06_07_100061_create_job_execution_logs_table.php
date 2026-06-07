<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name');
            $table->string('status');
            $table->json('payload_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('executed_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_execution_logs');
    }
};
