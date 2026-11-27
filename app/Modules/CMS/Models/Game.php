<?php

namespace App\Modules\CMS\Models;

use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Tournament\Models\Tournament;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $slug
 * @property int $game_category_id
 * @property string $gameplay_provider
 * @property array<string, mixed>|null $provider_config
 * @property string|null $banner_path
 * @property string|null $card_image_path
 * @property bool $is_active
 * @property-read GameCategory $category
 * @property-read GameTournamentDefault|null $tournamentDefaults
 * @property-read GameHeadToHeadDefault|null $headToHeadDefaults
 * @property-read Collection<int, StreamChannel> $streamChannels
 */
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    use SoftDeletes;

    public const PROVIDER_MANUAL = 'manual';

    public const PROVIDER_PLAYSTRATEGY = 'playstrategy';

    /**
     * Supported PlayStrategy board/card strategy variants.
     *
     * @var array<string, string>
     */
    public const PLAYSTRATEGY_VARIANTS = [
        'standard' => 'Chess',
        'backgammon' => 'Backgammon',
        'international' => 'International Draughts',
        'flipello' => 'Othello (Flipello)',
        'shogi' => 'Shogi',
        'xiangqi' => 'Xiangqi',
        'linesOfAction' => 'Lines of Action',
        'abalone' => 'Abalone',
        'amazons' => 'Amazons',
        'oware' => 'Oware',
        'go9x9' => 'Go 9x9',
        'gomoku' => 'Gomoku',
        'pente' => 'Pente',
        'mancala' => 'Mancala',
        'breakthroughtroyka' => 'Breakthrough',
        'chess960' => 'Chess960',
    ];

    /**
     * Pre-configured PlayStrategy game presets for one-click admin catalog creation.
     *
     * @return array<string, array{name: string, slug: string, description: string, clock_limit: int, clock_increment: int, artwork: string|null}>
     */
    public static function playStrategyCatalog(): array
    {
        return [
            'standard' => [
                'name' => 'Chess',
                'slug' => 'playstrategy-chess',
                'description' => 'Classic 1v1 competitive chess powered by PlayStrategy with automated match boards and verified results.',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => '/images/games/playstrategy-chess.svg',
            ],
            'backgammon' => [
                'name' => 'Backgammon',
                'slug' => 'playstrategy-backgammon',
                'description' => 'Head-to-head competitive backgammon hosted on PlayStrategy with automated result tracking.',
                'clock_limit' => 420,
                'clock_increment' => 5,
                'artwork' => '/images/games/playstrategy-backgammon.svg',
            ],
            'international' => [
                'name' => 'International Draughts',
                'slug' => 'playstrategy-draughts',
                'description' => 'Competitive 10x10 International Draughts on PlayStrategy with live browser boards.',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => '/images/games/playstrategy-draughts.svg',
            ],
            'flipello' => [
                'name' => 'Othello (Flipello)',
                'slug' => 'playstrategy-flipello',
                'description' => 'Fast-paced strategy board battles in Othello (Flipello) via PlayStrategy.',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => '/images/games/playstrategy-flipello.svg',
            ],
            'shogi' => [
                'name' => 'Shogi',
                'slug' => 'playstrategy-shogi',
                'description' => 'Japanese strategy chess with piece drops, hosted live on PlayStrategy with verified results.',
                'clock_limit' => 600,
                'clock_increment' => 5,
                'artwork' => null,
            ],
            'xiangqi' => [
                'name' => 'Xiangqi',
                'slug' => 'playstrategy-xiangqi',
                'description' => 'Traditional Chinese Chess (Xiangqi) played on live PlayStrategy boards with automatic winner sync.',
                'clock_limit' => 600,
                'clock_increment' => 5,
                'artwork' => null,
            ],
            'linesOfAction' => [
                'name' => 'Lines of Action',
                'slug' => 'playstrategy-lines-of-action',
                'description' => 'Abstract connection board game hosted on PlayStrategy with automated match boards.',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => null,
            ],
            'abalone' => [
                'name' => 'Abalone',
                'slug' => 'playstrategy-abalone',
                'description' => 'Tactical marble-pushing board duels powered by PlayStrategy.',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => null,
            ],
            'amazons' => [
                'name' => 'Amazons',
                'slug' => 'playstrategy-amazons',
                'description' => 'Territory-control strategy battles in Game of the Amazons on PlayStrategy.',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => null,
            ],
            'oware' => [
                'name' => 'Oware',
                'slug' => 'playstrategy-oware',
                'description' => 'Classic West African mancala strategy matches hosted live on PlayStrategy.',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => null,
            ],
            'go9x9' => [
                'name' => 'Go 9x9',
                'slug' => 'playstrategy-go-9x9',
                'description' => 'Fast-paced 9x9 Go strategy matches with automated PlayStrategy boards and scoring.',
                'clock_limit' => 300,
                'clock_increment' => 5,
                'artwork' => null,
            ],
            'gomoku' => [
                'name' => 'Gomoku',
                'slug' => 'playstrategy-gomoku',
                'description' => 'Five-in-a-row strategy duels hosted on PlayStrategy with verified winner sync.',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => null,
            ],
            'pente' => [
                'name' => 'Pente',
                'slug' => 'playstrategy-pente',
                'description' => 'Competitive Pente board matches with captures and five-in-a-row wins on PlayStrategy.',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => null,
            ],
            'mancala' => [
                'name' => 'Mancala',
                'slug' => 'playstrategy-mancala',
                'description' => 'Head-to-head Mancala seed-capture strategy matches powered by PlayStrategy.',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => null,
            ],
            'breakthroughtroyka' => [
                'name' => 'Breakthrough',
                'slug' => 'playstrategy-breakthrough',
                'description' => 'Fast tactical pawn-race strategy matches on PlayStrategy.',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => null,
            ],
            'chess960' => [
                'name' => 'Chess960',
                'slug' => 'playstrategy-chess960',
                'description' => 'Fischer Random Chess960 matches with live PlayStrategy boards and verified results.',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => '/images/games/playstrategy-chess.svg',
            ],
        ];
    }

    protected static function newFactory(): GameFactory
    {
        return GameFactory::new();
    }

    /**
     * Player/public catalog visibility: active, not archived, and inside an active category.
     * Deliberately an opt-in scope (never a global scope) so background jobs,
     * existing matches, payouts, and admin screens are never interrupted.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAvailableInCatalog(Builder $query): Builder
    {
        $query->withoutTrashed();

        return $query
            ->where('games.is_active', true)
            ->whereHas('category', fn (Builder $categories) => $categories->where('game_categories.is_active', true));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInCategorySlug(Builder $query, ?string $slug): Builder
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            return $query;
        }

        return $query->whereHas('category', fn (Builder $categories) => $categories->where('game_categories.slug', $slug));
    }

    public function isAvailableInCatalog(): bool
    {
        if ($this->trashed() || ! $this->is_active) {
            return false;
        }

        $category = $this->relationLoaded('category')
            ? $this->category
            : $this->category()->first();

        return $category !== null && (bool) $category->is_active;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'slug',
        'game_category_id',
        'gameplay_provider',
        'provider_config',
        'banner_path',
        'card_image_path',
        'game_id_settings',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array{game_category_id: 'integer', provider_config: 'array', is_active: 'boolean', game_id_settings: 'array'}
     */
    protected function casts(): array
    {
        return [
            'game_category_id' => 'integer',
            'provider_config' => 'array',
            'is_active' => 'boolean',
            'game_id_settings' => 'array',
        ];
    }

    public function isPlayStrategyManaged(): bool
    {
        return ($this->gameplay_provider ?? self::PROVIDER_MANUAL) === self::PROVIDER_PLAYSTRATEGY;
    }

    public function playStrategyVariant(): string
    {
        $configured = trim((string) ($this->provider_config['variant'] ?? ''));

        return match (strtolower($configured)) {
            '', 'chess' => 'standard',
            'draughts' => 'international',
            default => $configured,
        };
    }

    /**
     * @return array{variant: string, clock_limit: int, clock_increment: int, rated: bool}
     */
    public function playStrategySettings(): array
    {
        $config = is_array($this->provider_config) ? $this->provider_config : [];

        return [
            'variant' => $this->playStrategyVariant(),
            'clock_limit' => max(60, (int) ($config['clock_limit'] ?? 300)),
            'clock_increment' => max(0, (int) ($config['clock_increment'] ?? 3)),
            'rated' => (bool) ($config['rated'] ?? false),
        ];
    }

    /**
     * @return BelongsTo<GameCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GameCategory::class, 'game_category_id');
    }

    /**
     * Get translations for the game.
     *
     * @return HasMany<GameTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(GameTranslation::class);
    }

    /**
     * Get trailer/embed stream channels for the game.
     *
     * @return HasMany<StreamChannel, $this>
     */
    public function streamChannels(): HasMany
    {
        return $this->hasMany(StreamChannel::class);
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    /** @return HasOne<GameTournamentDefault, $this> */
    public function tournamentDefaults(): HasOne
    {
        return $this->hasOne(GameTournamentDefault::class);
    }

    /** Defaults for platform-managed 1v1 schedules; never shared with tournaments. */
    /** @return HasOne<GameHeadToHeadDefault, $this> */
    public function headToHeadDefaults(): HasOne
    {
        return $this->hasOne(GameHeadToHeadDefault::class);
    }

    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class)->withTimestamps();
    }

    public function bannerUrl(): ?string
    {
        return $this->mediaUrl($this->banner_path);
    }

    public function cardImageUrl(): ?string
    {
        return $this->cardArtworkUrl() ?: $this->bannerUrl();
    }

    public function cardArtworkUrl(): ?string
    {
        return $this->mediaUrl($this->card_image_path);
    }

    private function mediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://', '/']) ? $path : '/storage/'.ltrim($path, '/');
    }

    public function translation(?string $locale = null): ?GameTranslation
    {
        $locale ??= app()->getLocale();

        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale)
                ?? $this->translations->firstWhere('locale', 'en');
        }

        return $this->translations()->where('locale', $locale)->first()
            ?? $this->translations()->where('locale', 'en')->first();
    }

    public function localizedName(?string $locale = null): string
    {
        return $this->translation($locale)->name ?? $this->slug;
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        return $this->translation($locale)?->description;
    }
}
