<?php

declare(strict_types=1);

namespace App\Modules\CMS\Actions;

use App\Modules\CMS\Models\GameCategory;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class DeleteGameCategoryAction
{
    /**
     * Delete a game category only when zero games (including archived games) reference it.
     *
     * @throws AuthorizationException
     * @throws LogicException
     */
    public function execute(User $actor, GameCategory $category): void
    {
        if (! $actor->can('games.manage') && ! $actor->can('cms.manage')) {
            throw new AuthorizationException('You do not have permission to delete game categories.');
        }

        DB::transaction(function () use ($actor, $category): void {
            $locked = GameCategory::query()->lockForUpdate()->findOrFail($category->id);
            $referencedCount = $locked->allGames()->count();

            if ($referencedCount > 0) {
                throw new LogicException(
                    __('This category cannot be deleted because :count game(s) (including archived games) still reference it. Reassign those games or deactivate the category instead.', [
                        'count' => $referencedCount,
                    ])
                );
            }

            $snapshot = [
                'category_id' => $locked->id,
                'name' => $locked->name,
                'slug' => $locked->slug,
            ];

            $locked->delete();

            activity()
                ->causedBy($actor)
                ->withProperties($snapshot)
                ->log('admin_game_category_deleted');
        });
    }
}
