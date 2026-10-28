@php
    $profile = $user->profile;
    $displayName = $profile?->display_name ?: $user->username;
    $avatarUrl = $profile?->avatar_url;
    $xpWithinLevel = $progression->xpWithinLevel();
    $xpTarget = \App\Modules\Identity\Models\PlayerProgression::XP_PER_LEVEL;
    $winRate = $stats['matches_played'] > 0 ? (int) round(($stats['wins'] / $stats['matches_played']) * 100) : 0;
@endphp

<div class="player-dashboard min-w-0 max-w-full space-y-6 overflow-x-hidden" wire:poll.30s.visible>
    <x-player.dashboard-tabs :items="$navItems" />

    {{-- Command header: who you are, your level, and the three things you do most. --}}
    <section class="player-dashboard-welcome relative overflow-hidden rounded-3xl border border-cyan-400/20 bg-[radial-gradient(circle_at_85%_10%,rgba(34,230,255,.14),transparent_30%),linear-gradient(120deg,#0e1726,#0a1220_58%,#05080f)] p-5 shadow-2xl sm:p-7">
        <i data-lucide="gamepad-2" aria-hidden="true" class="player-dashboard-watermark"></i>
        <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-center">
            <div class="flex min-w-0 flex-col items-start gap-4 sm:flex-row sm:items-center sm:gap-6">
                <div class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl border border-[#c6ff3d]/50 bg-zinc-950 shadow-[0_0_26px_rgba(198,255,61,.2)] sm:h-20 sm:w-20">
                    @if($avatarUrl)<img src="{{ $avatarUrl }}" alt="{{ $displayName }}" class="h-full w-full object-cover">@else<div class="flex h-full w-full items-center justify-center font-orbitron text-xl font-black text-[#c6ff3d]">{{ strtoupper(substr($displayName, 0, 2)) }}</div>@endif
                </div>
                <div class="min-w-0">
                    <p class="text-[9px] font-black uppercase tracking-[0.3em] text-cyan-300">Player command center</p>
                    <h1 class="mt-2 break-words font-orbitron text-2xl font-black uppercase leading-tight text-white [overflow-wrap:anywhere] sm:text-3xl">{{ __('Welcome, :name', ['name' => $displayName]) }}</h1>
                    <div class="mt-4 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        <a href="{{ route('tournaments.browse', ['frequency' => 'daily']) }}" wire:navigate class="theme-primary-action inline-flex min-w-0 items-center justify-center gap-2 rounded-xl bg-[#c6ff3d] px-4 py-3 text-center text-[10px] font-black uppercase leading-tight tracking-widest text-[#05080f] transition hover:bg-[#d5ff66]"><i data-lucide="search" class="h-3.5 w-3.5 shrink-0"></i><span>Find Tournaments</span></a>
                        <a href="{{ route('my-tournaments') }}" wire:navigate class="inline-flex min-w-0 items-center justify-center gap-2 rounded-xl border border-white/15 bg-white/5 px-4 py-3 text-center text-[10px] font-black uppercase leading-tight tracking-widest text-zinc-200 transition hover:border-cyan-400/50 hover:text-white"><i data-lucide="trophy" class="h-3.5 w-3.5 shrink-0"></i><span>My Tournaments</span></a>
                        <a href="{{ route('platform-h2h') }}" wire:navigate class="col-span-2 inline-flex min-w-0 items-center justify-center gap-2 rounded-xl border border-cyan-400/35 bg-cyan-400/10 px-4 py-3 text-center text-[10px] font-black uppercase leading-tight tracking-widest text-cyan-200 transition hover:bg-cyan-400/20 sm:col-span-1"><i data-lucide="swords" class="h-3.5 w-3.5 shrink-0"></i><span>Find Head-to-Head Matches</span></a>
                    </div>
                </div>
            </div>

            <div class="player-dashboard-progression rounded-2xl border border-white/10 bg-black/30 p-5 backdrop-blur-sm">
                <div class="flex items-end justify-between gap-4"><div><p class="text-[9px] font-black uppercase tracking-[0.24em] text-zinc-500">Platform progression</p><p class="player-level-title mt-2 font-orbitron text-2xl font-black text-white">{{ __('LEVEL :value1', ['value1' => ($progression->level)]) }}</p></div><div class="text-right"><p class="player-level-xp font-orbitron text-sm font-black text-[#c6ff3d]">{{ __(':value1 XP', ['value1' => (number_format($progression->experience_points))]) }}</p><p class="mt-1 text-[9px] uppercase tracking-wider text-zinc-600">Lifetime XP</p></div></div>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-zinc-800"><div class="h-full rounded-full bg-gradient-to-r from-[#c6ff3d] to-[#22e6ff] shadow-[0_0_12px_rgba(198,255,61,.5)]" style="width: {{ $progression->progressPercent() }}%"></div></div>
                <div class="mt-2 flex justify-between text-[9px] font-bold uppercase tracking-wider text-zinc-500"><span>{{ __(':value1 / :value2 XP', ['value1' => ($xpWithinLevel), 'value2' => ($xpTarget)]) }}</span><span>{{ __(':value1 to next level', ['value1' => ($xpTarget - $xpWithinLevel)]) }}</span></div>
            </div>
        </div>
    </section>

    {{-- Four numbers that matter. --}}
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach([
            ['label' => 'Active Events', 'value' => $stats['active_tournaments'], 'icon' => 'calendar-check', 'color' => 'text-cyan-300'],
            ['label' => 'Match Record', 'value' => $stats['wins'].'W · '.$stats['losses'].'L', 'icon' => 'swords', 'color' => 'text-white'],
            ['label' => 'Win Rate', 'value' => $winRate.'%', 'icon' => 'target', 'color' => 'text-[#c6ff3d]'],
            ['label' => 'Prize Earnings', 'value' => \App\Shared\Support\Currency::symbol().number_format($stats['earnings'], 2), 'icon' => 'coins', 'color' => 'text-amber-300'],
        ] as $metric)
            <article class="player-stat-card rounded-2xl border border-cyan-500/15 bg-[#0a1220]/80 p-4 sm:p-5">
                <div class="flex items-center justify-between gap-3"><p class="text-[9px] font-black uppercase tracking-widest text-zinc-500">{{ __($metric['label']) }}</p><span class="player-stat-icon"><i data-lucide="{{ $metric['icon'] }}" class="h-4 w-4 {{ $metric['color'] }}"></i></span></div>
                <p class="mt-3 break-words font-orbitron text-lg font-black {{ $metric['color'] }} sm:text-2xl">{{ $metric['value'] }}</p>
            </article>
        @endforeach
    </section>

    {{-- Active games: quick launch into a game's competitions. --}}
    @if(! empty($activeGames))
        <section class="player-dashboard-games rounded-2xl border border-cyan-500/15 bg-[#0a1220]/70 p-5">
            <header class="flex items-center justify-between gap-3">
                <div><p class="text-[9px] font-black uppercase tracking-[0.22em] text-[#c6ff3d]">Active games</p><h2 class="mt-1 font-orbitron text-base font-black uppercase text-white">Jump Into a Game</h2></div>
                <a href="{{ route('tournaments.browse', ['frequency' => 'daily']) }}" wire:navigate class="shrink-0 text-[10px] font-black uppercase tracking-wider text-cyan-300 hover:text-[#c6ff3d]">All tournaments →</a>
            </header>
            <div class="mt-4 flex snap-x gap-3 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:grid sm:grid-cols-3 sm:overflow-visible lg:grid-cols-6">
                @foreach($activeGames as $game)
                    <a href="{{ route('games.show', $game['slug']) }}" wire:navigate class="group relative min-w-[9.5rem] snap-start overflow-hidden rounded-xl border border-white/10 bg-[#05080f] transition hover:border-[#c6ff3d]/60 sm:min-w-0">
                        <div class="aspect-[4/3] w-full bg-gradient-to-br from-[#0e1726] to-[#05080f]">
                            @if($game['image'])<img src="{{ $game['image'] }}" alt="" class="h-full w-full object-cover opacity-80 transition group-hover:opacity-100" loading="lazy">@endif
                        </div>
                        <div class="p-2.5">
                            <p class="truncate text-xs font-black text-white">{{ $game['name'] }}</p>
                            <p class="mt-0.5 text-[9px] font-bold uppercase tracking-wider {{ $game['open'] > 0 ? 'text-[#c6ff3d]' : 'text-zinc-600' }}">{{ $game['open'] > 0 ? __(':value1 open', ['value1' => $game['open']]) : __('No open events') }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="grid min-w-0 max-w-full gap-6 xl:grid-cols-12">
        <div class="min-w-0 max-w-full space-y-6 xl:col-span-8">
            <section class="w-full max-w-full overflow-hidden rounded-2xl border border-cyan-500/15 bg-[#0a1220]/70">
                <header class="flex min-w-0 flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-4"><div class="min-w-0"><p class="text-[9px] font-black uppercase tracking-[0.22em] text-cyan-400">Competition queue</p><h2 class="mt-1 font-orbitron text-base font-black uppercase text-white">Your Active Tournaments</h2></div><a href="/my-tournaments" wire:navigate class="shrink-0 text-[10px] font-black uppercase tracking-wider text-cyan-300 hover:text-[#c6ff3d]">View all →</a></header>
                @if($activeTournaments !== [])
                    <div class="divide-y divide-white/[0.05]">
                        @foreach($activeTournaments as $tournament)
                            @php $status = $tournament['status']; @endphp
                            <a href="/tournaments/{{ $tournament['uuid'] }}/view" wire:navigate class="player-clickable-row grid gap-3 px-5 py-4 transition hover:bg-cyan-400/5 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center">
                                <div class="min-w-0"><div class="flex items-start gap-2"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $status === 'ONGOING' ? 'animate-pulse bg-red-400' : ($status === 'REGISTRATION_OPEN' ? 'bg-[#c6ff3d]' : 'bg-amber-400') }}"></span><h3 class="break-words text-sm font-bold text-zinc-100">{{ $tournament['name'] }}</h3></div><p class="mt-1 break-words pl-4 text-[10px] uppercase tracking-wider text-zinc-500">{{ $tournament['game'] }} · {{ \App\Shared\Enums\TournamentStatus::from($status)->label() }}</p></div>
                                <div class="text-left sm:text-right"><p class="text-[9px] font-black uppercase tracking-wider text-zinc-600">Starts</p><p class="mt-1 text-xs font-bold text-zinc-300">{{ $tournament['starts_at'] }}</p></div>
                                <div class="text-left sm:w-16 sm:text-right"><p class="text-[9px] font-black uppercase tracking-wider text-zinc-600">Players</p><p class="mt-1 text-xs font-bold text-cyan-300">{{ $tournament['registrations_count'] }}/{{ $tournament['max_participants'] }}</p></div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center"><i data-lucide="calendar-plus" class="mx-auto h-8 w-8 text-zinc-700"></i><p class="mt-3 font-sans text-sm leading-6 text-zinc-400">Your competition queue is clear.</p><a href="/tournaments/browse?frequency=daily" wire:navigate class="mt-2 inline-block font-sans text-sm leading-6 text-[#c6ff3d]">Browse open tournaments</a></div>
                @endif
            </section>

            <section class="w-full max-w-full overflow-hidden rounded-2xl border border-cyan-500/15 bg-[#0a1220]/70">
                <header class="flex min-w-0 flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-4"><div class="min-w-0"><p class="text-[9px] font-black uppercase tracking-[0.22em] text-[#c6ff3d]">Performance feed</p><h2 class="mt-1 font-orbitron text-base font-black uppercase text-white">Recent Matches</h2></div><div class="shrink-0 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-[10px] font-bold text-zinc-400">{{ __(':value1 played', ['value1' => ($stats['matches_played'])]) }}</div></header>
                @if($recentMatches !== [])
                    <div class="divide-y divide-white/[0.05]">
                        @foreach($recentMatches as $match)
                            <a href="{{ ($match['workflow_version'] ?? 1) === 2 ? '/tournaments/'.$match['tournament_uuid'].'/view?activeTab=submit-results&match='.$match['uuid'] : '/matches/'.$match['uuid'] }}" wire:navigate class="player-clickable-row flex items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-cyan-400/5">
                                <div class="min-w-0"><p class="break-words text-xs font-bold text-white">{{ $match['tournament'] }}</p><p class="mt-1 break-words text-[10px] text-zinc-500">{{ $match['game'] }} · {{ __(ucfirst($match['status'])) }} · {{ $match['updated_at'] }}</p></div>
                                <div class="flex shrink-0 flex-col items-end gap-1"><span class="rounded-md px-2 py-1 text-[9px] font-black uppercase {{ $match['outcome'] === 'win' ? 'bg-[#c6ff3d]/12 text-[#c6ff3d]' : ($match['outcome'] === 'loss' ? 'bg-rose-500/10 text-rose-300' : 'bg-amber-500/10 text-amber-300') }}">{{ __($match['outcome'] === 'loss' ? 'Lost' : ucfirst($match['outcome'])) }}</span><span class="text-[9px] font-black uppercase tracking-wider text-cyan-300">{{ ($match['workflow_version'] ?? 1) === 2 ? 'Open Submit Result' : 'Open Match Room' }}</span></div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="p-8 text-center font-sans text-sm leading-6 text-zinc-400">Completed and active matches will appear here.</p>
                @endif
            </section>
        </div>

        <aside class="min-w-0 max-w-full space-y-6 xl:col-span-4">
            <div class="player-dashboard-quick-links grid grid-cols-2 gap-3">
                <a href="/wallet" wire:navigate class="player-dashboard-quick-card player-dashboard-balance rounded-2xl border border-[#c6ff3d]/20 bg-[#c6ff3d]/5 p-4 transition hover:bg-[#c6ff3d]/10"><i data-lucide="wallet" class="h-5 w-5 text-[#c6ff3d]"></i><p class="mt-3 text-[9px] font-black uppercase tracking-wider text-zinc-500">Balance</p><p class="mt-1 break-words font-orbitron text-lg font-black text-[#c6ff3d]">@currencySymbol{{ number_format($stats['balance'], 2) }}</p></a>
                <a href="/profile" wire:navigate class="player-dashboard-quick-card player-dashboard-completed rounded-2xl border border-cyan-400/20 bg-cyan-400/5 p-4 transition hover:bg-cyan-400/10"><i data-lucide="shield-check" class="h-5 w-5 text-cyan-300"></i><p class="mt-3 text-[9px] font-black uppercase tracking-wider text-zinc-500">Completed</p><p class="mt-1 break-words font-orbitron text-lg font-black text-cyan-300">{{ __(':value1 events', ['value1' => ($progression->tournaments_completed)]) }}</p></a>
            </div>

            <section class="player-dashboard-announcements w-full max-w-full overflow-hidden rounded-2xl border border-amber-400/20 bg-[#0a1220]/70">
                <header class="flex min-w-0 items-center gap-2 border-b border-white/[0.06] px-5 py-4"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-400/10"><i data-lucide="megaphone" class="h-4 w-4 text-amber-300"></i></span><div class="min-w-0"><p class="text-[9px] font-black uppercase tracking-[0.22em] text-amber-400">Official feed</p><h2 class="font-orbitron text-sm font-black uppercase text-white">Announcements</h2></div></header>
                <div class="divide-y divide-white/[0.05]">
                    @forelse($announcements as $announcement)
                        <article class="px-5 py-4"><div class="flex items-start gap-3"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span><div class="min-w-0"><h3 class="break-words text-xs font-black text-amber-100">{{ $announcement['title'] }}</h3><p class="mt-1.5 break-words text-xs leading-5 text-zinc-400">{{ $announcement['message'] }}</p><p class="mt-2 text-[9px] uppercase tracking-wider text-zinc-600">{{ $announcement['created_at'] }}</p></div></div></article>
                    @empty
                        <p class="p-6 text-center font-sans text-sm leading-6 text-zinc-400">No active platform announcements.</p>
                    @endforelse
                </div>
            </section>

            <section class="w-full max-w-full overflow-hidden rounded-2xl border border-cyan-500/15 bg-[#0a1220]/70 p-5">
                <div class="flex min-w-0 flex-wrap items-center justify-between gap-3"><div class="min-w-0"><p class="text-[9px] font-black uppercase tracking-[0.22em] text-[#c6ff3d]">Squad radar</p><h2 class="mt-1 font-orbitron text-sm font-black uppercase text-white">Following Online</h2></div><span class="shrink-0 rounded-full bg-[#c6ff3d]/10 px-2.5 py-1 text-[9px] font-black text-[#c6ff3d]">{{ __(':value1 online', ['value1' => (count($onlineFollowing))]) }}</span></div>
                <div class="mt-4 space-y-2">
                    @forelse($onlineFollowing as $onlinePlayer)
                        <a href="/chat" wire:navigate class="flex items-center gap-3 rounded-xl border border-white/[0.06] bg-white/[0.03] p-2.5 transition hover:border-[#c6ff3d]/30"><div class="relative h-9 w-9 shrink-0 overflow-hidden rounded-full bg-zinc-800">@if($onlinePlayer['avatar_url'])<img src="{{ $onlinePlayer['avatar_url'] }}" alt="{{ $onlinePlayer['username'] }}" class="h-full w-full object-cover">@else<div class="flex h-full w-full items-center justify-center text-[10px] font-black text-zinc-400">{{ strtoupper(substr($onlinePlayer['username'], 0, 2)) }}</div>@endif<span class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 border-zinc-950 bg-[#c6ff3d]"></span></div><div class="min-w-0"><p class="break-words text-xs font-bold text-zinc-200" translate="no">{{ $onlinePlayer['display_name'] }}</p><p class="text-[9px] uppercase tracking-wider text-[#c6ff3d]">Online now</p></div><i data-lucide="message-circle" class="ml-auto h-3.5 w-3.5 shrink-0 text-zinc-600"></i></a>
                    @empty
                        <div class="rounded-xl border border-dashed border-white/10 p-4 text-center font-sans text-sm leading-6 text-zinc-400">No followed players are online right now.</div>
                    @endforelse
                </div>
            </section>

            <section class="w-full max-w-full overflow-hidden rounded-2xl border border-cyan-500/15 bg-[#0a1220]/70">
                <header class="flex min-w-0 flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-4"><div class="min-w-0"><p class="text-[9px] font-black uppercase tracking-[0.22em] text-cyan-400">Live community</p><h2 class="mt-1 font-orbitron text-sm font-black uppercase text-white">Global Chat</h2></div><a href="/chat" wire:navigate class="shrink-0 text-[9px] font-black uppercase tracking-wider text-cyan-300 hover:text-[#c6ff3d]">Open chat →</a></header>
                <div class="space-y-3 p-4">
                    @forelse($globalMessages as $message)
                        <div class="flex items-start gap-2.5"><div class="h-7 w-7 shrink-0 overflow-hidden rounded-full bg-zinc-800">@if($message['avatar_url'])<img src="{{ $message['avatar_url'] }}" alt="" class="h-full w-full object-cover">@else<div class="flex h-full w-full items-center justify-center text-[8px] font-black text-zinc-500">{{ strtoupper(substr($message['username'], 0, 2)) }}</div>@endif</div><div class="min-w-0"><p class="break-words text-[10px] font-black text-cyan-300" translate="no">{{ $message['display_name'] }}</p><p class="break-words text-xs leading-5 text-zinc-400">{{ $message['body'] }}</p></div></div>
                    @empty
                        <p class="py-4 text-center font-sans text-sm leading-6 text-zinc-400">Global chat is quiet. Start the conversation.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</div>
