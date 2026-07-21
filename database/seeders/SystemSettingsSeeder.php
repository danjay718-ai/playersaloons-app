<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'platform.rake_percentage',
                'value' => '10',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'match.rematch_window_minutes',
                'value' => '30',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'tournament.waiting_result_time_default',
                'value' => '30',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'wallet.withdrawal_limit_min',
                'value' => '10.00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'wallet.withdrawal_limit_max_daily',
                'value' => '1000.00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'system.maintenance_mode',
                'value' => 'false',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'feature.kyc_required',
                'value' => 'true',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral.enabled',
                'value' => 'true',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral.referrer_reward',
                'value' => '5.00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral.referred_reward',
                'value' => '2.00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['key' => 'deposit_fee.enabled', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'deposit_fee.fixed', 'value' => '0.00', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'deposit_fee.percentage', 'value' => '0.00', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'language_switcher.show_guest', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'language_switcher.show_admin', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
