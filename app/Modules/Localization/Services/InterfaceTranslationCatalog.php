<?php

declare(strict_types=1);

namespace App\Modules\Localization\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\BladeCompiler;

final class InterfaceTranslationCatalog
{
    public function __construct(
        private readonly BladeCompiler $blade,
        private readonly ContentTranslationSegments $segments,
    ) {}

    /** @return iterable<string> */
    public function phrases(): iterable
    {
        // Inspect source literals, without rendering pages or reading player data.
        foreach ([resource_path('views'), app_path('Livewire'), app_path('Http/Controllers'), app_path('Modules'), app_path('Shared/Enums')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                if ($file->getExtension() !== 'php' || str_contains($path, '/admin/') || str_contains($path, '/Admin/')
                    || str_ends_with($path, '/admin-content.blade.php') || str_ends_with($path, '/layouts/admin.blade.php')) {
                    continue;
                }
                yield from $this->extract(File::get($path), str_ends_with($path, '.blade.php'));
            }
        }
        yield from array_values(config('countries', []));
        yield from $this->javascriptPhrases();
    }

    /** @return list<string> */
    public function javascriptPhrases(): array
    {
        $phrases = [];
        foreach (File::allFiles(resource_path('js')) as $file) {
            if ($file->getExtension() === 'js') {
                $this->collectLiterals('<?php '.File::get($file->getPathname()), $phrases);
            }
        }

        return array_keys($phrases);
    }

    /** @return list<string> */
    public function extract(string $source, bool $isBlade): array
    {
        $phrases = [];
        if ($isBlade) {
            $source = preg_replace_callback('#<script\b[^>]*>(.*?)</script>#is', function ($match) use (&$phrases): string {
                $this->collectLiterals('<?php '.$match[1], $phrases);

                return '';
            }, $source);
            $source = $this->blade->compileString($source);
        }
        $html = $this->collectLiterals($source, $phrases);
        if ($isBlade && trim($html) !== '') {
            $document = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML('<?xml encoding="UTF-8">'.$html);
                $xpath = new DOMXPath($document);
                foreach ($xpath->query('//text()[not(ancestor::script or ancestor::style or ancestor::pre or ancestor::code or ancestor::textarea or ancestor::*[@translate="no"] or ancestor::*[@aria-hidden="true"])]') as $node) {
                    $this->add($node->nodeValue, $phrases);
                }
                foreach ($xpath->query('//@*') as $attribute) {
                    if (! $attribute instanceof \DOMAttr) {
                        continue;
                    }
                    if (in_array($attribute->name, ['label', 'action-label', 'scroll-label', 'title', 'alt', 'placeholder', 'aria-label', 'wire:confirm'], true)) {
                        if (! str_contains($attribute->value, '$') && ! str_contains($attribute->value, '{{')) {
                            $this->add($attribute->value, $phrases);
                        }
                    } elseif (str_starts_with($attribute->name, 'x-') || str_starts_with($attribute->name, '@') || str_starts_with($attribute->name, ':')) {
                        $this->collectLiterals('<?php '.$attribute->value, $phrases);
                    }
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
        }

        return array_keys($phrases);
    }

    private function collectLiterals(string $source, array &$phrases): string
    {
        $html = '';
        $previous = [];
        foreach (token_get_all($source) as $token) {
            if (! is_array($token)) {
                $previous[] = $token;
                $previous = array_slice($previous, -2);

                continue;
            }
            if ($token[0] === T_INLINE_HTML) {
                $html .= $token[1];
            } elseif ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $text = substr($token[1], 1, -1);
                $text = $token[1][0] === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], $text) : stripcslashes($text);
                $explicitTranslation = count($previous) === 2 && $previous[1] === '('
                    && in_array($previous[0], ['__', 'trans', 'trans_choice'], true);
                if (($explicitTranslation && ! preg_match('/^[a-z_]+\.[a-z_.]+$/', $text)) || $this->isCopy($text)) {
                    $this->add($text, $phrases);
                }
            }
            if (! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $previous[] = $token[1];
                $previous = array_slice($previous, -2);
            }
        }

        return $html;
    }

    private function isCopy(string $text): bool
    {
        if (! preg_match('/[A-Za-z]/', $text) || preg_match('/\$[a-zA-Z_{]/', $text) || str_contains($text, '\\')
            || str_contains($text, '=>') || str_contains($text, '{{') || str_contains($text, '::')
            || preg_match('#^(https?://|/|[a-z_]+\.[a-z_.]+$)#', $text)
            || preg_match('/\b(?:bg|text|border|rounded|font|shadow|space|grid|flex|items|justify|tracking|leading|p|px|py|m|mx|my|w|h|min|max)-[a-z0-9\[]/', $text)
            || preg_match('/^(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)\s/', $text)) {
            return false;
        }
        if (preg_match('/^\([a-z-]+:\s*[^)]+\)$/', $text)) {
            return false;
        }
        if (preg_match('/^[dDjlFmMnYy]+(?:[ ,\/-]+[dDjlFmMnYy]+)+(?:\s+[gGhH]:i(?::s)?(?:\s+[AaT])*)?$/', $text)) {
            return false;
        }

        return str_contains($text, ' ') || preg_match('/^[A-Z][a-z]+[.!?…]?$/u', $text) === 1;
    }

    private function add(string $text, array &$phrases): void
    {
        $text = trim($text);
        foreach (mb_strlen($text) <= 500 ? [$text] : $this->segments->split($text) as $phrase) {
            $phrase = trim($phrase);
            if ($phrase !== '' && preg_match('/[A-Za-z]/', $phrase)) {
                $phrases[$phrase] = true;
            }
        }
    }
}
