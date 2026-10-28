@php
    $rawContent = (string) ($article->translation()?->content ?? '');
    $plainContent = trim(strip_tags($rawContent));
    $readMinutes = max(1, (int) ceil(max(1, str_word_count($plainContent)) / 180));
@endphp

<main class="landing-page-root gr-public-page min-h-screen bg-[#05080f] pt-10 text-zinc-100">
    <article
        class="mx-auto w-full max-w-4xl px-4 pb-20 sm:px-6 lg:px-8"
        x-data="{ copied: false }"
    >
        <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ $indexUrl }}" wire:navigate class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-[#0e1726] px-4 py-2 text-xs font-extrabold uppercase tracking-wider text-[#a8b6c9] transition-colors hover:border-[#c6ff3d] hover:text-[#c6ff3d]">
                    <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>{{ __('Back to :value1', ['value1' => ($label)]) }}
                </a>
                <a href="{{ route('updates.index') }}" wire:navigate class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-[#080f1c] px-3.5 py-2 text-xs font-extrabold uppercase tracking-wider text-[#22e6ff] transition-colors hover:border-[#22e6ff]">
                    <span>All Updates Hub</span>
                </a>
            </div>

            <button
                type="button"
                @click="navigator.clipboard.writeText(window.location.href); copied = true; setTimeout(() => copied = false, 2000)"
                class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-[#0e1726] px-3.5 py-2 text-xs font-extrabold uppercase tracking-wider text-[#a8b6c9] transition hover:border-[#c6ff3d] hover:text-[#c6ff3d]"
            >
                <span x-text="copied ? '✓ Link Copied' : 'Share / Copy Link'"></span>
            </button>
        </div>

        <header class="rounded-2xl border border-white/10 bg-[#0a1220]/85 p-6 sm:p-8">
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="rounded-full border border-[#22e6ff]/40 bg-[#22e6ff]/10 px-3 py-0.5 text-[10px] font-extrabold uppercase tracking-widest text-[#22e6ff]">{{ $label }}</span>
                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-0.5 text-[10px] font-extrabold uppercase tracking-widest text-[#c6ff3d]">{{ $readMinutes }} min read</span>
            </div>

            <h1 class="mt-4 font-orbitron text-3xl font-black uppercase leading-tight text-white sm:text-5xl">
                {{ $article->localizedTitle() }}
            </h1>
            @if($article->localizedExcerpt())
                <p class="mt-4 text-base leading-8 text-[#a8b6c9] sm:text-lg">{{ $article->localizedExcerpt() }}</p>
            @endif
            <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-white/10 pt-4 text-xs font-extrabold uppercase tracking-widest text-[#22e6ff]">
                <span>{{ $article->published_at?->format('M j, Y') }}</span>
                @if($article->creator)
                    <span class="text-white/25">/</span>
                    <span translate="no" class="text-[#c6ff3d]">{{ $article->creator->username }}</span>
                @endif
            </div>
        </header>

        @if($article->featured_image_path)
            <div class="mt-8 aspect-[16/9] overflow-hidden rounded-2xl border border-[#22e6ff]/25 bg-[#0a111d]">
                <img src="{{ $article->featured_image_path }}" alt="{{ $article->localizedTitle() }}" class="h-full w-full object-cover">
            </div>
        @endif

        <div class="landing-card gr-surface-card prose prose-invert prose-zinc mt-8 max-w-none p-6 prose-headings:font-orbitron prose-a:text-[#22e6ff] sm:p-8">
            {!! $rawContent !!}
        </div>
    </article>
</main>
