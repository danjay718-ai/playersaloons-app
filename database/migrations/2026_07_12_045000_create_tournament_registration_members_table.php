<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_registration_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('tournament_registrations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamps();
            $table->unique(['registration_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_registration_members');
    }
};
