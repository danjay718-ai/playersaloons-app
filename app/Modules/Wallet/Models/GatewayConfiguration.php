<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GatewayConfiguration extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        $immutable = fn () => throw new \LogicException('Gateway credential versions are immutable.');
        static::updating($immutable);
        static::deleting($immutable);
    }

    /** @return HasMany<GatewayCredential, $this> */
    public function credentials(): HasMany
    {
        return $this->hasMany(GatewayCredential::class);
    }

    public function values(): array
    {
        return $this->credentials->mapWithKeys(fn ($credential) => [$credential->name => $credential->value])->all();
    }

    public function credential(string $name): string
    {
        return (string) $this->credentials->firstWhere('name', $name)?->value;
    }
}
