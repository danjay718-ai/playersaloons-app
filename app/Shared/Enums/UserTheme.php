<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum UserTheme: string
{
    case NEON_ARENA = 'neon_arena';
    case LIGHT = 'light';

    public static function default(): self
    {
        return self::NEON_ARENA;
    }

    public static function fromStored(?string $value): self
    {
        if ($value === self::LIGHT->value) {
            return self::LIGHT;
        }

        return self::NEON_ARENA;
    }

    public function label(): string
    {
        return match ($this) {
            self::NEON_ARENA => 'Neon Arena',
            self::LIGHT => 'Light',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NEON_ARENA => 'Signature dark esports arena with neon lime and electric cyan accents.',
            self::LIGHT => 'A bright, crisp interface with high-contrast slate and cyan accents.',
        };
    }

    /** @return list<string> */
    public function swatches(): array
    {
        return match ($this) {
            self::NEON_ARENA => ['#05080f', '#c6ff3d', '#22e6ff'],
            self::LIGHT => ['#f1f5f9', '#ffffff', '#0891b2'],
        };
    }

    public function metaColor(): string
    {
        return match ($this) {
            self::NEON_ARENA => '#05080f',
            self::LIGHT => '#f1f5f9',
        };
    }
}
