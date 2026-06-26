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

<div class="landing-page-root relative">

    {{-- ═══════════════════════════════════════════════
         HERO SECTION — Full-viewport video hero
    ═══════════════════════════════════════════════ --}}
    <section class="landing-hero relative flex min-h-[100svh] items-center justify-center overflow-hidden text-center">

        {{-- Video Background --}}
        @if($hero?->media_path)
            <video class="absolute inset-0 h-full w-full object-cover scale-105" autoplay muted loop playsinline preload="metadata">
                <source src="{{ $hero->media_path }}" type="video/mp4">
            </video>
        @endif

        {{-- Overlay layers --}}
        <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-[#050311]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_80%_50%_at_50%_-20%,rgba(120,80,255,0.35),transparent)]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_50%,rgba(34,211,238,0.08),transparent_60%)]"></div>

        {{-- Animated grid lines --}}
        <div class="landing-grid-overlay absolute inset-0 opacity-20"></div>

        {{-- Hero Content --}}
        <div class="relative z-10 mx-auto flex w-full max-w-6xl flex-col items-center px-4 pt-24 sm:px-6 sm:pt-20 lg:px-8">

            {{-- Badge --}}
            @if($hero?->subtitle)
                <div class="landing-fade-in mb-6 inline-flex max-w-full items-center gap-2 rounded-full border border-cyan-400/25 bg-black/40 px-4 py-2 backdrop-blur-md sm:mb-8 sm:px-5">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-cyan-400 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-cyan-400"></span>
                    </span>
                    <span class="truncate text-[9px] font-black uppercase tracking-[0.18em] text-cyan-300 sm:text-[10px] sm:tracking-[0.25em]">{{ $hero->subtitle }}</span>
                </div>
            @endif

            {{-- Main Heading --}}
            <h1 class="landing-fade-in landing-fade-delay-1 mb-5 max-w-full font-orbitron text-[3rem] font-black leading-[0.9] tracking-normal text-white drop-shadow-2xl min-[390px]:text-[3.5rem] sm:mb-6 sm:text-7xl md:text-8xl lg:text-[7rem] xl:text-[8.5rem]">
                @php
                    $titleText = $hero?->title ?? 'PLAY. WIN. CASH OUT.';
                    $parts = explode('.', rtrim($titleText, '.'));
                @endphp
                @if(count($parts) >= 3)
                    {{ trim($parts[0]) }}.<br>
                    {{ trim($parts[1]) }}.<br>
                    <span class="landing-gradient-text">{{ trim($parts[2]) }}.</span>
                @else
                    {!! nl2br(e($titleText)) !!}
                @endif
            </h1>

            {{-- Subtext --}}
            @if($hero?->body)
                <p class="landing-fade-in landing-fade-delay-2 mb-9 max-w-[34rem] text-sm font-medium leading-7 text-zinc-300/80 sm:mb-12 sm:text-lg md:text-xl">
                    {{ $hero->body }}
                </p>
            @endif

            {{-- CTAs --}}
            <div class="landing-fade-in landing-fade-delay-3 flex w-full max-w-sm flex-col items-center justify-center gap-3 sm:w-auto sm:max-w-none sm:flex-row sm:gap-4">
                @if($hero?->cta_label && $hero?->cta_url)
                    <a href="{{ $hero->cta_url }}"
                        class="landing-cta-primary group relative flex w-full items-center justify-center gap-3 overflow-hidden rounded-2xl px-6 py-4 text-[11px] font-black uppercase tracking-[0.14em] text-white shadow-[0_0_40px_rgba(120,80,255,0.5)] transition-all duration-300 hover:shadow-[0_0_60px_rgba(120,80,255,0.7)] hover:scale-[1.02] sm:w-64 sm:px-8 sm:text-xs sm:tracking-[0.2em]">
                        <span class="absolute inset-0 bg-gradient-to-r from-violet-600 via-purple-600 to-cyan-600 transition-all duration-300"></span>
                        <span class="absolute inset-0 bg-gradient-to-r from-violet-500 via-purple-500 to-cyan-500 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></span>
                        <i data-lucide="trophy" class="relative h-5 w-5"></i>
                        <span class="relative">{{ $hero->cta_label }}</span>
                    </a>
                @endif
                <a href="/register"
                    class="group relative flex w-full items-center justify-center gap-3 overflow-hidden rounded-2xl border border-white/20 bg-white/5 px-6 py-4 text-[11px] font-black uppercase tracking-[0.14em] text-white backdrop-blur-md transition-all duration-300 hover:border-white/40 hover:bg-white/10 hover:scale-[1.02] sm:w-64 sm:px-8 sm:text-xs sm:tracking-[0.2em]">
                    <i data-lucide="user-plus" class="h-5 w-5 transition-transform duration-300 group-hover:scale-110"></i>
                    <span>Create Account</span>
                </a>
            </div>

            {{-- Scroll hint --}}
            <div class="landing-fade-in landing-fade-delay-4 mt-12 flex flex-col items-center gap-2 opacity-40 sm:mt-20">
                <span class="text-[9px] font-black uppercase tracking-[0.3em] text-zinc-400">Scroll</span>
                <div class="landing-scroll-arrow h-6 w-px bg-gradient-to-b from-zinc-400 to-transparent"></div>
            </div>
        </div>

        {{-- Bottom gradient blend --}}
        <div class="absolute bottom-0 left-0 right-0 h-40 bg-gradient-to-t from-[#050311] to-transparent"></div>
    </section>


    {{-- ═══════════════════════════════════════════════
         MAIN CONTENT
    ═══════════════════════════════════════════════ --}}
    <main class="landing-main-pattern relative z-10 bg-[#050311]">

        {{-- Decorative top glow — uses .landing-top-glow to prevent horizontal overflow --}}
        <div class="landing-top-glow pointer-events-none bg-gradient-to-r from-transparent via-violet-500/60 to-transparent"></div>

        {{-- ─── GAMES SECTION ─── --}}
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
            <div class="mb-8 flex flex-col gap-4 sm:mb-14 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="landing-section-kicker text-cyan-400">{{ $gamesSection?->subtitle }}</p>
                    <h2 class="landing-section-title mt-2 text-white">{{ $gamesSection?->title }}</h2>
                </div>
                <p class="max-w-lg text-sm leading-6 text-zinc-500 sm:leading-7">{{ $gamesSection?->body }}</p>
            </div>

            <div class="landing-games-scroll -mx-4 flex snap-x gap-4 overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
                @forelse($games as $game)
                    @php($translation = $game->translations->where('locale', 'en')->first())
                    <article class="landing-card group relative min-h-[300px] w-[82vw] max-w-[340px] shrink-0 snap-start overflow-hidden rounded-2xl border border-zinc-800/60 bg-zinc-900/30 transition-all duration-500 hover:-translate-y-1 hover:border-cyan-500/40 hover:shadow-[0_20px_60px_-15px_rgba(34,211,238,0.18)] sm:min-h-[330px] sm:w-[340px]">
                        <div class="relative h-36 overflow-hidden bg-zinc-950 sm:h-40">
                            @if($game->banner_path)
                                <img src="{{ $game->banner_path }}" alt="{{ $translation?->name ?? $game->slug }} banner" class="h-full w-full object-cover transition duration-700 group-hover:scale-105">
                            @else
                                <div class="landing-game-card-pattern flex h-full w-full items-center justify-center">
                                    <i data-lucide="gamepad-2" class="h-12 w-12 text-cyan-300/60"></i>
                                </div>
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-zinc-950 via-zinc-950/25 to-transparent"></div>
                            <div class="absolute left-4 top-4 rounded-full border border-cyan-400/20 bg-zinc-950/60 px-3 py-1 text-[9px] font-black uppercase tracking-widest text-cyan-300 backdrop-blur">
                                Active
                            </div>
                        </div>
                        <div class="relative p-5 sm:p-6">
                            <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-cyan-500/10 blur-2xl transition-all duration-500 group-hover:bg-cyan-500/20"></div>
                            <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500/20 to-violet-500/20 border border-cyan-500/20 text-cyan-300 transition-all duration-300 group-hover:border-cyan-400/40">
                                <i data-lucide="gamepad-2" class="h-5 w-5"></i>
                            </div>
                            <h3 class="relative text-lg font-black text-white">{{ $translation?->name ?? $game->slug }}</h3>
                            <p class="relative mt-3 line-clamp-3 text-sm leading-6 text-zinc-500">{{ $translation?->description ?? 'Competitive events available soon.' }}</p>
                        </div>
                    </article>
                @empty
                    <div class="w-full rounded-2xl border border-zinc-800 bg-zinc-900/30 p-8 text-center text-sm text-zinc-600">
                        <i data-lucide="gamepad-2" class="mx-auto mb-3 h-8 w-8 opacity-40"></i>
                        <p>No active games available yet.</p>
                    </div>
                @endforelse
            </div>
            @if($games->isNotEmpty())
                <p class="mt-4 text-center text-[10px] font-black uppercase tracking-[0.2em] text-zinc-700 sm:hidden">Swipe to browse games</p>
            @endif
        </section>


        {{-- ─── HOW IT WORKS ─── --}}
        <section class="landing-section-overflow-clip relative border-y border-zinc-800/40 py-16 sm:py-24">
            {{-- BG accent --}}
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_60%_60%_at_50%_50%,rgba(168,85,247,0.06),transparent)]"></div>
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-9 text-center sm:mb-14">
                    <p class="landing-section-kicker text-fuchsia-400">{{ $howSection?->subtitle }}</p>
                    <h2 class="landing-section-title mt-2 text-white">{{ $howSection?->title }}</h2>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-4">
                    @foreach($howSection?->activeItems ?? [] as $index => $item)
                        <article class="landing-card group relative overflow-hidden rounded-2xl border border-zinc-800/60 bg-zinc-900/20 p-5 transition-all duration-500 hover:-translate-y-1 hover:border-fuchsia-500/40 hover:shadow-[0_20px_60px_-15px_rgba(192,38,211,0.15)] sm:p-6">
                            <div class="absolute -bottom-6 -right-6 h-20 w-20 rounded-full bg-fuchsia-500/8 blur-2xl transition-all duration-500 group-hover:bg-fuchsia-500/15"></div>
                            {{-- Step number --}}
                            <div class="mb-4 flex items-center gap-3">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-fuchsia-500/15 text-xs font-black text-fuchsia-300 border border-fuchsia-500/25">
                                    {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                </span>
                                <i data-lucide="{{ $item->icon ?: 'circle' }}" class="h-5 w-5 text-fuchsia-300"></i>
                            </div>
                            <h3 class="relative text-sm font-black uppercase tracking-widest text-white">{{ $item->title }}</h3>
                            <p class="relative mt-3 text-sm leading-6 text-zinc-500">{{ $item->body }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>


        {{-- ─── STATS ─── --}}
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
            <div class="mb-9 text-center sm:mb-14">
                <p class="landing-section-kicker text-emerald-400">{{ $statsSection?->subtitle }}</p>
                <h2 class="landing-section-title mt-2 text-white">{{ $statsSection?->title }}</h2>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($stats as $stat)
                    <article class="landing-stat-card group relative overflow-hidden rounded-2xl border border-zinc-800/60 bg-zinc-900/30 p-5 text-center transition-all duration-500 hover:-translate-y-1 hover:border-emerald-500/40 hover:shadow-[0_20px_60px_-15px_rgba(16,185,129,0.2)] sm:p-6">
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"></div>
                        <div class="relative mb-4 flex justify-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 transition-all duration-300 group-hover:border-emerald-400/40 group-hover:bg-emerald-500/20">
                                <i data-lucide="{{ $stat['icon'] }}" class="h-6 w-6"></i>
                            </div>
                        </div>
                        <p class="relative break-words font-orbitron text-3xl font-black text-white sm:text-4xl">{{ $stat['value'] }}</p>
                        <p class="relative mt-2 text-[10px] font-black uppercase tracking-[0.22em] text-zinc-500">{{ $stat['label'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>


        {{-- ─── TOP PLAYERS ─── --}}
        <section class="landing-section-overflow-clip relative border-y border-zinc-800/40 py-16 sm:py-24">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_60%_60%_at_50%_50%,rgba(124,77,255,0.07),transparent)]"></div>
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-9 text-center sm:mb-14">
                    <p class="landing-section-kicker text-violet-400">{{ $topPlayersSection?->subtitle }}</p>
                    <h2 class="landing-section-title mt-2 text-white">{{ $topPlayersSection?->title }}</h2>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                    @forelse($topPlayers as $player)
                        <article class="landing-card group relative overflow-hidden rounded-2xl border border-zinc-800/60 bg-zinc-900/30 p-6 transition-all duration-500 hover:-translate-y-1 hover:border-violet-500/50 hover:shadow-[0_20px_60px_-15px_rgba(124,77,255,0.25)]">
                            <div class="absolute -top-10 -left-10 h-28 w-28 rounded-full bg-violet-600/10 blur-3xl transition-all duration-500 group-hover:bg-violet-600/20"></div>
                            <div class="relative mb-6 flex items-center justify-between">
                                <span class="font-orbitron text-5xl font-black text-violet-300/40 leading-none">#{{ $player['rank'] }}</span>
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl border border-violet-500/25 bg-violet-500/10 font-black text-lg text-violet-200 transition-all duration-300 group-hover:border-violet-400/40 group-hover:bg-violet-500/20">
                                    {{ $player['avatar'] }}
                                </div>
                            </div>
                            <h3 class="relative break-words text-xl font-black text-white">{{ $player['name'] }}</h3>
                            <div class="relative mt-5 grid grid-cols-3 gap-2 rounded-xl border border-zinc-800/60 bg-zinc-950/50 p-3 text-center text-xs">
                                <div class="border-r border-zinc-800/60">
                                    <span class="block font-black text-white">{{ $player['wins'] }}</span>
                                    <span class="text-[10px] uppercase tracking-widest text-zinc-600">Wins</span>
                                </div>
                                <div class="border-r border-zinc-800/60">
                                    <span class="block font-black text-white">{{ $player['winrate'] }}</span>
                                    <span class="text-[10px] uppercase tracking-widest text-zinc-600">Rate</span>
                                </div>
                                <div>
                                    <span class="block font-black text-emerald-300">{{ $player['cash'] }}</span>
                                    <span class="text-[10px] uppercase tracking-widest text-zinc-600">Cash</span>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="col-span-full rounded-2xl border border-zinc-800 bg-zinc-900/30 p-8 text-center text-sm text-zinc-600">
                            <i data-lucide="award" class="mx-auto mb-3 h-8 w-8 opacity-40"></i>
                            <p>No weekly leaderboard results yet.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>


        {{-- ─── FEATURES ─── --}}
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
            <div class="mb-9 text-center sm:mb-14">
                <p class="landing-section-kicker text-cyan-400">{{ $featuresSection?->subtitle }}</p>
                <h2 class="landing-section-title mt-2 text-white">{{ $featuresSection?->title }}</h2>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($featuresSection?->activeItems ?? [] as $item)
                    <a href="{{ $item->url ?: '#' }}"
                        class="landing-card group relative flex flex-col overflow-hidden rounded-2xl border border-zinc-800/60 bg-zinc-900/30 p-5 transition-all duration-500 hover:-translate-y-1 hover:border-cyan-500/40 hover:shadow-[0_20px_60px_-15px_rgba(34,211,238,0.15)] sm:p-6">
                        <div class="absolute inset-0 bg-gradient-to-br from-cyan-500/5 via-transparent to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"></div>
                        <div class="relative mb-5 flex h-12 w-12 items-center justify-center rounded-xl border border-cyan-500/20 bg-cyan-500/10 text-cyan-300 transition-all duration-300 group-hover:border-cyan-400/40 group-hover:bg-cyan-500/20">
                            <i data-lucide="{{ $item->icon ?: 'sparkles' }}" class="h-6 w-6"></i>
                        </div>
                        <h3 class="relative text-sm font-black uppercase tracking-widest text-white">{{ $item->title }}</h3>
                        <p class="relative mt-3 flex-1 text-sm leading-6 text-zinc-500">{{ $item->body }}</p>
                        <div class="relative mt-5 flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-cyan-500 opacity-0 transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-1">
                            <span>Learn More</span>
                            <i data-lucide="arrow-right" class="h-3 w-3"></i>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>


        {{-- ─── REVIEWS ─── --}}
        <section class="landing-section-overflow-clip relative border-t border-zinc-800/40 py-16 sm:py-24">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_60%_50%_at_50%_50%,rgba(192,38,211,0.06),transparent)]"></div>
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-9 text-center sm:mb-14">
                    <p class="landing-section-kicker text-fuchsia-400">{{ $reviewsSection?->subtitle }}</p>
                    <h2 class="landing-section-title mt-2 text-white">{{ $reviewsSection?->title }}</h2>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                    @foreach($reviewsSection?->activeItems ?? [] as $item)
                        <article class="landing-card group relative overflow-hidden rounded-2xl border border-zinc-800/60 bg-zinc-900/30 p-5 transition-all duration-500 hover:-translate-y-1 hover:border-fuchsia-500/40 hover:shadow-[0_20px_60px_-15px_rgba(192,38,211,0.15)] sm:p-6">
                            <div class="absolute -top-8 -right-8 h-20 w-20 rounded-full bg-fuchsia-500/10 blur-2xl transition-all duration-500 group-hover:bg-fuchsia-500/20"></div>
                            {{-- Quote icon --}}
                            <div class="relative mb-5 flex h-10 w-10 items-center justify-center rounded-lg border border-fuchsia-500/20 bg-fuchsia-500/10 text-fuchsia-300">
                                <i data-lucide="{{ $item->icon ?: 'quote' }}" class="h-5 w-5"></i>
                            </div>
                            <h3 class="relative break-words text-base font-black text-white">{{ $item->title }}</h3>
                            <p class="relative mt-4 text-sm leading-6 text-zinc-400">{{ $item->body }}</p>
                            <div class="relative mt-6 flex items-center gap-3 border-t border-zinc-800/60 pt-4">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-fuchsia-500/10 text-xs font-black text-fuchsia-300">
                                    {{ strtoupper(substr($item->subtitle ?? 'P', 0, 1)) }}
                                </span>
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-500">{{ $item->subtitle }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>


        {{-- ─── CTA BANNER ─── --}}
        <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 sm:pb-24 lg:px-8">
            <div class="relative overflow-hidden rounded-3xl border border-violet-500/25 bg-gradient-to-br from-violet-900/30 via-purple-900/20 to-cyan-900/20 p-6 text-center shadow-[0_0_80px_-20px_rgba(124,77,255,0.4)] sm:p-16">
                {{-- Decorative orbs --}}
                <div class="absolute -top-16 -left-16 h-48 w-48 rounded-full bg-violet-600/20 blur-3xl"></div>
                <div class="absolute -bottom-16 -right-16 h-48 w-48 rounded-full bg-cyan-600/15 blur-3xl"></div>
                <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-violet-500/50 to-transparent"></div>

                <p class="relative landing-section-kicker text-violet-400 mb-4">Ready to compete?</p>
                <h2 class="relative font-orbitron text-2xl font-black uppercase tracking-normal text-white sm:text-5xl md:text-6xl">
                    Join the <span class="landing-gradient-text">Arena</span>
                </h2>
                <p class="relative mx-auto mt-5 max-w-xl text-sm leading-6 text-zinc-400 sm:mt-6 sm:text-base">
                    Create your account today and step into the battleground. Your first tournament awaits.
                </p>
                <div class="relative mt-8 flex flex-col items-center justify-center gap-3 sm:mt-10 sm:flex-row sm:gap-4">
                    <a href="/register"
                        class="group flex w-full items-center justify-center gap-3 overflow-hidden rounded-2xl bg-gradient-to-r from-violet-600 to-cyan-600 px-6 py-4 text-[11px] font-black uppercase tracking-[0.14em] text-white shadow-[0_0_40px_rgba(124,77,255,0.5)] transition-all duration-300 hover:shadow-[0_0_60px_rgba(124,77,255,0.7)] hover:scale-[1.02] sm:w-auto sm:px-12 sm:text-xs sm:tracking-[0.2em]">
                        <i data-lucide="zap" class="h-5 w-5 transition-transform duration-300 group-hover:scale-110"></i>
                        <span>Get Started Free</span>
                    </a>
                    <a href="/tournaments"
                        class="flex w-full items-center justify-center gap-3 rounded-2xl border border-white/20 bg-white/5 px-6 py-4 text-[11px] font-black uppercase tracking-[0.14em] text-white backdrop-blur-md transition-all duration-300 hover:border-white/40 hover:bg-white/10 sm:w-auto sm:px-12 sm:text-xs sm:tracking-[0.2em]">
                        <i data-lucide="trophy" class="h-5 w-5"></i>
                        <span>Browse Tournaments</span>
                    </a>
                </div>
            </div>
        </section>

    </main>


    {{-- ─── FOOTER ─── --}}
    <footer class="relative border-t border-zinc-800/50 bg-[#030209] px-4 py-10 sm:py-12">
        <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-violet-500/30 to-transparent"></div>
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 text-center md:flex-row md:text-left">
            <div class="flex items-center gap-3">
                <img src="/playersaloons_logo.webp" alt="Logo" class="h-7 w-auto brightness-75">
                <span class="font-orbitron text-xs font-black uppercase tracking-widest text-zinc-500">
                    {{ $footer?->title ?? 'PlayerSaloons' }}
                </span>
            </div>
            <p class="max-w-xs text-[9px] font-bold uppercase leading-5 tracking-widest text-zinc-700 sm:max-w-none sm:text-[10px]">
                &copy; {{ date('Y') }} {{ $footer?->body ?? 'ALL RIGHTS RESERVED. OPERATED BY PLAYERSALOONS SYSTEMS.' }}
            </p>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-3 text-[10px] font-black uppercase tracking-widest text-zinc-600 md:justify-end">
                @foreach($footer?->activeItems ?? [] as $item)
                    <a href="{{ $item->url ?: '#' }}" class="transition-colors hover:text-cyan-400">{{ $item->label ?: $item->title }}</a>
                @endforeach
            </div>
        </div>
    </footer>

</div>
