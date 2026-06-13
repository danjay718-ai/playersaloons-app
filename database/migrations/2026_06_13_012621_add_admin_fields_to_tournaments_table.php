<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->text('rules')->nullable();
            $table->string('platform')->nullable(); // console, mobile, pc, etc.
            $table->integer('waiting_time')->nullable(); // in minutes
            $table->integer('waiting_result_time')->nullable(); // in minutes
            $table->integer('team_size')->default(1);
            $table->decimal('prize_1st', 10, 2)->nullable();
            $table->decimal('prize_2nd', 10, 2)->nullable();
            $table->decimal('prize_3rd', 10, 2)->nullable();
            $table->integer('winning_points')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'rules',
                'platform',
                'waiting_time',
                'waiting_result_time',
                'team_size',
                'prize_1st',
                'prize_2nd',
                'prize_3rd',
                'winning_points',
            ]);
        });
    }
};
