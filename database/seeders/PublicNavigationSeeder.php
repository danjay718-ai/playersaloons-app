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
            PublicNavigationItem::query()->updateOrCreate(
                ['label' => $item['label']],
                array_merge(['uuid' => Str::uuid()->toString()], $item)
            );
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
            ],
            [
                'label' => 'Teams',
                'url' => '/teams',
                'icon' => 'users',
                'match_pattern' => 'teams*',
                'visibility' => 'guest_or_player',
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'label' => 'Dashboard',
                'url' => '/dashboard',
                'icon' => 'layout-dashboard',
                'match_pattern' => 'dashboard*',
                'visibility' => 'auth',
                'sort_order' => 30,
                'is_active' => true,
            ],
        ];
    }
}
