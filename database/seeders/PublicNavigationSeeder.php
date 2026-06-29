<?php

namespace Database\Seeders;

use App\Modules\CMS\Models\PublicNavigationItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PublicNavigationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $item) {
            $navItem = PublicNavigationItem::query()
                ->withTrashed()
                ->firstOrNew(['label' => $item['label']]);

            if (! $navItem->exists) {
                $navItem->uuid = Str::uuid()->toString();
            }

            if ($navItem->exists && $navItem->trashed()) {
                $navItem->restore();
            }

            $navItem->fill($item);
            $navItem->save();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(): array
    {
        return [
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
                'label' => 'Teams',
                'url' => '/teams',
                'icon' => 'users',
                'match_pattern' => 'teams*',
                'visibility' => 'guest_or_player',
                'sort_order' => 20,
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
