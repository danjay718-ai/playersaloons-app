<?php

namespace App\Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $slug
 * @property string|null $banner_path
 * @property bool $is_active
 */
class Game extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'slug',
        'banner_path',
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
