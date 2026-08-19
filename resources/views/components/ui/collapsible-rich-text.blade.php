@props([
    'content',
    'threshold' => 600,
    'previewHeight' => 240,
])

@php
    $safeHtml = \App\Support\SafeRichText::render((string) $content);
    $plainTextLength = mb_strlen(trim(html_entity_decode(strip_tags((string) $content))));
    $isLong = $plainTextLength > (int) $threshold;
@endphp

<div x-data="{ expanded: false }" {{ $attributes }}>
    <div class="relative">
        <div
            class="overflow-hidden"
            x-bind:style="expanded ? 'max-height: none' : 'max-height: {{ (int) $previewHeight }}px'"
        >
            <div class="prose prose-invert prose-zinc max-w-none text-sm leading-7 text-zinc-300 prose-headings:font-orbitron prose-headings:text-white prose-h2:text-lg prose-h3:mb-2 prose-h3:mt-6 prose-h3:text-sm prose-li:my-1 prose-strong:text-white">
                {!! $safeHtml !!}
            </div>
        </div>

        @if($isLong)
            <div x-show="!expanded" class="pointer-events-none absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-zinc-950/95 to-transparent"></div>
        @endif
    </div>

    @if($isLong)
        <button
            type="button"
            x-on:click="expanded = !expanded"
            class="mt-4 inline-flex items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-950/70 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-cyan-300 transition hover:border-cyan-500/40 hover:text-cyan-200"
        >
            <span x-text="expanded ? 'View less' : 'View more'"></span>
            <i data-lucide="chevron-down" class="h-3.5 w-3.5 transition-transform" x-bind:class="expanded && 'rotate-180'"></i>
        </button>
    @endif
</div>
