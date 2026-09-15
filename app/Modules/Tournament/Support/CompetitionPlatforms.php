<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Support;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CompetitionPlatforms
{
    /** @return array<int, int> */
    public static function ids(array $data): array
    {
        return array_values(array_unique(array_map('intval', $data['platform_ids'] ?? (isset($data['platform_id']) ? [$data['platform_id']] : []))));
    }

    public static function hasDeleted(array $data): bool
    {
        $ids = self::ids($data);

        return $ids !== [] && Platform::onlyTrashed()->whereIn('id', $ids)->exists();
    }

    /** @return array<string, mixed> */
    public static function rules(int $gameId): array
    {
        return [
            'platform_ids' => ['required', 'array', 'min:1'],
            'platform_ids.*' => ['required', 'integer', 'distinct', Rule::exists('platforms', 'id')->where('is_active', true)->whereNull('deleted_at'), Rule::exists('game_platform', 'platform_id')->where('game_id', $gameId)],
        ];
    }

    /** @return array<int, int> */
    public static function validate(Game $game, array $data): array
    {
        $ids = self::ids($data);
        $allowed = $game->platforms()->where('platforms.is_active', true)->pluck('platforms.id')->all();
        if ($ids === [] || array_diff($ids, $allowed) !== []) {
            throw ValidationException::withMessages(['platform_ids' => __('Select platforms supported by the selected game.')]);
        }

        return $ids;
    }
}
