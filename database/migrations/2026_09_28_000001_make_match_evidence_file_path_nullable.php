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
        Schema::table('match_evidence', function (Blueprint $table): void {
            $table->string('file_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('match_evidence')->whereNull('file_path')->update(['file_path' => '']);

        Schema::table('match_evidence', function (Blueprint $table): void {
            $table->string('file_path')->nullable(false)->change();
        });
    }
};
