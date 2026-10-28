@php
    $feedItems = collect($articles ?? [])->map(function ($article) {
        $translation = $article->translation();
        $rawContent = (string) ($translation?->content ?? '');
        $plainText = trim(strip_tags($rawContent));
        $wordCount = max(1, str_word_count($plainText));
        $readMinutes = max(1, (int) ceil($wordCount / 180));

        return [
            'id' => $article->id,
            'type' => $article->type,
            'label' => $article->type === 'news' ? 'Official News' : 'Community Blog',
            'badge' => $article->type === 'news' ? 'News' : 'Blog',
            'slug' => $article->slug,
            'url' => '/'.$article->type.'/'.$article->slug,
            'title' => $article->localizedTitle(),
            'excerpt' => $translation?->excerpt ?: \Illuminate\Support\Str::limit($plainText, 160),
            'content' => $rawContent,
            'image' => $article->featured_image_path,
            'is_featured' => (bool) $article->is_featured,
            'date' => $article->published_at?->format('M j, Y') ?? 'Recently',
            'author' => $article->creator?->username ?? 'GamersRival Desk',
            'read_time' => $readMinutes.' min read',
            'search_blob' => strtolower($article->localizedTitle().' '.($translation?->excerpt ?? '').' '.$plainText.' '.$article->type),
        ];
    })->values();
@endphp

