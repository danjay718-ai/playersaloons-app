<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Model;

class GatewayCredential extends Model
{
    protected $guarded = [];

    protected $hidden = ['value'];

    protected static function booted(): void
    {
        $immutable = fn () => throw new \LogicException('Gateway credential versions are immutable.');
        static::updating($immutable);
        static::deleting($immutable);
    }

    protected function casts(): array
    {
        return ['value' => 'encrypted', 'uses_environment' => 'boolean'];
    }
}
