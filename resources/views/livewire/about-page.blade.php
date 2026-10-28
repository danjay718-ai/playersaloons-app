@php
    $symbol = \App\Shared\Support\Currency::symbol();
    $arenaTimezone = app(\App\Modules\Tournament\Services\TournamentTimezone::class)->value();
    $openByGame = $openTournaments->groupBy('game_id');
    $floorTables = $games->take(8)->values()->map(function ($game, $idx) use ($openByGame, $symbol, $arenaTimezone) {
        $gameTournaments = $openByGame->get($game->id, collect());
        $next = $gameTournaments->first();
        return [
            'id' => $game->id,
            'name' => $game->localizedName(),
            'slug' => $game->slug,
            'image' => $game->cardImageUrl() ?: $game->bannerUrl() ?: '/gamersrival-icon.png',
            'openCount' => $gameTournaments->count(),
            'nextTitle' => $next?->display_name,
            'nextUuid' => $next?->uuid,
            'nextTime' => $next?->start_at ? $next->start_at->copy()->setTimezone($arenaTimezone)->format('M d · H:i').' '.$arenaTimezone : __('Daily slots'),
            'nextSeats' => $next ? ($next->registrations_count.' / '.$next->max_participants) : '0 / 0',
            'nextEntry' => $next ? ((float) $next->entry_fee > 0 ? $symbol.number_format((float) $next->entry_fee, 2) : __('Free')) : __('Free'),
            'href' => route('games.show', ['game' => $game, 'view' => 'guest']),
        ];
    })->all();

    $pillars = [
        [
            'num' => '01',
            'tag' => __('Rules First'),
            'title' => __('Published fair-play rules before you ever spend a credit.'),
            'summary' => __('Every tournament and 1v1 duel follows transparent, pre-published rules covering lobby setup, match timers, and reporting.'),
            'detail' => __('Before joining any seat, you can inspect the exact format, platform requirements, and result reporting window. No hidden conditions.'),
            'link' => '/policies',
            'linkLabel' => __('Read platform policies'),
            'accent' => 'lime',
        ],
        [
            'num' => '02',
            'tag' => __('Clear Entry & Prizes'),
            'title' => __('Transparent entry fees and guaranteed prize structures.'),
            'summary' => __('Each competition displays its exact entry fee and winner payout structure upfront before registration opens.'),
            'detail' => __('Whether you enter a free sponsored bracket or a high-stakes 1v1 Head-to-Head, the prize pool and seat capacity are locked and visible on the board.'),
            'link' => '/tournaments',
            'linkLabel' => __('Browse open seats'),
            'accent' => 'cyan',
        ],
        [
            'num' => '03',
            'tag' => __('Verified Results'),
            'title' => __('Automated confirmation backed by human admin arbitration.'),
            'summary' => __('Players submit match results directly in the match room. Undisputed results confirm fast; conflicts go straight to admin review.'),
            'detail' => __('Screenshot proof and match timestamps protect honest competitors. False reporting is penalized automatically to keep the arena clean.'),
            'link' => '/h2h?view=guest',
            'linkLabel' => __('Explore 1v1 Head-to-Head'),
            'accent' => 'yellow',
        ],
        [
            'num' => '04',
            'tag' => __('Fast Wallet Payouts'),
            'title' => __('Prize credits land in your wallet as soon as the match settles.'),
            'summary' => __('Track every entry, refund, and prize payout in your real-time wallet ledger and request a cash-out anytime.'),
            'detail' => __('Every financial movement is recorded in an immutable ledger so you always have full visibility over your balance and winnings.'),
            'link' => '/register',
            'linkLabel' => __('Create free account'),
            'accent' => 'pink',
        ],
    ];
@endphp

