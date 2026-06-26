<?php

declare(strict_types=1);

namespace App\Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandingSectionItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'landing_section_id',
        'item_key',
        'title',
        'subtitle',
        'body',
        'icon',
        'label',
        'url',
        'metadata',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<LandingSection, LandingSectionItem>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(LandingSection::class, 'landing_section_id');
    }
}
