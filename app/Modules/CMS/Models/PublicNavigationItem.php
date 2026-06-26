<?php

declare(strict_types=1);

namespace App\Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PublicNavigationItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'label',
        'url',
        'icon',
        'match_pattern',
        'visibility',
        'sort_order',
        'is_active',
        'opens_new_tab',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'opens_new_tab' => 'boolean',
        ];
    }
}
