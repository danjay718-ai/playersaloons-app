<?php

declare(strict_types=1);

namespace App\Support;

final class SafeRichText
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ol><ul><li><h2><h3><blockquote>';

    public static function render(?string $content): string
    {
        $content = trim((string) $content);

        if ($content === '') {
            return '';
        }

        if ($content === strip_tags($content)) {
            return nl2br(e($content), false);
        }

        $html = strip_tags($content, self::ALLOWED_TAGS);

        // Quill output does not need attributes in the public view. Removing
        // every attribute prevents event handlers, styles, and unsafe URLs.
        $html = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', $html) ?? '';

        return $html;
    }
}
