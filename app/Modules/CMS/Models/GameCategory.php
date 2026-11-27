<?php

declare(strict_types=1);

namespace App\Modules\CMS\Models;

use Database\Factories\GameCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property int $display_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Game> $games
 */
class GameCategory extends Model
{
    /** @use HasFactory<GameCategoryFactory> */
    use HasFactory;

    public const VIDEO_GAMES_SLUG = 'video-games';

    public const CARD_BOARD_GAMES_SLUG = 'card-board-games';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'display_order',
        'is_active',
    ];

    /**
     * @return array{display_order: 'integer', is_active: 'boolean'}
     */
    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): GameCategoryFactory
    {
        return GameCategoryFactory::new();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('game_categories.is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('game_categories.display_order')
            ->orderBy('game_categories.name')
            ->orderBy('game_categories.id');
    }

    /**
     * Non-archived games in this category.
     *
     * @return HasMany<Game, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class, 'game_category_id');
    }

    /**
     * Every game in this category, including soft-deleted (archived) records.
     *
     * @return HasMany<Game, $this>
     */
    public function allGames(): HasMany
    {
        return $this->hasMany(Game::class, 'game_category_id')->withTrashed();
    }
}
