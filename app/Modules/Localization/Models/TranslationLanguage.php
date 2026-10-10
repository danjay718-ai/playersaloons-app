<?php

declare(strict_types=1);

namespace App\Modules\Localization\Models;

use Illuminate\Database\Eloquent\Model;

final class TranslationLanguage extends Model
{
    protected $fillable = ['code', 'english', 'native', 'flag'];
}
