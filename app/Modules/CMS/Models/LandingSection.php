<?php

declare(strict_types=1);

namespace App\Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandingSection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'key',
        'title',
        'subtitle',
        'body',
        'media_path',
        'cta_label',
        'cta_url',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<LandingSectionItem, LandingSection>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LandingSectionItem::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<LandingSectionItem, LandingSection>
     */
    public function activeItems(): HasMany
    {
        return $this->items()->where('is_active', true);
    }
}
