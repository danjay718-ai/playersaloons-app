<?php

declare(strict_types=1);

namespace App\Modules\Identity\Casts;

use App\Shared\Enums\UserTheme;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<UserTheme, UserTheme|string|null>
 */
final class UserThemeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): UserTheme
    {
        return UserTheme::fromStored(is_string($value) ? $value : null);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof UserTheme) {
            return $value->value;
        }

        return UserTheme::fromStored(is_string($value) ? $value : null)->value;
    }
}
