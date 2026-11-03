<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve existing links, including ones an admin disabled or deleted.
        if (DB::table('public_navigation_items')->where('label', 'Head-to-Head')
            ->orWhere('url', '/h2h')->orWhere('url', 'like', '/h2h?%')->exists()) {
            return;
        }

        DB::table('public_navigation_items')->insert([
            'uuid' => '3b2f17b8-0472-4d2e-8bc4-b87a342083c7',
            'label' => 'Head-to-Head',
            'url' => '/h2h?view=guest',
            'icon' => 'swords',
            'match_pattern' => 'h2h*',
            'visibility' => 'public',
            'sort_order' => 15,
            'is_active' => true,
            'opens_new_tab' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('public_navigation_items')->where('uuid', '3b2f17b8-0472-4d2e-8bc4-b87a342083c7')->delete();
    }
};
