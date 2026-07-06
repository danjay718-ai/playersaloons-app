<main class="landing-page-root min-h-screen bg-[#050311] pt-28 text-zinc-100">
    <article class="mx-auto w-full max-w-4xl px-4 pb-20 sm:px-6 lg:px-8">
        <a href="{{ $indexUrl }}" wire:navigate class="mb-8 inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-zinc-500 transition-colors hover:text-cyan-300">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Back to {{ $label }}
        </a>

        <header>
            <p class="landing-section-kicker">{{ $label }}</p>
            <h1 class="mt-4 font-orbitron text-3xl font-black uppercase leading-tight text-white sm:text-5xl">
                {{ $article->localizedTitle() }}
            </h1>
            @if($article->localizedExcerpt())
                <p class="mt-5 text-base leading-8 text-zinc-400 sm:text-lg">{{ $article->localizedExcerpt() }}</p>
            @endif
            <div class="mt-6 flex flex-wrap items-center gap-3 text-[10px] font-black uppercase tracking-widest text-zinc-600">
                <span>{{ $article->published_at?->format('M j, Y') }}</span>
                @if($article->creator)
                    <span class="text-zinc-800">/</span>
                    <span>{{ $article->creator->username }}</span>
                @endif
            </div>
        </header>

        @if($article->featured_image_path)
            <div class="mt-10 aspect-[16/9] overflow-hidden rounded-lg border border-zinc-800 bg-zinc-950">
                <img src="{{ $article->featured_image_path }}" alt="{{ $article->localizedTitle() }}" class="h-full w-full object-cover">
            </div>
        @endif

        <div class="landing-card prose prose-invert prose-zinc mt-10 max-w-none p-6 prose-headings:font-orbitron prose-a:text-cyan-300 sm:p-8">
            {!! $article->translation()?->content !!}
        </div>
    </article>
</main>
