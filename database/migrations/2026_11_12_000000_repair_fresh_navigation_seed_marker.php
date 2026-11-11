<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            ['label' => 'Home', 'url' => '/', 'icon' => 'house', 'match_pattern' => '/', 'visibility' => 'public', 'sort_order' => 0],
            ['label' => 'Head-to-Head', 'url' => '/h2h?view=guest', 'icon' => 'swords', 'match_pattern' => 'h2h*', 'visibility' => 'public', 'sort_order' => 15],
            ['label' => 'Updates', 'url' => '/updates', 'icon' => 'newspaper', 'match_pattern' => 'updates*|blog*|news*', 'visibility' => 'public', 'sort_order' => 25],
        ];

        if (DB::table('users')->exists() || DB::table('public_navigation_items')->count() !== count($defaults)) {
            return;
        }

        foreach ($defaults as $attributes) {
            if (! DB::table('public_navigation_items')->where($attributes)
                ->where('is_active', true)->where('opens_new_tab', false)->whereNull('deleted_at')->exists()) {
                return;
            }
        }

        // Earlier data migrations incorrectly mark a fresh menu as already managed.
        DB::table('system_settings')->where('key', 'cms.public_navigation_seeded')->delete();
    }

    public function down(): void
    {
        // Preserve the marker once the seeder or an administrator takes ownership.
    }
};
