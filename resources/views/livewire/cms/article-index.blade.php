<main class="landing-page-root min-h-screen bg-[#050311] pt-28 text-zinc-100">
    <section class="mx-auto w-full max-w-6xl px-4 pb-20 sm:px-6 lg:px-8">
        <div class="mb-10">
            <p class="landing-section-kicker">PlayerSaloons {{ strtolower($label) }}</p>
            <h1 class="mt-3 font-orbitron text-4xl font-black uppercase text-white sm:text-5xl">{{ $title }}</h1>
            <p class="mt-4 max-w-3xl text-sm leading-7 text-zinc-400 sm:text-base">{{ $intro }}</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @forelse($articles as $article)
                @php($translation = $article->translation())
                @php($fallbackExcerpt = \Illuminate\Support\Str::limit(strip_tags((string) ($translation?->content ?? '')), 140))
                <a href="/{{ $type }}/{{ $article->slug }}" wire:navigate class="landing-card group block overflow-hidden transition-transform hover:-translate-y-1">
                    @if($article->featured_image_path)
                        <div class="aspect-[16/9] overflow-hidden border-b border-zinc-800 bg-zinc-950">
                            <img src="{{ $article->featured_image_path }}" alt="{{ $article->localizedTitle() }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                        </div>
                    @else
                        <div class="aspect-[16/9] border-b border-zinc-800 bg-[radial-gradient(circle_at_top_left,rgba(34,211,238,0.22),transparent_38%),radial-gradient(circle_at_bottom_right,rgba(124,58,237,0.2),transparent_35%),#09090f]"></div>
                    @endif

                    <div class="p-6">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[10px] font-black uppercase tracking-widest text-cyan-300">{{ $label }}</p>
                            @if($article->is_featured)
                                <span class="rounded border border-amber-400/20 bg-amber-400/10 px-2 py-0.5 text-[9px] font-black uppercase tracking-widest text-amber-300">Featured</span>
                            @endif
                        </div>
                        <h2 class="mt-3 text-xl font-black leading-tight text-white">{{ $article->localizedTitle() }}</h2>
                        <p class="mt-4 text-sm leading-6 text-zinc-400">{{ $translation?->excerpt ?? $fallbackExcerpt }}</p>
                        <p class="mt-5 text-[10px] font-black uppercase tracking-widest text-zinc-600">
                            {{ $article->published_at?->format('M j, Y') }}
                        </p>
                    </div>
                </a>
            @empty
                <div class="landing-card p-8 text-center text-sm text-zinc-500 md:col-span-2 lg:col-span-3">
                    No published {{ strtolower($label) }} articles are available yet.
                </div>
            @endforelse
        </div>
    </section>
</main>
