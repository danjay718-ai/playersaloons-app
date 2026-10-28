<main class="landing-page-root gr-public-page min-h-screen bg-[#05080f] pt-10 text-zinc-100">
    <section
        class="mx-auto w-full max-w-6xl px-4 pb-20 sm:px-6 lg:px-8"
        x-data="{
            search: '',
            expandedId: {{ $articles->first()?->id ?? 'null' }},
            matchesSearch(text) {
                const q = this.search.trim().toLowerCase();
                return !q || text.toLowerCase().includes(q);
            }
        }"
    >
        {{-- Header & Channel Switcher --}}
        <div class="mb-8 flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
                <p class="gr-eyebrow"><span class="gr-slash" aria-hidden="true">//</span> {{ __('GamersRival :value1', ['value1' => (strtolower($label))]) }}</p>
                <h1 class="mt-2 font-orbitron text-4xl font-black uppercase tracking-tight text-white sm:text-5xl">{{ $title }}</h1>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-[#a8b6c9] sm:text-base">{{ $intro }}</p>
            </div>

            {{-- Channel Navigation Pills --}}
            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('updates.index') }}"
                    wire:navigate
                    class="inline-flex items-center gap-1.5 rounded-lg border border-white/15 bg-[#0a1220] px-3.5 py-2 text-xs font-extrabold uppercase tracking-wider text-[#a8b6c9] transition hover:border-[#22e6ff] hover:text-white"
                >
                    <span>← {{ __('All Updates') }}</span>
                </a>
                <a
                    href="{{ route('blog.index') }}"
                    wire:navigate
                    class="inline-flex items-center gap-1.5 rounded-lg border px-3.5 py-2 text-xs font-extrabold uppercase tracking-wider transition {{ $type === 'blog' ? 'border-[#c6ff3d] bg-[#c6ff3d] text-[#05080f]' : 'border-white/15 bg-[#0a1220] text-[#a8b6c9] hover:border-[#c6ff3d]/50 hover:text-white' }}"
                >
                    <span>Blog</span>
                </a>
                <a
                    href="{{ route('news.index') }}"
                    wire:navigate
                    class="inline-flex items-center gap-1.5 rounded-lg border px-3.5 py-2 text-xs font-extrabold uppercase tracking-wider transition {{ $type === 'news' ? 'border-[#22e6ff] bg-[#22e6ff] text-[#05080f]' : 'border-white/15 bg-[#0a1220] text-[#a8b6c9] hover:border-[#22e6ff]/50 hover:text-white' }}"
                >
                    <span>News</span>
                </a>
            </div>
        </div>

        {{-- Interactive Search & Quick-Preview Bar --}}
        <div class="mb-6 flex flex-col gap-3 rounded-xl border border-white/10 bg-[#0a1220]/90 p-3.5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2.5 text-xs font-bold text-[#a8b6c9]">
                <span class="h-2 w-2 rounded-full bg-[#c6ff3d]"></span>
                <span>Showing <strong class="text-white">{{ $articles->count() }}</strong> published {{ strtolower($label) }} dispatches — click <strong class="text-[#c6ff3d]">Quick Read</strong> on any row to expand inline.</span>
            </div>

            <div class="relative w-full sm:w-72">
                <input
                    type="search"
                    x-model="search"
                    placeholder="Filter {{ strtolower($label) }} articles..."
                    class="w-full rounded-lg border border-white/15 bg-[#05080f]/90 py-2 pl-3.5 pr-8 text-xs font-semibold text-white placeholder-[#7f8ea3] focus:border-[#22e6ff] focus:outline-none"
                >
                <button
                    type="button"
                    x-show="search.length > 0"
                    @click="search = ''"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-bold text-[#a8b6c9] hover:text-white"
                    aria-label="Clear search"
                >
                    ✕
                </button>
            </div>
        </div>

        {{-- Interactive Article Board --}}
        <div class="flex flex-col gap-4">
            @forelse($articles as $article)
                @php
                    $translation = $article->translation();
                    $rawContent = (string) ($translation?->content ?? '');
                    $plainContent = trim(strip_tags($rawContent));
                    $fallbackExcerpt = \Illuminate\Support\Str::limit($plainContent, 160);
                    $readMinutes = max(1, (int) ceil(max(1, str_word_count($plainContent)) / 180));
                    $searchText = $article->localizedTitle().' '.($translation?->excerpt ?? $fallbackExcerpt);
                @endphp

                <article
                    x-show="matchesSearch(@js($searchText))"
                    class="landing-card gr-surface-card overflow-hidden border transition-all"
                    :class="expandedId === {{ $article->id }} ? 'border-[#c6ff3d]/60 shadow-[0_0_28px_rgba(198,255,61,0.08)]' : 'border-white/10'"
                >
                    <div class="flex flex-col md:flex-row md:items-stretch">
                        {{-- Thumbnail --}}
                        <a href="/{{ $type }}/{{ $article->slug }}" wire:navigate class="group relative aspect-[16/9] w-full shrink-0 overflow-hidden border-b border-white/10 bg-[#070d17] md:aspect-auto md:w-64 md:border-b-0 md:border-r">
                            @if($article->featured_image_path)
                                <img src="{{ $article->featured_image_path }}" alt="{{ $article->localizedTitle() }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                            @else
                                <div class="flex h-full min-h-[150px] w-full items-center justify-center bg-[radial-gradient(circle_at_top_left,rgba(198,255,61,0.18),transparent_45%),radial-gradient(circle_at_bottom_right,rgba(34,230,255,0.18),transparent_45%),#070d17]">
                                    <img src="/gamersrival-icon.png" alt="" width="44" height="44" class="opacity-75">
                                </div>
                            @endif
                        </a>

                        {{-- Content Summary + Interactive Controls --}}
                        <div class="flex flex-1 flex-col justify-between p-5 sm:p-6">
                            <div>
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border border-[#22e6ff]/35 bg-[#22e6ff]/10 px-3 py-0.5 text-[10px] font-extrabold uppercase tracking-widest text-[#22e6ff]">{{ $label }}</span>
                                        @if($article->is_featured)
                                            <span class="rounded-full border border-[#ffd23f]/40 bg-[#ffd23f]/15 px-3 py-0.5 text-[10px] font-extrabold uppercase tracking-widest text-[#ffd23f]">Featured</span>
                                        @endif
                                        <span class="text-[11px] font-bold text-[#7f8ea3]">• {{ $readMinutes }} min read</span>
                                    </div>
                                    <span class="text-xs font-bold uppercase tracking-wider text-[#a8b6c9]">
                                        {{ $article->published_at?->format('M j, Y') }}
                                    </span>
                                </div>

                                <a href="/{{ $type }}/{{ $article->slug }}" wire:navigate class="group block">
                                    <h2 class="mt-2.5 font-orbitron text-xl font-black leading-snug text-white transition-colors group-hover:text-[#c6ff3d] sm:text-2xl">{{ $article->localizedTitle() }}</h2>
                                </a>
                                <p class="mt-2 text-sm leading-6 text-[#a8b6c9]">{{ $translation?->excerpt ?? $fallbackExcerpt }}</p>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-3.5">
                                <button
                                    type="button"
                                    @click="expandedId = expandedId === {{ $article->id }} ? null : {{ $article->id }}"
                                    class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-extrabold uppercase tracking-wider transition"
                                    :class="expandedId === {{ $article->id }}
                                        ? 'border-[#c6ff3d] bg-[#c6ff3d]/15 text-[#c6ff3d]'
                                        : 'border-white/15 bg-[#05080f]/70 text-[#a8b6c9] hover:border-[#22e6ff]/50 hover:text-white'"
                                >
                                    <span x-text="expandedId === {{ $article->id }} ? 'Hide Quick Read' : 'Quick Read Inline'"></span>
                                    <span x-text="expandedId === {{ $article->id }} ? '▴' : '▾'" aria-hidden="true"></span>
                                </button>

                                <a href="/{{ $type }}/{{ $article->slug }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-[#c6ff3d] hover:text-[#22e6ff]">
                                    <span>{{ __('Read article') }}</span>
                                    <span aria-hidden="true">↗</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Inline Quick-Read Drawer --}}
                    <div
                        x-show="expandedId === {{ $article->id }}"
                        x-cloak
                        class="border-t border-white/10 bg-[#060b14]/95 px-5 py-5 sm:px-7"
                    >
                        <div class="mb-3 flex items-center justify-between text-[11px] font-extrabold uppercase tracking-[0.16em] text-[#22e6ff]">
                            <span>Quick-Read Dispatch Preview</span>
                            <a href="/{{ $type }}/{{ $article->slug }}" wire:navigate class="text-[#c6ff3d] hover:underline">Open Full Page ↗</a>
                        </div>
                        <div class="prose prose-invert prose-zinc max-w-none text-sm leading-7 prose-headings:font-orbitron prose-a:text-[#22e6ff]">
                            {!! $rawContent !!}
                        </div>
                    </div>
                </article>
            @empty
                <div class="landing-card gr-surface-card p-10 text-center text-sm text-[#a8b6c9]">{{ __('No published :value1 articles are available yet.', ['value1' => (strtolower($label))]) }}</div>
            @endforelse
        </div>
    </section>
</main>
