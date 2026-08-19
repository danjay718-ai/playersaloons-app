<?php

namespace App\Modules\CMS\Models;

use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Tournament\Models\Tournament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $slug
 * @property string|null $banner_path
 * @property string|null $card_image_path
 * @property bool $is_active
 * @property-read Collection<int, StreamChannel> $streamChannels
 */
class Game extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'slug',
        'banner_path',
        'card_image_path',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get translations for the game.
     *
     * @return HasMany<GameTranslation, Game>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(GameTranslation::class);
    }

    /**
     * Get trailer/embed stream channels for the game.
     *
     * @return HasMany<StreamChannel, Game>
     */
    public function streamChannels(): HasMany
    {
        return $this->hasMany(StreamChannel::class);
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
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
        return $this->translation($locale)?->name ?? $this->slug;
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        return $this->translation($locale)?->description;
    }
}
