<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum UserTheme: string
{
    case PURPLE_DARK = 'purple_dark';
    case BLUE_DARK = 'blue_dark';
    case LIGHT = 'light';

    public function label(): string
    {
        return match ($this) {
            self::PURPLE_DARK => 'Purple Dark',
            self::BLUE_DARK => 'Blue Dark',
            self::LIGHT => 'Light',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PURPLE_DARK => 'The original neon purple arena.',
            self::BLUE_DARK => 'A dark navy arena matched to the PlayerSaloons logo.',
            self::LIGHT => 'A bright, cool interface with blue brand accents.',
        };
    }

    /** @return list<string> */
    public function swatches(): array
    {
        return match ($this) {
            self::PURPLE_DARK => ['#08070d', '#7c3aed', '#d946ef'],
            self::BLUE_DARK => ['#06101f', '#2563eb', '#22d3ee'],
            self::LIGHT => ['#f1f5f9', '#ffffff', '#2563eb'],
        };
    }

    public function metaColor(): string
    {
        return match ($this) {
            self::PURPLE_DARK => '#0a0718',
            self::BLUE_DARK => '#06101f',
            self::LIGHT => '#f1f5f9',
        };
    }
}