<main class="landing-page-root gr-public-page min-h-screen bg-[#05080f] text-zinc-100">
    <!-- 1. Interactive Hero + Live Arena Floor Radar -->
    <section class="relative overflow-hidden border-b border-cyan-500/20 pb-16 pt-8 sm:pb-20">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(600px_circle_at_15%_20%,rgba(198,255,61,0.12),transparent_60%),radial-gradient(700px_circle_at_85%_30%,rgba(34,230,255,0.14),transparent_60%)]"></div>

        <div class="gr-wrap relative z-10 grid items-center gap-10 lg:grid-cols-12">
            <!-- Left Copy -->
            <div class="space-y-6 lg:col-span-6">
                <p class="gr-eyebrow"><span class="gr-slash" aria-hidden="true">//</span> {{ __('Inside GamersRival') }}</p>
                <h1 class="font-orbitron text-4xl font-black uppercase leading-[1.05] tracking-tight text-white sm:text-5xl lg:text-6xl">
                    <span class="block">{{ __('An arena where') }}</span>
                    <span class="block text-[#22e6ff]">{{ __('every match') }}</span>
                    <span class="block text-[#c6ff3d]">{{ __('is a test of skill.') }}</span>
                </h1>
                <p class="max-w-xl text-base leading-relaxed text-[#a8b6c9]">
                    {{ $subtitle }}
                </p>

                <div class="flex flex-wrap gap-3.5 pt-1">
                    @guest
                        <x-guest.button href="/register">{{ __('Create Free Account') }}</x-guest.button>
                    @else
                        <x-guest.button href="/tournaments">{{ __('Enter the Arena') }}</x-guest.button>
                    @endguest
                    <x-guest.button href="#arena-pillars" variant="ghost" arrow="↓">{{ __('How the House Runs') }}</x-guest.button>
                </div>

                <!-- Live Proof Strip -->
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3 rounded-2xl border border-cyan-500/20 bg-[#0a111d]/90 px-5 py-3.5 text-xs sm:text-sm">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-[#c6ff3d] shadow-[0_0_8px_#c6ff3d]"></span>
                        <strong class="font-orbitron font-black text-white">{{ number_format($stats['players'] ?? 0) }}</strong>
                        <span class="text-[#a8b6c9]">{{ __('players') }}</span>
                    </span>
                    <span class="text-white/15" aria-hidden="true">|</span>
                    <span>
                        <strong class="font-orbitron font-black text-[#22e6ff]">{{ number_format($stats['matches'] ?? 0) }}</strong>
                        <span class="text-[#a8b6c9]">{{ __('matches settled') }}</span>
                    </span>
                    <span class="text-white/15" aria-hidden="true">|</span>
                    <span>
                        <strong class="font-orbitron font-black text-[#ffd23f]">{{ $winSummary['total_won'] ?? ($symbol.'0.00') }}</strong>
                        <span class="text-[#a8b6c9]">{{ __('paid to winners') }}</span>
                    </span>
                </div>
            </div>

            <!-- Right: Interactive Arena Floor Radar -->
            <div
                class="lg:col-span-6"
                x-data="{
                    tables: @js($floorTables),
                    picked: 0,
                    get active() { return this.tables[this.picked] || null; }
                }"
            >
                <div class="gr-surface-card relative overflow-hidden rounded-3xl p-5 sm:p-7">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 pb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-[#c6ff3d] shadow-[0_0_10px_#c6ff3d] animate-pulse"></span>
                            <span class="font-orbitron text-xs font-black uppercase tracking-widest text-white">{{ __('Live Arena Floor') }}</span>
                        </div>
                        <span class="font-mono text-xs font-bold text-[#22e6ff]">
                            {{ $openTournaments->count() }} {{ __('open competitions') }}
                        </span>
                    </div>

                    @if(!empty($floorTables))
                        <!-- Interactive Game Nodes Grid -->
                        <p class="mt-3 text-xs text-[#a8b6c9]">{{ __('Tap any game table below to inspect its live status on the floor:') }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                            <template x-for="(table, index) in tables" :key="table.id">
                                <button
                                    type="button"
                                    @click="picked = index"
                                    class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border p-3 text-left transition-all"
                                    :class="picked === index
                                        ? 'border-[#c6ff3d] bg-[#c6ff3d]/15 shadow-[0_0_24px_rgba(198,255,61,0.2)]'
                                        : 'border-white/10 bg-[#05080f]/80 hover:border-[#22e6ff]/50'"
                                >
                                    <div class="flex items-center justify-between gap-1.5">
                                        <span
                                            class="h-2 w-2 rounded-full"
                                            :class="table.openCount > 0 ? 'bg-[#c6ff3d] shadow-[0_0_8px_#c6ff3d]' : 'bg-zinc-600'"
                                        ></span>
                                        <span class="font-mono text-[10px] font-bold" :class="table.openCount > 0 ? 'text-[#c6ff3d]' : 'text-zinc-500'" x-text="table.openCount + ' open'"></span>
                                    </div>
                                    <span class="mt-3 truncate font-orbitron text-xs font-black uppercase text-white" x-text="table.name"></span>
                                </button>
                            </template>
                        </div>

                        <!-- Selected Table Live Inspector -->
                        <template x-if="active">
                            <div class="mt-4 rounded-2xl border border-[#22e6ff]/30 bg-[#05080f]/90 p-4 sm:p-5">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#22e6ff]" x-text="active.name + ' Table'"></span>
                                        <h3 class="mt-1 font-orbitron text-base font-black text-white sm:text-lg" x-text="active.nextTitle || (active.name + ' — Daily Arena')"></h3>
                                    </div>
                                    <span class="rounded-full border border-[#c6ff3d]/40 bg-[#c6ff3d]/10 px-3 py-1 font-orbitron text-xs font-black text-[#c6ff3d]" x-text="active.nextEntry"></span>
                                </div>

                                <dl class="mt-4 grid grid-cols-3 gap-3 border-t border-white/10 pt-3 text-xs">
                                    <div>
                                        <dt class="text-[10px] uppercase tracking-wider text-[#a8b6c9]">{{ __('Next Start') }}</dt>
                                        <dd class="mt-0.5 font-mono font-bold text-[#ffd23f]" x-text="active.nextTime"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] uppercase tracking-wider text-[#a8b6c9]">{{ __('Seats') }}</dt>
                                        <dd class="mt-0.5 font-mono font-bold text-white" x-text="active.nextSeats"></dd>
                                    </div>
                                    <div class="text-right">
                                        <a :href="active.nextUuid ? ('/tournaments/' + active.nextUuid + '/view?view=guest') : active.href" wire:navigate class="inline-flex items-center gap-1.5 rounded-xl bg-[#c6ff3d] px-3.5 py-2 font-orbitron text-[10px] font-black uppercase tracking-wider text-[#07110a] transition hover:brightness-110">
                                            <span x-text="active.nextUuid ? '{{ __('Take a Seat') }}' : '{{ __('View Table') }}'"></span>
                                            <span aria-hidden="true">↗</span>
                                        </a>
                                    </div>
                                </dl>
                            </div>
                        </template>
                    @else
                        <div class="py-10 text-center text-sm text-[#a8b6c9]">
                            {{ __('Explore live tournaments and 1v1 Head-to-Head matches across all supported platforms.') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <!-- 2. Live Winners Ticker -->
    <livewire:landing.guest-pulse placement="activity" />

    <!-- 3. Interactive "How the House Runs" 4 Pillars -->
    <section id="arena-pillars" class="gr-wrap py-16 sm:py-20" x-data="{ activePillar: 0 }">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="gr-eyebrow"><span class="gr-slash" aria-hidden="true">//</span> {{ __('Arena Standard') }}</p>
                <h2 class="font-orbitron text-3xl font-black uppercase tracking-tight text-white sm:text-4xl">{{ __('How the house runs.') }}</h2>
                <p class="mt-2 max-w-2xl text-sm text-[#a8b6c9]">
                    {{ __('Platform-run events with published rules, fixed entry fees, and verified prize payouts. Select any pillar below to inspect how it works.') }}
                </p>
            </div>
            <a href="/tournaments" wire:navigate class="text-xs font-extrabold uppercase tracking-wider text-[#c6ff3d] hover:text-[#22e6ff]">
                {{ __('See Open Competitions') }} ↗
            </a>
        </div>

        <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            @foreach($pillars as $idx => $pillar)
                <article
                    @click="activePillar = {{ $idx }}"
                    class="gr-surface-card cursor-pointer rounded-3xl p-6 transition-all"
                    :class="activePillar === {{ $idx }} ? '!border-[#c6ff3d] shadow-[0_0_32px_rgba(198,255,61,0.16)] -translate-y-1' : 'hover:border-[#22e6ff]/40'"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-orbitron text-2xl font-black text-[#22e6ff]">{{ $pillar['num'] }}</span>
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 font-orbitron text-[10px] font-bold uppercase tracking-wider text-[#c6ff3d]">{{ $pillar['tag'] }}</span>
                    </div>
                    <h3 class="mt-4 font-orbitron text-base font-black leading-snug text-white">{{ $pillar['title'] }}</h3>
                    <p class="mt-2.5 text-xs leading-relaxed text-[#a8b6c9]">{{ $pillar['summary'] }}</p>
                    <div x-show="activePillar === {{ $idx }}" x-collapse class="mt-4 border-t border-white/10 pt-3 text-xs leading-relaxed text-zinc-200">
                        <p>{{ $pillar['detail'] }}</p>
                        <a href="{{ $pillar['link'] }}" wire:navigate class="mt-3 inline-flex items-center gap-1.5 font-orbitron text-[10px] font-black uppercase tracking-wider text-[#c6ff3d] hover:text-[#22e6ff]">
                            <span>{{ $pillar['linkLabel'] }}</span>
                            <span aria-hidden="true">↗</span>
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <!-- 4. Mission & Story Section (Preserves required test classes) -->
    <section class="mx-auto w-full max-w-6xl px-4 pb-16 sm:px-6 lg:px-8">
        <div class="mx-auto mb-10 max-w-3xl text-center">
            <p class="landing-section-kicker gr-eyebrow justify-center"><span class="gr-slash" aria-hidden="true">//</span> GamersRival</p>
            <h2 class="mt-3 font-orbitron text-3xl font-black uppercase text-white sm:text-4xl">{{ $title }}</h2>
            <p class="mt-3 text-sm leading-7 text-[#a8b6c9] sm:text-base">{{ __('Built by players, engineers, and tournament admins to keep every bracket fair and open.') }}</p>
        </div>

        <article class="landing-card relative mx-auto w-full max-w-5xl overflow-hidden rounded-3xl p-6 sm:p-10">
            <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-[#22e6ff]/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-20 -left-20 h-48 w-48 rounded-full bg-[#c6ff3d]/10 blur-3xl"></div>
            <div class="about-content relative prose prose-invert max-w-none text-sm leading-7 text-zinc-200 sm:text-base">
                {!! $body !!}
            </div>

            <div class="relative mt-8 flex flex-wrap items-center justify-between gap-4 border-t border-white/10 pt-6">
                <div class="flex flex-wrap gap-2">
                    @foreach(['Console', 'PC', 'Mobile', '1v1 Head-to-Head', 'Daily Brackets', 'Global Arena'] as $tag)
                        <span class="rounded-full border border-[#22e6ff]/30 bg-[#22e6ff]/10 px-3.5 py-1 font-orbitron text-[10px] font-bold uppercase tracking-wider text-[#22e6ff]">{{ $tag }}</span>
                    @endforeach
                </div>
                <a href="/contact" wire:navigate class="text-xs font-extrabold uppercase tracking-wider text-[#c6ff3d] hover:text-[#22e6ff]">
                    {{ __('Talk to our team') }} →
                </a>
            </div>
        </article>
    </section>

    <!-- 5. Interactive Game Shelf ("On the Floor") -->
    @if($games->isNotEmpty())
        <section class="gr-wrap pb-12">
            <div class="flex items-end justify-between gap-4 mb-6">
                <div>
                    <p class="gr-eyebrow"><span class="gr-slash" aria-hidden="true">//</span> {{ __('Supported Titles') }}</p>
                    <h2 class="font-orbitron text-2xl font-black uppercase text-white sm:text-3xl">{{ __('On the floor right now.') }}</h2>
                </div>
                <a href="/tournaments" wire:navigate class="text-xs font-extrabold uppercase tracking-wider text-[#c6ff3d] hover:text-[#22e6ff]">
                    {{ __('View All Games') }} ↗
                </a>
            </div>
            <div class="flex gap-3.5 overflow-x-auto pb-3 [scrollbar-width:none]">
                @foreach($games as $game)
                    <a href="{{ route('games.show', ['game' => $game, 'view' => 'guest']) }}" wire:navigate class="gr-surface-card group flex min-w-[210px] items-center gap-3.5 rounded-2xl p-3 transition-all hover:-translate-y-0.5">
                        <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-white/10 bg-[#05080f]">
                            @if($game->cardImageUrl() || $game->bannerUrl())
                                <img src="{{ $game->cardImageUrl() ?: $game->bannerUrl() }}" alt="{{ $game->localizedName() }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">
                            @else
                                <div class="flex h-full w-full items-center justify-center"><img src="/gamersrival-icon.png" alt="" class="h-7 w-7 opacity-75"></div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-orbitron text-xs font-black uppercase text-white group-hover:text-[#c6ff3d]">{{ $game->localizedName() }}</h3>
                            <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-[#22e6ff]">
                                {{ __('Open Table') }} <span aria-hidden="true">↗</span>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <!-- 6. Closing CTA Band -->
    <x-guest.cta-band />
</main>
