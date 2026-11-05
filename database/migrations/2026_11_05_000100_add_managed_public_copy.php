<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // A fresh database contains only the H2H link from its data migration.
        // Existing menus belong to admins, including renamed or deleted links.
        if (DB::table('public_navigation_items')->where('uuid', '!=', '3b2f17b8-0472-4d2e-8bc4-b87a342083c7')->exists()) {
            DB::table('system_settings')->insertOrIgnore([
                'key' => 'cms.public_navigation_seeded', 'value' => '1',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (! DB::table('public_navigation_items')->where('url', '/')->exists()) {
            DB::table('public_navigation_items')->insert([
                'uuid' => 'a06d7c26-3061-414e-9e66-56492ed2f2a7',
                'label' => 'Home', 'url' => '/', 'icon' => 'house', 'match_pattern' => '/',
                'visibility' => 'public', 'sort_order' => 0, 'is_active' => true, 'opens_new_tab' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $footerId = DB::table('landing_sections')->where('key', 'footer')->whereNull('deleted_at')->value('id');
        if ($footerId === null) {
            return;
        }

        foreach ([
            'competition-tournaments' => ['Tournaments', '/tournaments'],
            'competition-head-to-head' => ['Head-to-Head', '/h2h?view=guest'],
            'competition-games' => ['Supported Games', '/#games'],
            'competition-how' => ['How it Works', '/#how'],
            'about-us' => ['About Us', '/about'],
            'contact-support' => ['Contact Support', '/contact'],
        ] as $key => [$title, $url]) {
            if (DB::table('landing_section_items')->where('landing_section_id', $footerId)->where('item_key', $key)->exists()) {
                continue;
            }
            DB::table('landing_section_items')->insert([
                'uuid' => Str::uuid()->toString(), 'landing_section_id' => $footerId,
                'item_key' => $key, 'title' => $title, 'url' => $url, 'sort_order' => 10,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // CMS content may have been edited since deployment; keep it on rollback.
    }
};
