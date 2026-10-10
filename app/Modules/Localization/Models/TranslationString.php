<?php

declare(strict_types=1);

namespace App\Modules\Localization\Models;

use App\Modules\Localization\Services\TranslationCatalogCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $key
 * @property string $locale
 * @property string|null $text
 */
final class TranslationString extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'locale',
        'text',
    ];

    protected static function booted(): void
    {
        $invalidate = fn (self $row) => TranslationCatalogCache::invalidate($row->locale);
        self::saved($invalidate);
        self::deleted($invalidate);
        self::restored($invalidate);
    }
}
