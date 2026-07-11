<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Advertisement extends Model
{
    /** @var list<string> */
    protected $fillable = ['uuid', 'title', 'description', 'image_url', 'target_url', 'cta_label', 'is_active', 'starts_at', 'ends_at', 'impressions', 'clicks', 'created_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function scopeCurrentlyVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q): Builder => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q): Builder => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