<main class="landing-page-root gr-public-page min-h-screen bg-[#05080f] pt-10 text-zinc-100">
    <section
        class="mx-auto w-full max-w-6xl px-4 pb-20 sm:px-6 lg:px-8"
        x-data="{
            filter: 'all',
            search: '',
            activeId: {{ $feedItems->first()['id'] ?? 'null' }},
            items: @js($feedItems),
            get filteredItems() {
                const q = this.search.trim().toLowerCase();
                return this.items.filter(item => {
                    const typeMatch = this.filter === 'all' || item.type === this.filter;
                    const searchMatch = !q || item.search_blob.includes(q);
                    return typeMatch && searchMatch;
                });
            },
            get activeItem() {
                const list = this.filteredItems;
                return list.find(i => i.id === this.activeId) || list[0] || null;
            }
        }"
    >
        {{-- Top Command Header --}}
        <div class="mb-8 flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
            <div>
                <p class="gr-eyebrow"><span class="gr-slash" aria-hidden="true">//</span> GamersRival updates</p>
                <h1 class="mt-2 font-orbitron text-4xl font-black uppercase tracking-tight text-white sm:text-5xl">Blog &amp; News</h1>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-[#a8b6c9] sm:text-base">
                    Browse official platform dispatches, patch notes, and competitive guides. Filter by channel, search topics, or click any dispatch below to quick-read instantly.
                </p>
            </div>

            {{-- Direct Channel Cards --}}
            <div class="grid grid-cols-2 gap-3 sm:w-auto">
                <a href="{{ route('blog.index') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-[#c6ff3d]/30 bg-[#0b1322]/90 px-4 py-3 transition hover:-translate-y-0.5 hover:border-[#c6ff3d]">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#c6ff3d]/15 text-[#c6ff3d]">
                        <i data-lucide="book-open" class="h-4 w-4"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="block font-orbitron text-xs font-black uppercase text-white group-hover:text-[#c6ff3d]">Blog</span>
                        <span class="block truncate text-[11px] font-semibold text-[#a8b6c9]">Guides &amp; stories ↗</span>
                    </span>
                </a>

                <a href="{{ route('news.index') }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-[#22e6ff]/30 bg-[#0b1322]/90 px-4 py-3 transition hover:-translate-y-0.5 hover:border-[#22e6ff]">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#22e6ff]/15 text-[#22e6ff]">
                        <i data-lucide="radio" class="h-4 w-4"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="block font-orbitron text-xs font-black uppercase text-white group-hover:text-[#22e6ff]">News</span>
                        <span class="block truncate text-[11px] font-semibold text-[#a8b6c9]">Official updates ↗</span>
                    </span>
                </a>
            </div>
        </div>

        {{-- Interactive Filter & Search Bar --}}
        <div class="mb-6 flex flex-col gap-3 rounded-xl border border-white/10 bg-[#0a1220]/90 p-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-1.5" role="tablist" aria-label="Filter updates by category">
                <button
                    type="button"
                    @click="filter = 'all'"
                    :class="filter === 'all' ? 'border-[#c6ff3d] bg-[#c6ff3d] text-[#05080f]' : 'border-white/10 bg-[#05080f]/60 text-[#a8b6c9] hover:text-white'"
                    class="rounded-lg border px-3.5 py-2 text-xs font-extrabold uppercase tracking-wider transition"
                >
                    All Updates ({{ $feedItems->count() }})
                </button>
                <button
                    type="button"
                    @click="filter = 'news'"
                    :class="filter === 'news' ? 'border-[#22e6ff] bg-[#22e6ff] text-[#05080f]' : 'border-white/10 bg-[#05080f]/60 text-[#a8b6c9] hover:text-white'"
                    class="rounded-lg border px-3.5 py-2 text-xs font-extrabold uppercase tracking-wider transition"
                >
                    Official News ({{ $feedItems->where('type', 'news')->count() }})
                </button>
                <button
                    type="button"
                    @click="filter = 'blog'"
                    :class="filter === 'blog' ? 'border-[#c6ff3d] bg-[#c6ff3d] text-[#05080f]' : 'border-white/10 bg-[#05080f]/60 text-[#a8b6c9] hover:text-white'"
                    class="rounded-lg border px-3.5 py-2 text-xs font-extrabold uppercase tracking-wider transition"
                >
                    Community Blog ({{ $feedItems->where('type', 'blog')->count() }})
                </button>
            </div>

            <div class="relative w-full sm:w-72">
                <input
                    type="search"
                    x-model="search"
                    placeholder="Search guides, patch notes, news..."
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

        @if($feedItems->isNotEmpty())
            {{-- Interactive Split Dispatch Board --}}
            <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
                {{-- Left Column: Interactive Feed List --}}
                <div class="flex flex-col gap-2.5 lg:col-span-5">
                    <div class="flex items-center justify-between px-1 text-[11px] font-extrabold uppercase tracking-[0.16em] text-[#7f8ea3]">
                        <span>Dispatch Feed — Click to Preview</span>
                        <span x-text="filteredItems.length + ' articles'"></span>
                    </div>

                    <template x-for="item in filteredItems" :key="item.id">
                        <div
                            @click="activeId = item.id"
                            :class="activeItem && activeItem.id === item.id
                                ? 'border-[#c6ff3d]/70 bg-[#0e192d] shadow-[0_0_24px_rgba(198,255,61,0.1)]'
                                : 'border-white/10 bg-[#09101d]/85 hover:border-[#22e6ff]/40 hover:bg-[#0c1626]'"
                            class="group cursor-pointer rounded-xl border p-4 transition-all"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span
                                        :class="item.type === 'news'
                                            ? 'border-[#22e6ff]/40 bg-[#22e6ff]/10 text-[#22e6ff]'
                                            : 'border-[#c6ff3d]/40 bg-[#c6ff3d]/10 text-[#c6ff3d]'"
                                        class="rounded-full border px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-widest"
                                        x-text="item.badge"
                                    ></span>
                                    <span x-show="item.is_featured" class="rounded-full border border-[#ffd23f]/40 bg-[#ffd23f]/15 px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-widest text-[#ffd23f]">Featured</span>
                                </div>
                                <span class="text-[11px] font-semibold text-[#7f8ea3]" x-text="item.date"></span>
                            </div>

                            <h2
                                class="mt-2.5 font-orbitron text-base font-black leading-snug text-white transition-colors group-hover:text-[#c6ff3d]"
                                x-text="item.title"
                            ></h2>

                            <p class="mt-1.5 line-clamp-2 text-xs leading-5 text-[#a8b6c9]" x-text="item.excerpt"></p>

                            <div class="mt-3 flex items-center justify-between border-t border-white/10 pt-2.5 text-[11px] font-bold">
                                <span class="text-[#7f8ea3]" x-text="item.read_time"></span>
                                <a
                                    :href="item.url"
                                    wire:navigate
                                    @click.stop
                                    class="inline-flex items-center gap-1 font-extrabold uppercase tracking-wider text-[#22e6ff] hover:text-[#c6ff3d]"
                                >
                                    <span>Full Page</span>
                                    <span aria-hidden="true">↗</span>
                                </a>
                            </div>
                        </div>
                    </template>

                    <div x-show="filteredItems.length === 0" class="landing-card gr-surface-card p-8 text-center text-sm text-[#a8b6c9]">
                        No articles match your current filter. Try clearing your search.
                    </div>
                </div>

                {{-- Right Column: Interactive Quick-Reader Preview --}}
                <div class="lg:sticky lg:top-24 lg:col-span-7">
                    <template x-if="activeItem">
                        <article class="landing-card gr-surface-card overflow-hidden border border-[#22e6ff]/30 bg-[#0a1220]/95 shadow-2xl">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 bg-[#070d18] px-6 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <span class="h-2 w-2 rounded-full bg-[#c6ff3d]"></span>
                                    <span class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-[#c6ff3d]">Interactive Quick-Reader</span>
                                    <span class="text-white/20">|</span>
                                    <span class="text-xs font-bold text-[#a8b6c9]" x-text="activeItem.label"></span>
                                </div>
                                <a
                                    :href="activeItem.url"
                                    wire:navigate
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-[#c6ff3d]/50 bg-[#c6ff3d]/10 px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-wider text-[#c6ff3d] transition hover:bg-[#c6ff3d] hover:text-[#05080f]"
                                >
                                    <span>Open Dedicated Article</span>
                                    <span aria-hidden="true">↗</span>
                                </a>
                            </div>

                            <template x-if="activeItem.image">
                                <div class="aspect-[21/9] w-full overflow-hidden border-b border-white/10 bg-[#05080f]">
                                    <img :src="activeItem.image" :alt="activeItem.title" class="h-full w-full object-cover">
                                </div>
                            </template>

                            <div class="p-6 sm:p-8">
                                <div class="flex flex-wrap items-center gap-3 text-xs font-extrabold uppercase tracking-widest text-[#22e6ff]">
                                    <span x-text="activeItem.date"></span>
                                    <span class="text-white/20">/</span>
                                    <span class="text-[#c6ff3d]" x-text="activeItem.read_time"></span>
                                    <span class="text-white/20">/</span>
                                    <span class="text-[#a8b6c9]" x-text="activeItem.author"></span>
                                </div>

                                <h2 class="mt-3 font-orbitron text-2xl font-black uppercase leading-tight text-white sm:text-3xl" x-text="activeItem.title"></h2>

                                <p class="mt-3 rounded-xl border border-white/10 bg-[#05080f]/60 p-4 text-sm font-medium leading-6 text-[#d6e1f0]" x-text="activeItem.excerpt"></p>

                                <div
                                    class="prose prose-invert prose-zinc mt-6 max-h-[380px] max-w-none overflow-y-auto pr-2 text-sm leading-7 prose-headings:font-orbitron prose-a:text-[#22e6ff]"
                                    x-html="activeItem.content"
                                ></div>

                                <div class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-white/10 pt-5">
                                    <span class="text-xs font-semibold text-[#7f8ea3]">Want a distraction-free full page view or shareable link?</span>
                                    <a
                                        :href="activeItem.url"
                                        wire:navigate
                                        class="gr-btn-lime inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-extrabold uppercase tracking-wider"
                                    >
                                        <span>Read Full Article</span>
                                        <span aria-hidden="true">→</span>
                                    </a>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>
            </div>
        @else
            {{-- Fallback when no articles exist in DB --}}
            <div class="grid gap-5 md:grid-cols-2">
                <a href="{{ route('blog.index') }}" wire:navigate class="landing-card gr-surface-card group relative block overflow-hidden p-6 transition-all hover:-translate-y-1 sm:p-8">
                    <p class="relative text-[11px] font-extrabold uppercase tracking-[0.16em] text-[#c6ff3d]">Explore</p>
                    <h2 class="relative mt-2 font-orbitron text-2xl font-black uppercase text-white">Blog</h2>
                    <p class="relative mt-3 text-sm leading-6 text-[#a8b6c9]">Competitive guides, platform stories, and behind-the-scenes notes from GamersRival.</p>
                    <p class="relative mt-6 inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-[#c6ff3d]">Read the blog <span aria-hidden="true">→</span></p>
                </a>

                <a href="{{ route('news.index') }}" wire:navigate class="landing-card gr-surface-card group relative block overflow-hidden p-6 transition-all hover:-translate-y-1 sm:p-8">
                    <p class="relative text-[11px] font-extrabold uppercase tracking-[0.16em] text-[#22e6ff]">Official</p>
                    <h2 class="relative mt-2 font-orbitron text-2xl font-black uppercase text-white">News</h2>
                    <p class="relative mt-3 text-sm leading-6 text-[#a8b6c9]">Official announcements, release notes, and operational updates for the platform.</p>
                    <p class="relative mt-6 inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-[#22e6ff]">View the news <span aria-hidden="true">→</span></p>
                </a>
            </div>
        @endif
    </section>
</main>
