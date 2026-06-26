@php
    $hero = $sections->get('hero');
    $gamesSection = $sections->get('games');
    $howSection = $sections->get('how_it_works');
    $statsSection = $sections->get('stats');
    $topPlayersSection = $sections->get('top_players');
    $featuresSection = $sections->get('features');
    $reviewsSection = $sections->get('reviews');
    $footer = $sections->get('footer');
@endphp

<div class="relative">
    <section class="relative flex min-h-[calc(100vh-72px)] items-center overflow-hidden px-4 py-20 text-center sm:px-6 lg:px-8">
        @if($hero?->media_path)
            <video class="absolute inset-0 h-full w-full object-cover" autoplay muted loop playsinline preload="metadata">
                <source src="{{ $hero->media_path }}" type="video/mp4">
            </video>
        @endif
        <div class="absolute inset-0 bg-zinc-950/75"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(34,211,238,0.18),transparent_34%),linear-gradient(to_bottom,rgba(9,9,11,0.25),#09090b)]"></div>

        <div class="relative z-10 mx-auto flex w-full max-w-5xl flex-col items-center">
            @if($hero?->subtitle)
                <div class="mb-8 inline-flex items-center space-x-2 rounded-full border border-cyan-400/20 bg-zinc-950/50 px-4 py-1.5 backdrop-blur-md">
                    <span class="flex h-2 w-2 rounded-full bg-cyan-400"></span>
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] text-cyan-300">{{ $hero->subtitle }}</span>
                </div>
            @endif

            <h1 class="mb-8 text-5xl font-black leading-[0.9] tracking-normal text-white drop-shadow-2xl sm:text-6xl md:text-8xl lg:text-9xl">
                {{ $hero?->title ?? 'PLAY. WIN. CASH OUT.' }}
            </h1>

            @if($hero?->body)
                <p class="mb-12 max-w-2xl text-base font-medium leading-relaxed text-zinc-300 md:text-xl">
                    {{ $hero->body }}
                </p>
            @endif

            <div class="flex w-full flex-col items-center justify-center gap-4 sm:w-auto sm:flex-row">
                @if($hero?->cta_label && $hero?->cta_url)
                    <a href="{{ $hero->cta_url }}" class="flex w-full items-center justify-center space-x-3 rounded-2xl bg-cyan-500 px-7 py-4 text-xs font-black uppercase tracking-[0.18em] text-zinc-950 shadow-[0_15px_35px_-10px_rgba(34,211,238,0.7)] transition hover:bg-cyan-300 sm:w-64">
                        <i data-lucide="trophy" class="h-5 w-5"></i>
                        <span>{{ $hero->cta_label }}</span>
                    </a>
                @endif
                <a href="/register" class="flex w-full items-center justify-center rounded-2xl border border-white/15 bg-white/10 px-7 py-4 text-xs font-black uppercase tracking-[0.18em] text-white backdrop-blur-md transition hover:bg-white/15 sm:w-64">
                    Create Account
                </a>
            </div>
        </div>
    </section>

    <main class="relative z-10 bg-zinc-950">
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="mb-10 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-cyan-400">{{ $gamesSection?->subtitle }}</p>
                    <h2 class="mt-2 text-3xl font-black uppercase tracking-normal text-white md:text-5xl">{{ $gamesSection?->title }}</h2>
                </div>
                <p class="max-w-xl text-sm leading-6 text-zinc-400">{{ $gamesSection?->body }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @forelse($games as $game)
                    @php($translation = $game->translations->where('locale', 'en')->first())
                    <article class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-5">
                        <div class="mb-5 flex h-11 w-11 items-center justify-center rounded bg-cyan-400/10 text-cyan-300">
                            <i data-lucide="gamepad-2" class="h-5 w-5"></i>
                        </div>
                        <h3 class="text-lg font-black text-white">{{ $translation?->name ?? $game->slug }}</h3>
                        <p class="mt-3 line-clamp-3 text-sm leading-6 text-zinc-500">{{ $translation?->description ?? 'Competitive events available soon.' }}</p>
                    </article>
                @empty
                    <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-6 text-sm text-zinc-500">No active games are available yet.</div>
                @endforelse
            </div>
        </section>

        <section class="border-y border-zinc-900 bg-zinc-900/30 px-4 py-20 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="mb-10 text-center">
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-fuchsia-400">{{ $howSection?->subtitle }}</p>
                    <h2 class="mt-2 text-3xl font-black uppercase tracking-normal text-white md:text-5xl">{{ $howSection?->title }}</h2>
                </div>
                <div class="grid gap-4 md:grid-cols-4">
                    @foreach($howSection?->activeItems ?? [] as $item)
                        <article class="rounded-lg border border-zinc-800 bg-zinc-950/80 p-5">
                            <i data-lucide="{{ $item->icon ?: 'circle' }}" class="mb-5 h-6 w-6 text-fuchsia-300"></i>
                            <h3 class="text-sm font-black uppercase tracking-widest text-white">{{ $item->title }}</h3>
                            <p class="mt-3 text-sm leading-6 text-zinc-500">{{ $item->body }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="mb-10 text-center">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-400">{{ $statsSection?->subtitle }}</p>
                <h2 class="mt-2 text-3xl font-black uppercase tracking-normal text-white md:text-5xl">{{ $statsSection?->title }}</h2>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($stats as $stat)
                    <article class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-6">
                        <div class="mb-5 flex items-center justify-between">
                            <i data-lucide="{{ $stat['icon'] }}" class="h-6 w-6 text-emerald-300"></i>
                            <span class="text-[10px] font-black uppercase tracking-widest text-zinc-600">Live</span>
                        </div>
                        <p class="text-3xl font-black text-white">{{ $stat['value'] }}</p>
                        <p class="mt-2 text-[10px] font-black uppercase tracking-[0.2em] text-zinc-500">{{ $stat['label'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="border-y border-zinc-900 bg-zinc-900/30 px-4 py-20 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="mb-10 text-center">
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-violet-400">{{ $topPlayersSection?->subtitle }}</p>
                    <h2 class="mt-2 text-3xl font-black uppercase tracking-normal text-white md:text-5xl">{{ $topPlayersSection?->title }}</h2>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    @forelse($topPlayers as $player)
                        <article class="rounded-lg border border-zinc-800 bg-zinc-950/80 p-6">
                            <div class="mb-6 flex items-center justify-between">
                                <span class="text-4xl font-black text-violet-300">#{{ $player['rank'] }}</span>
                                <span class="flex h-12 w-12 items-center justify-center rounded bg-violet-500/10 text-sm font-black text-violet-200">{{ $player['avatar'] }}</span>
                            </div>
                            <h3 class="text-xl font-black text-white">{{ $player['name'] }}</h3>
                            <div class="mt-5 grid grid-cols-3 gap-3 text-center text-xs">
                                <div><span class="block font-black text-white">{{ $player['wins'] }}</span><span class="text-zinc-600">Wins</span></div>
                                <div><span class="block font-black text-white">{{ $player['winrate'] }}</span><span class="text-zinc-600">Rate</span></div>
                                <div><span class="block font-black text-white">{{ $player['cash'] }}</span><span class="text-zinc-600">Cash</span></div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-lg border border-zinc-800 bg-zinc-950/80 p-6 text-sm text-zinc-500 md:col-span-3">No weekly leaderboard results yet.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="mb-10 text-center">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-cyan-400">{{ $featuresSection?->subtitle }}</p>
                <h2 class="mt-2 text-3xl font-black uppercase tracking-normal text-white md:text-5xl">{{ $featuresSection?->title }}</h2>
            </div>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                @foreach($featuresSection?->activeItems ?? [] as $item)
                    <a href="{{ $item->url ?: '#' }}" class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-5 transition hover:border-cyan-400/40">
                        <i data-lucide="{{ $item->icon ?: 'sparkles' }}" class="mb-5 h-6 w-6 text-cyan-300"></i>
                        <h3 class="text-sm font-black uppercase tracking-widest text-white">{{ $item->title }}</h3>
                        <p class="mt-3 text-sm leading-6 text-zinc-500">{{ $item->body }}</p>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="border-y border-zinc-900 bg-zinc-900/30 px-4 py-20 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="mb-10 text-center">
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-fuchsia-400">{{ $reviewsSection?->subtitle }}</p>
                    <h2 class="mt-2 text-3xl font-black uppercase tracking-normal text-white md:text-5xl">{{ $reviewsSection?->title }}</h2>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach($reviewsSection?->activeItems ?? [] as $item)
                        <article class="rounded-lg border border-zinc-800 bg-zinc-950/80 p-6">
                            <i data-lucide="{{ $item->icon ?: 'quote' }}" class="mb-5 h-6 w-6 text-fuchsia-300"></i>
                            <h3 class="text-lg font-black text-white">{{ $item->title }}</h3>
                            <p class="mt-4 text-sm leading-6 text-zinc-400">{{ $item->body }}</p>
                            <p class="mt-6 text-[10px] font-black uppercase tracking-[0.2em] text-zinc-600">{{ $item->subtitle }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    </main>

    <footer class="relative z-10 border-t border-zinc-900/50 bg-zinc-950 px-4 py-10 text-center">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 md:flex-row">
            <div class="flex items-center gap-3 opacity-70">
                <img src="/playersaloons_logo.webp" alt="Logo" class="h-6 w-auto grayscale">
                <span class="text-xs font-black uppercase tracking-widest text-zinc-400">{{ $footer?->title ?? 'PlayerSaloons' }}</span>
            </div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-700">
                &copy; {{ date('Y') }} {{ $footer?->body ?? 'ALL RIGHTS RESERVED.' }}
            </p>
            <div class="flex gap-8 text-[10px] font-black uppercase tracking-widest text-zinc-600">
                @foreach($footer?->activeItems ?? [] as $item)
                    <a href="{{ $item->url ?: '#' }}" class="transition-colors hover:text-cyan-400">{{ $item->label ?: $item->title }}</a>
                @endforeach
            </div>
        </div>
    </footer>
</div>
