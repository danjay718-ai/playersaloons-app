<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\CMS\Models\Game;
use App\Shared\Enums\CompetitionType;
use Illuminate\Support\Str;

final class CompetitionBannerResolver
{
    public function resolve(Game $game, CompetitionType $type, ?string $occurrenceOverride = null, ?string $scheduleOverride = null): ?string
    {
        $game->loadMissing(['tournamentDefaults', 'headToHeadDefaults']);
        $default = $type === CompetitionType::HEAD_TO_HEAD
            ? $game->headToHeadDefaults?->head_to_head_banner_path
            : $game->tournamentDefaults?->tournament_banner_path;

        foreach ([$occurrenceOverride, $scheduleOverride, $default, $game->banner_path] as $path) {
            if (filled($path)) {
                return $this->publicUrl((string) $path);
            }
        }

        return null;
    }

    private function publicUrl(string $path): string
    {
        return Str::startsWith($path, ['http://', 'https://', '/']) ? $path : '/storage/'.ltrim($path, '/');
    }
}
