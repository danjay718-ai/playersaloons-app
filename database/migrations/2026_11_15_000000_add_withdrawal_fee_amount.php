<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', fn (Blueprint $table) => $table->decimal('fee_amount', 18, 2)->default(0));
    }

    public function down(): void
    {
        if (DB::table('withdrawals')->where('fee_amount', '>', 0)->exists()) {
            throw new LogicException('Preserve withdrawal fee snapshots before rolling back this migration.');
        }
        Schema::table('withdrawals', fn (Blueprint $table) => $table->dropColumn('fee_amount'));
    }
};
