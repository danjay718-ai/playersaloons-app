<?php

declare(strict_types=1);

namespace App\Modules\CMS\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PolicyPage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'slug',
        'title',
        'summary',
        'content',
        'sort_order',
        'is_active',
        'published_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, PolicyPage>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
