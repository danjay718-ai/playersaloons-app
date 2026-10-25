<?php

declare(strict_types=1);

namespace App\Modules\Localization\Services;

final class ContentTranslationSegments
{
    public const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /** @return list<string> */
    public function split(string $text): array
    {
        if (preg_match('/^(.+?)( - '.$this->datePattern().'\s*)$/u', $text, $matches)) {
            return [...$this->split($matches[1]), $matches[2]];
        }

        // Retain separators so translated content keeps its original spacing.
        $sentences = preg_split('/((?<=[.!?])\s+|\R+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $segments = [];
        foreach ($sentences as $sentence) {
            if (mb_strlen($sentence) <= 500) {
                $segments[] = $sentence;

                continue;
            }

            $chunk = '';
            foreach (preg_split('/(\s+)/u', $sentence, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$sentence] as $word) {
                if (mb_strlen($chunk.$word) > 500 && $chunk !== '') {
                    $segments[] = $chunk;
                    $chunk = '';
                }
                // Even a single unusually long token must fit the indexed key.
                while (mb_strlen($word) > 500) {
                    $segments[] = mb_substr($word, 0, 500);
                    $word = mb_substr($word, 500);
                }
                $chunk .= $word;
            }
            if ($chunk !== '') {
                $segments[] = $chunk;
            }
        }

        return $segments;
    }

    /** @return list<string> */
    public function phrases(string $content): array
    {
        $content = (string) preg_replace('#<(script|style|pre|code|textarea)\b[^>]*>.*?</\1>#is', '', $content);
        preg_match_all('/(<(?:"[^"]*"|\'[^\']*\'|[^><])*>)|([^<]+)/u', $content, $nodes, PREG_SET_ORDER);
        $phrases = [];
        foreach ($nodes as $node) {
            if (! empty($node[1])) {
                continue;
            }
            $text = html_entity_decode($node[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            foreach ($this->split($text) as $segment) {
                $phrase = trim($segment);
                if ($phrase !== '' && ! preg_match('/^ - '.$this->datePattern().'\s*$/u', $segment) && preg_match('/[[:alpha:]]/u', $phrase)) {
                    $phrases[] = $phrase;
                }
            }
        }

        return array_values(array_unique($phrases));
    }

    private function datePattern(): string
    {
        return '(?:'.implode('|', self::MONTHS).') \d{2}, \d{4} \d{1,2}:\d{2}(?: [AP]M)?';
    }
}
