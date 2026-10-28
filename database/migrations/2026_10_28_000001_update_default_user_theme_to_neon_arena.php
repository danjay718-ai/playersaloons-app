<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'theme')) {
            return;
        }

        DB::table('users')
            ->whereIn('theme', ['purple_dark', 'blue_dark'])
            ->orWhereNull('theme')
            ->update(['theme' => 'neon_arena']);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('theme', 24)->default('neon_arena')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'theme')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('theme', 24)->default('neon_arena')->change();
        });
    }
};
