<main class="landing-page-root min-h-screen bg-[#050311] pt-28 text-zinc-100">
    <section class="mx-auto w-full max-w-6xl px-4 pb-20 sm:px-6 lg:px-8">
        <div class="mb-10">
            <p class="landing-section-kicker">PlayerSaloons updates</p>
            <h1 class="mt-3 font-orbitron text-4xl font-black uppercase text-white sm:text-5xl">Blog &amp; News</h1>
            <p class="mt-4 max-w-3xl text-sm leading-7 text-zinc-400 sm:text-base">
                Choose what you want to explore: guides and stories from the community, or official PlayerSaloons announcements.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <a href="{{ route('blog.index') }}" wire:navigate class="landing-card group relative block overflow-hidden p-6 transition-transform hover:-translate-y-1 sm:p-8">
                <div class="pointer-events-none absolute -right-12 -top-12 h-40 w-40 rounded-full bg-cyan-400/10 blur-3xl transition-opacity group-hover:bg-cyan-400/20"></div>
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-cyan-400/20 bg-cyan-400/10 text-cyan-300">
                        <i data-lucide="book-open" class="h-6 w-6"></i>
                    </div>
                    <i data-lucide="arrow-up-right" class="h-5 w-5 shrink-0 text-zinc-500 transition-colors group-hover:text-cyan-300"></i>
                </div>
                <p class="relative mt-8 text-[10px] font-black uppercase tracking-widest text-cyan-300">Explore</p>
                <h2 class="relative mt-2 text-2xl font-black text-white">Blog</h2>
                <p class="relative mt-4 text-sm leading-6 text-zinc-400">Competitive guides, platform stories, and behind-the-scenes notes from PlayerSaloons.</p>
                <p class="relative mt-6 text-[10px] font-black uppercase tracking-widest text-zinc-500">Read the blog →</p>
            </a>

            <a href="{{ route('news.index') }}" wire:navigate class="landing-card group relative block overflow-hidden p-6 transition-transform hover:-translate-y-1 sm:p-8">
                <div class="pointer-events-none absolute -right-12 -top-12 h-40 w-40 rounded-full bg-violet-500/10 blur-3xl transition-opacity group-hover:bg-violet-500/20"></div>
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-violet-400/20 bg-violet-400/10 text-violet-300">
                        <i data-lucide="radio" class="h-6 w-6"></i>
                    </div>
                    <i data-lucide="arrow-up-right" class="h-5 w-5 shrink-0 text-zinc-500 transition-colors group-hover:text-violet-300"></i>
                </div>
                <p class="relative mt-8 text-[10px] font-black uppercase tracking-widest text-violet-300">Official</p>
                <h2 class="relative mt-2 text-2xl font-black text-white">News</h2>
                <p class="relative mt-4 text-sm leading-6 text-zinc-400">Official announcements, release notes, and operational updates for the platform.</p>
                <p class="relative mt-6 text-[10px] font-black uppercase tracking-widest text-zinc-500">View the news →</p>
            </a>
        </div>
    </section>
</main>
