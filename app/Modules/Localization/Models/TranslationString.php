<?php

declare(strict_types=1);

namespace App\Modules\Localization\Models;

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
}
