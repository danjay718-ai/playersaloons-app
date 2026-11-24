<?php

declare(strict_types=1);

namespace App\Modules\CMS\Actions;

use App\Modules\CMS\Models\GameCategory;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ToggleGameCategoryStatusAction
{
    /**
     * Activate or deactivate a game category.
     *
     * @throws AuthorizationException
     */
    public function execute(User $actor, GameCategory $category, ?bool $active = null): GameCategory
    {
        if (! $actor->can('games.manage') && ! $actor->can('cms.manage')) {
            throw new AuthorizationException('You do not have permission to manage game categories.');
        }

        return DB::transaction(function () use ($actor, $category, $active): GameCategory {
            $locked = GameCategory::query()->lockForUpdate()->findOrFail($category->id);
            $nextState = $active ?? ! $locked->is_active;

            if ($locked->is_active !== $nextState) {
                $locked->update(['is_active' => $nextState]);

                activity()
                    ->causedBy($actor)
                    ->performedOn($locked)
                    ->withProperties(['is_active' => $nextState])
                    ->log($nextState ? 'admin_game_category_activated' : 'admin_game_category_deactivated');
            }

            return $locked->fresh() ?? $locked;
        });
    }
}
