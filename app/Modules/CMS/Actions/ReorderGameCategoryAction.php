<?php

declare(strict_types=1);

namespace App\Modules\CMS\Actions;

use App\Modules\CMS\Models\GameCategory;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ReorderGameCategoryAction
{
    /**
     * Move a category up or down in display order.
     *
     * @throws AuthorizationException
     */
    public function execute(User $actor, GameCategory $category, string $direction): void
    {
        if (! $actor->can('games.manage') && ! $actor->can('cms.manage')) {
            throw new AuthorizationException('You do not have permission to reorder game categories.');
        }

        if (! in_array($direction, ['up', 'down'], true)) {
            return;
        }

        DB::transaction(function () use ($actor, $category, $direction): void {
            $ordered = GameCategory::query()->ordered()->lockForUpdate()->get()->values();
            $index = $ordered->search(fn (GameCategory $item): bool => $item->id === $category->id);

            if ($index === false) {
                return;
            }

            $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
            if ($swapIndex < 0 || $swapIndex >= $ordered->count()) {
                return;
            }

            $ids = $ordered->pluck('id')->all();
            [$ids[$index], $ids[$swapIndex]] = [$ids[$swapIndex], $ids[$index]];

            foreach ($ids as $position => $id) {
                GameCategory::query()->whereKey($id)->update(['display_order' => $position + 1]);
            }

            activity()
                ->causedBy($actor)
                ->performedOn($category)
                ->withProperties(['direction' => $direction, 'order' => $ids])
                ->log('admin_game_category_reordered');
        });
    }
}
