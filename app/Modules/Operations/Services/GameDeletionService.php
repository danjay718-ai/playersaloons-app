<?php

declare(strict_types=1);

namespace App\Modules\Operations\Services;

use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class GameDeletionService
{
    // These are game-owned catalog metadata, not competition or player history.
    private const CATALOG_TABLES = ['game_translations', 'game_platform'];

    public function references(Game $game): array
    {
        $references = [];
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            if ($name === 'games' || in_array($name, self::CATALOG_TABLES, true)) {
                continue;
            }
            $columns = in_array('game_id', Schema::getColumnListing($name), true) ? ['game_id'] : [];
            foreach (Schema::getForeignKeys($name) as $foreignKey) {
                if ($foreignKey['foreign_table'] === 'games') {
                    $columns = array_merge($columns, $foreignKey['columns']);
                }
            }
            foreach (array_unique($columns) as $column) {
                // Query raw tables so deleted history also blocks permanent deletion.
                $count = DB::table($name)->where($column, $game->id)->count();
                if ($count > 0) {
                    $references[$name] = max($references[$name] ?? 0, $count);
                }
            }
        }

        return $references;
    }

    public function restore(int $id, User $actor): void
    {
        abort_unless($actor->can('games.restore'), 403);
        DB::transaction(function () use ($id, $actor): void {
            $game = Game::onlyTrashed()->lockForUpdate()->findOrFail($id);
            $game->update(['is_active' => false]);
            $game->restore();
            activity()->causedBy($actor)->performedOn($game)->log('admin_game_restored');
        });
    }

    public function permanentlyDelete(int $id, User $actor): void
    {
        abort_unless($actor->can('games.force_delete'), 403);
        DB::transaction(function () use ($id, $actor): void {
            $game = Game::onlyTrashed()->lockForUpdate()->findOrFail($id);
            if ($this->references($game) !== []) {
                throw ValidationException::withMessages(['permanentDeleteConfirmation' => 'Permanent deletion is blocked because connected data still exists. Keep this game soft deleted to preserve history.']);
            }
            activity()->causedBy($actor)->withProperties([
                'game_id' => $game->id, 'uuid' => $game->uuid,
                'slug' => $game->slug, 'name' => $game->localizedName('en'),
                'recoverable' => false,
            ])->log('admin_game_permanently_deleted');

            // Remove only owned metadata explicitly; do not depend on cascades.
            foreach (self::CATALOG_TABLES as $table) {
                DB::table($table)->where('game_id', $id)->delete();
            }
            $game->forceDelete();
        });
    }
}
