<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $updates = DB::table('public_navigation_items')->where('label', 'Updates')->first();

        $attributes = [
            'url' => '/updates',
            'icon' => 'newspaper',
            'match_pattern' => 'updates*|blog*|news*',
            'visibility' => 'public',
            'sort_order' => 25,
            'is_active' => true,
            'opens_new_tab' => false,
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        if ($updates) {
            DB::table('public_navigation_items')->where('id', $updates->id)->update($attributes);
        } else {
            DB::table('public_navigation_items')->insert(array_merge($attributes, [
                'uuid' => (string) Str::uuid(),
                'label' => 'Updates',
                'created_at' => $now,
            ]));
        }

        DB::table('public_navigation_items')
            ->whereIn('label', ['Blog', 'News'])
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('public_navigation_items')->where('label', 'Updates')->delete();

        DB::table('public_navigation_items')
            ->whereIn('label', ['Blog', 'News'])
            ->update(['deleted_at' => null, 'updated_at' => now()]);
    }
};
