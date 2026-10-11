<?php

declare(strict_types=1);

namespace App\Modules\CMS\Services;

final class AboutContentFormatter
{
    public function format(string $content): string
    {
        // Existing admin-authored HTML already defines its own paragraph structure.
        if (preg_match('/<\/?[a-z][^>]*>/i', $content)) {
            return $content;
        }

        $content = trim(str_replace(["\r\n", "\r"], "\n", $content));
        if ($content === '') {
            return '';
        }

        $paragraphs = preg_split('/\n(?:[\t ]*\n)+/', $content);

        return implode("\n", array_map(
            fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph)), false).'</p>',
            $paragraphs,
        ));
    }
}
