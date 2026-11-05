<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key' => 'footer.company_details',
            'value' => "Skillplay Ltd\nCompany number: 15920047\nIncorporated on: 27 August 2024\nRegistered office:\n167–169 Great Portland Street, 5th Floor\nLondon, W1W 5PF, England",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'footer.company_details')->delete();
    }
};
