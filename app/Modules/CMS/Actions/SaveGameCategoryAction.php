<?php

declare(strict_types=1);

namespace App\Modules\CMS\Actions;

use App\Modules\CMS\Models\GameCategory;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SaveGameCategoryAction
{
    /**
     * Create or update a game category.
     *
     * @param  array{name: string, slug?: string|null, display_order?: int, is_active?: bool}  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, array $data, ?GameCategory $category = null): GameCategory
    {
        if (! $actor->can('games.manage') && ! $actor->can('cms.manage')) {
            throw new AuthorizationException('You do not have permission to manage game categories.');
        }

        $name = trim($data['name']);
        $slug = Str::slug(trim((string) ($data['slug'] ?? '')) !== '' ? (string) $data['slug'] : $name);

        if ($name === '') {
            throw ValidationException::withMessages(['categoryName' => __('Enter a category name.')]);
        }

        if ($slug === '') {
            throw ValidationException::withMessages(['categorySlug' => __('Enter a valid category name or slug containing letters or numbers.')]);
        }

        $duplicate = GameCategory::query()
            ->where('slug', $slug)
            ->when($category !== null, fn ($query) => $query->whereKeyNot($category->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['categorySlug' => __('This category slug is already in use. Choose a different name or slug.')]);
        }

        return DB::transaction(function () use ($actor, $category, $data, $name, $slug): GameCategory {
            $displayOrder = isset($data['display_order'])
                ? max(0, (int) $data['display_order'])
                : ($category !== null
                    ? $category->display_order
                    : ((int) GameCategory::query()->max('display_order') + 1));

            $isActive = array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : ($category !== null ? (bool) $category->is_active : true);

            if ($category !== null) {
                $locked = GameCategory::query()->lockForUpdate()->findOrFail($category->id);
                $locked->update([
                    'name' => $name,
                    'slug' => $slug,
                    'display_order' => $displayOrder,
                    'is_active' => $isActive,
                ]);

                activity()
                    ->causedBy($actor)
                    ->performedOn($locked)
                    ->withProperties(['name' => $name, 'slug' => $slug, 'display_order' => $displayOrder, 'is_active' => $isActive])
                    ->log('admin_game_category_updated');

                return $locked->fresh() ?? $locked;
            }

            $created = GameCategory::query()->create([
                'uuid' => Str::uuid()->toString(),
                'name' => $name,
                'slug' => $slug,
                'display_order' => $displayOrder,
                'is_active' => $isActive,
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($created)
                ->withProperties(['name' => $name, 'slug' => $slug, 'display_order' => $displayOrder, 'is_active' => $isActive])
                ->log('admin_game_category_created');

            return $created;
        });
    }
}
