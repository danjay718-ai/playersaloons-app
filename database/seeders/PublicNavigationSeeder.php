<?php

namespace Database\Seeders;

use App\Modules\CMS\Models\PublicNavigationItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicNavigationSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('system_settings')->where('key', 'cms.public_navigation_seeded')->exists()) {
            return;
        }

        foreach ($this->items() as $item) {
            $navItem = PublicNavigationItem::query()
                ->withTrashed()
                ->firstOrNew(['label' => $item['label']]);

            if (! $navItem->exists) {
                $navItem->uuid = Str::uuid()->toString();
            }

            if ($navItem->exists) {
                continue;
            }

            $navItem->fill($item);
            $navItem->save();
        }

        DB::table('system_settings')->insertOrIgnore([
            'key' => 'cms.public_navigation_seeded', 'value' => '1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(): array
    {
        return [
            [
                'label' => 'Home',
                'url' => '/',
                'icon' => 'house',
                'match_pattern' => '/',
                'visibility' => 'public',
                'sort_order' => 0,
                'is_active' => true,
                'opens_new_tab' => false,
            ],
            [
                'label' => 'Tournaments',
                'url' => '/tournaments',
                'icon' => 'trophy',
                'match_pattern' => 'tournaments*',
                'visibility' => 'public',
                'sort_order' => 10,
                'is_active' => true,
                'opens_new_tab' => false,
            ],
            [
                'label' => 'Head-to-Head',
                'url' => '/h2h?view=guest',
                'icon' => 'swords',
                'match_pattern' => 'h2h*',
                'visibility' => 'public',
                'sort_order' => 15,
                'is_active' => true,
                'opens_new_tab' => false,
            ],
            [
                'label' => 'Teams',
                'url' => '/teams',
                'icon' => 'users',
                'match_pattern' => 'teams*',
                'visibility' => 'player',
                'sort_order' => 20,
                'is_active' => true,
                'opens_new_tab' => false,
            ],
            [
                'label' => 'Updates',
                'url' => '/updates',
                'icon' => 'newspaper',
                'match_pattern' => 'updates*|blog*|news*',
                'visibility' => 'public',
                'sort_order' => 25,
                'is_active' => true,
                'opens_new_tab' => false,
            ],
            [
                'label' => 'Dashboard',
                'url' => '/dashboard',
                'icon' => 'layout-dashboard',
                'match_pattern' => 'dashboard*',
                'visibility' => 'auth',
                'sort_order' => 30,
                'is_active' => true,
                'opens_new_tab' => false,
            ],
        ];
    }
}
