@php
    $profile = $user->profile;
    $displayName = $profile?->display_name ?: $user->username;
    $avatarUrl = $profile?->avatar_url;
    $xpWithinLevel = $progression->xpWithinLevel();
    $xpTarget = \App\Modules\Identity\Models\PlayerProgression::XP_PER_LEVEL;
    $winRate = $stats['matches_played'] > 0 ? (int) round(($stats['wins'] / $stats['matches_played']) * 100) : 0;
@endphp

<div class="min-w-0 max-w-full space-y-6 overflow-x-hidden" wire:poll.30s.visible>
    <x-player.dashboard-tabs :items="$navItems" />

    <section class="relative overflow-hidden rounded-3xl border border-violet-500/20 bg-[radial-gradient(circle_at_82%_12%,rgba(124,58,237,.28),transparent_28%),linear-gradient(120deg,#17102d,#0b0816_58%,#08070d)] p-5 shadow-2xl sm:p-7">
        <div class="absolute inset-0 opacity-20 cyber-grid"></div>
        <div class="relative grid gap-7 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-center">
            <div class="flex min-w-0 flex-col items-start gap-4 sm:flex-row sm:items-center sm:gap-6">
                <div class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl border border-violet-400/40 bg-zinc-950 shadow-[0_0_28px_rgba(139,92,246,.25)] sm:h-20 sm:w-20">
                    @if($avatarUrl)<img src="{{ $avatarUrl }}" alt="{{ $displayName }}" class="h-full w-full object-cover">@else<div class="flex h-full w-full items-center justify-center font-orbitron text-xl font-black text-violet-300">{{ strtoupper(substr($displayName, 0, 2)) }}</div>@endif
                </div>
                <div class="min-w-0">
                    <p class="text-[9px] font-black uppercase tracking-[0.3em] text-violet-300">Player command center</p>
                    <h1 class="mt-2 break-words font-orbitron text-2xl font-black uppercase leading-tight text-white [overflow-wrap:anywhere] sm:text-4xl">Welcome, {{ $displayName }}</h1>
                    <p class="mt-2 text-xs text-zinc-400">Your competitions, progression, squad activity, and platform updates in one place.</p>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <a href="{{ route('tournaments.browse', ['frequency' => 'daily']) }}" wire:navigate class="inline-flex min-w-0 items-center justify-center gap-2 rounded-xl bg-violet-600 px-2 py-3 text-center text-[9px] font-black uppercase leading-tight tracking-wider text-white transition hover:bg-violet-500 sm:px-4 sm:text-[10px] sm:tracking-widest"><i data-lucide="search" class="h-3.5 w-3.5 shrink-0"></i><span>Find Tournaments</span></a>
                        <a href="{{ route('my-tournaments') }}" wire:navigate class="inline-flex min-w-0 items-center justify-center gap-2 rounded-xl border border-zinc-700 bg-zinc-950/60 px-2 py-3 text-center text-[9px] font-black uppercase leading-tight tracking-wider text-zinc-300 transition hover:border-zinc-500 hover:text-white sm:px-4 sm:text-[10px] sm:tracking-widest"><i data-lucide="trophy" class="h-3.5 w-3.5 shrink-0"></i><span>My Tournaments</span></a>
                        <a href="{{ route('platform-h2h') }}" wire:navigate class="col-span-2 inline-flex min-w-0 items-center justify-center gap-2 rounded-xl border border-fuchsia-500/30 bg-fuchsia-500/10 px-4 py-3 text-center text-[10px] font-black uppercase leading-tight tracking-wider text-fuchsia-200 transition hover:border-fuchsia-400/60 hover:bg-fuchsia-500/20 sm:tracking-widest"><i data-lucide="swords" class="h-3.5 w-3.5 shrink-0"></i><span>Find Head-to-Head Matches</span></a>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-black/25 p-5 backdrop-blur-sm">
                <div class="flex items-end justify-between gap-4"><div><p class="text-[9px] font-black uppercase tracking-[0.24em] text-zinc-500">Platform progression</p><p class="mt-2 font-orbitron text-2xl font-black text-white">LEVEL {{ $progression->level }}</p></div><div class="text-right"><p class="font-orbitron text-sm font-black text-violet-300">{{ number_format($progression->experience_points) }} XP</p><p class="mt-1 text-[9px] uppercase tracking-wider text-zinc-600">Lifetime XP</p></div></div>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-zinc-800"><div class="h-full rounded-full bg-gradient-to-r from-violet-600 via-fuchsia-500 to-cyan-400 shadow-[0_0_12px_rgba(168,85,247,.7)]" style="width: {{ $progression->progressPercent() }}%"></div></div>
                <div class="mt-2 flex justify-between text-[9px] font-bold uppercase tracking-wider text-zinc-500"><span>{{ $xpWithinLevel }} / {{ $xpTarget }} XP</span><span>{{ $xpTarget - $xpWithinLevel }} to next level</span></div>
            </div>
        </div>
    </section>

    <section class="flex w-full max-w-full snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:grid sm:grid-cols-2 sm:overflow-visible sm:pb-0 lg:grid-cols-4">
        @foreach([
            ['label' => 'Active Events', 'value' => $stats['active_tournaments'], 'icon' => 'calendar-check', 'color' => 'text-cyan-300', 'border' => 'border-cyan-500/20'],
            ['label' => 'Match Record', 'value' => $stats['wins'].'W · '.$stats['losses'].'L', 'icon' => 'swords', 'color' => 'text-violet-300', 'border' => 'border-violet-500/20'],
            ['label' => 'Win Rate', 'value' => $winRate.'%', 'icon' => 'target', 'color' => 'text-fuchsia-300', 'border' => 'border-fuchsia-500/20'],
            ['label' => 'Prize Earnings', 'value' => '$'.number_format($stats['earnings'], 2), 'icon' => 'badge-dollar-sign', 'color' => 'text-emerald-300', 'border' => 'border-emerald-500/20'],
        ] as $metric)
            <article class="min-w-[15rem] snap-start rounded-2xl border {{ $metric['border'] }} bg-zinc-950/65 p-5 shadow-lg sm:min-w-0">
                <div class="flex items-center justify-between gap-3"><p class="text-[9px] font-black uppercase tracking-widest text-zinc-600">{{ $metric['label'] }}</p><i data-lucide="{{ $metric['icon'] }}" class="h-4 w-4 {{ $metric['color'] }}"></i></div>
                <p class="mt-3 break-words font-orbitron text-xl font-black {{ $metric['color'] }} sm:text-2xl">{{ $metric['value'] }}</p>
            </article>
        @endforeach
    </section>

    <div class="grid min-w-0 max-w-full gap-6 xl:grid-cols-12">
        <div class="min-w-0 max-w-full space-y-6 xl:col-span-8">
            <section class="w-full max-w-full overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-950/55">
                <header class="flex min-w-0 flex-wrap items-center justify-between gap-3 border-b border-zinc-800 px-5 py-4"><div class="min-w-0"><p class="break-words text-[9px] font-black uppercase tracking-[0.22em] text-cyan-400">Competition queue</p><h2 class="mt-1 break-words font-orbitron text-base font-black uppercase text-white">Your Active Tournaments</h2></div><a href="/my-tournaments" wire:navigate class="shrink-0 text-[10px] font-black uppercase tracking-wider text-violet-400 hover:text-violet-300">View all →</a></header>
                @if($activeTournaments !== [])
                    <div class="divide-y divide-zinc-800/70">
                        @foreach($activeTournaments as $tournament)
                            @php $status = $tournament['status']; @endphp
                            <a href="/tournaments/{{ $tournament['uuid'] }}/view" wire:navigate class="grid gap-3 px-5 py-4 transition hover:bg-violet-500/5 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center">
                                <div class="min-w-0"><div class="flex items-start gap-2"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $status === 'ONGOING' ? 'animate-pulse bg-red-400' : ($status === 'REGISTRATION_OPEN' ? 'bg-emerald-400' : 'bg-amber-400') }}"></span><h3 class="break-words text-sm font-bold text-zinc-100">{{ $tournament['name'] }}</h3></div><p class="mt-1 break-words pl-4 text-[10px] uppercase tracking-wider text-zinc-600">{{ $tournament['game'] }} · {{ str_replace('_', ' ', $status) }}</p></div>
                                <div class="text-left sm:text-right"><p class="text-[9px] font-black uppercase tracking-wider text-zinc-600">Starts</p><p class="mt-1 text-xs font-bold text-zinc-300">{{ $tournament['starts_at'] }}</p></div>
                                <div class="text-left sm:w-16 sm:text-right"><p class="text-[9px] font-black uppercase tracking-wider text-zinc-600">Players</p><p class="mt-1 text-xs font-bold text-cyan-300">{{ $tournament['registrations_count'] }}/{{ $tournament['max_participants'] }}</p></div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center"><i data-lucide="calendar-plus" class="mx-auto h-8 w-8 text-zinc-700"></i><p class="mt-3 text-sm font-bold text-zinc-400">Your competition queue is clear.</p><a href="/tournaments/browse?frequency=daily" wire:navigate class="mt-2 inline-block text-xs text-violet-400">Browse open tournaments</a></div>
                @endif
            </section>

            <section class="w-full max-w-full overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-950/55">
                <header class="flex min-w-0 flex-wrap items-center justify-between gap-3 border-b border-zinc-800 px-5 py-4"><div class="min-w-0"><p class="break-words text-[9px] font-black uppercase tracking-[0.22em] text-fuchsia-400">Performance feed</p><h2 class="mt-1 break-words font-orbitron text-base font-black uppercase text-white">Recent Matches</h2></div><div class="shrink-0 rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-[10px] font-bold text-zinc-500">{{ $stats['matches_played'] }} played</div></header>
                @if($recentMatches !== [])
                    <div class="flex w-full max-w-full snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain p-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:grid sm:grid-cols-2 sm:overflow-visible">
                        @foreach($recentMatches as $match)
                            <a href="/matches/{{ $match['uuid'] }}" wire:navigate class="min-w-[17rem] snap-start rounded-xl border border-zinc-800 bg-zinc-900/55 p-4 transition hover:border-violet-500/30 sm:min-w-0"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="break-words text-xs font-bold text-white">{{ $match['tournament'] }}</p><p class="mt-1 break-words text-[10px] text-zinc-600">{{ $match['game'] }} · {{ ucfirst($match['status']) }}</p></div><span class="shrink-0 rounded-md px-2 py-1 text-[9px] font-black uppercase {{ $match['outcome'] === 'win' ? 'bg-emerald-500/10 text-emerald-300' : ($match['outcome'] === 'loss' ? 'bg-rose-500/10 text-rose-300' : 'bg-amber-500/10 text-amber-300') }}">{{ $match['outcome'] }}</span></div><p class="mt-3 break-words text-[9px] font-black uppercase tracking-wider text-violet-400">Open Match Room · {{ $match['updated_at'] }}</p></a>
                        @endforeach
                    </div>
                @else
                    <p class="p-8 text-center text-sm text-zinc-600">Completed and active matches will appear here.</p>
                @endif
            </section>
        </div>

        <aside class="min-w-0 max-w-full space-y-6 xl:col-span-4">
            <section class="w-full max-w-full overflow-hidden rounded-2xl border border-amber-500/20 bg-[linear-gradient(145deg,rgba(120,53,15,.18),rgba(9,9,11,.9))]">
                <header class="flex min-w-0 items-center justify-between border-b border-amber-500/15 px-5 py-4"><div class="flex min-w-0 items-center gap-2"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/10"><i data-lucide="megaphone" class="h-4 w-4 text-amber-300"></i></span><div class="min-w-0"><p class="break-words text-[9px] font-black uppercase tracking-[0.22em] text-amber-400">Official feed</p><h2 class="break-words font-orbitron text-sm font-black uppercase text-white">Announcements</h2></div></div></header>
                <div class="divide-y divide-amber-500/10">
                    @forelse($announcements as $announcement)
                        <article class="px-5 py-4"><div class="flex items-start gap-3"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span><div class="min-w-0"><h3 class="break-words text-xs font-black text-amber-100">{{ $announcement['title'] }}</h3><p class="mt-1.5 break-words text-xs leading-5 text-zinc-400">{{ $announcement['message'] }}</p><p class="mt-2 text-[9px] uppercase tracking-wider text-zinc-700">{{ $announcement['created_at'] }}</p></div></div></article>
                    @empty
                        <p class="p-6 text-center text-xs text-zinc-600">No active platform announcements.</p>
                    @endforelse
                </div>
            </section>

            <section class="w-full max-w-full overflow-hidden rounded-2xl border border-emerald-500/15 bg-zinc-950/60 p-5">
                <div class="flex min-w-0 flex-wrap items-center justify-between gap-3"><div class="min-w-0"><p class="break-words text-[9px] font-black uppercase tracking-[0.22em] text-emerald-400">Squad radar</p><h2 class="mt-1 break-words font-orbitron text-sm font-black uppercase text-white">Following Online</h2></div><span class="shrink-0 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[9px] font-black text-emerald-300">{{ count($onlineFollowing) }} online</span></div>
                <div class="mt-4 space-y-2">
                    @forelse($onlineFollowing as $onlinePlayer)
                        <a href="/chat" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-800/70 bg-zinc-900/50 p-2.5 transition hover:border-emerald-500/25"><div class="relative h-9 w-9 shrink-0 overflow-hidden rounded-full bg-zinc-800">@if($onlinePlayer['avatar_url'])<img src="{{ $onlinePlayer['avatar_url'] }}" alt="{{ $onlinePlayer['username'] }}" class="h-full w-full object-cover">@else<div class="flex h-full w-full items-center justify-center text-[10px] font-black text-zinc-400">{{ strtoupper(substr($onlinePlayer['username'], 0, 2)) }}</div>@endif<span class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 border-zinc-950 bg-emerald-400"></span></div><div class="min-w-0"><p class="break-words text-xs font-bold text-zinc-200">{{ $onlinePlayer['display_name'] }}</p><p class="text-[9px] uppercase tracking-wider text-emerald-400">Online now</p></div><i data-lucide="message-circle" class="ml-auto h-3.5 w-3.5 shrink-0 text-zinc-600"></i></a>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-800 p-4 text-center text-xs text-zinc-600">No followed players are online right now.</div>
                    @endforelse
                </div>
            </section>

            <section class="w-full max-w-full overflow-hidden rounded-2xl border border-violet-500/15 bg-zinc-950/60">
                <header class="flex min-w-0 flex-wrap items-center justify-between gap-3 border-b border-zinc-800 px-5 py-4"><div class="min-w-0"><p class="break-words text-[9px] font-black uppercase tracking-[0.22em] text-violet-400">Live community</p><h2 class="mt-1 break-words font-orbitron text-sm font-black uppercase text-white">Global Chat</h2></div><a href="/chat" wire:navigate class="shrink-0 text-[9px] font-black uppercase tracking-wider text-violet-400">Open chat →</a></header>
                <div class="space-y-3 p-4">
                    @forelse($globalMessages as $message)
                        <div class="flex items-start gap-2.5"><div class="h-7 w-7 shrink-0 overflow-hidden rounded-full bg-zinc-800">@if($message['avatar_url'])<img src="{{ $message['avatar_url'] }}" alt="" class="h-full w-full object-cover">@else<div class="flex h-full w-full items-center justify-center text-[8px] font-black text-zinc-500">{{ strtoupper(substr($message['username'], 0, 2)) }}</div>@endif</div><div class="min-w-0"><p class="break-words text-[10px] font-black text-violet-300">{{ $message['display_name'] }}</p><p class="break-words text-xs leading-5 text-zinc-400">{{ $message['body'] }}</p></div></div>
                    @empty
                        <p class="py-4 text-center text-xs text-zinc-600">Global chat is quiet. Start the conversation.</p>
                    @endforelse
                </div>
            </section>

            <section class="flex w-full max-w-full snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:grid sm:grid-cols-2 sm:overflow-visible sm:pb-0">
                <a href="/wallet" wire:navigate class="min-w-[13rem] snap-start rounded-2xl border border-emerald-500/15 bg-emerald-500/5 p-4 transition hover:bg-emerald-500/10 sm:min-w-0"><i data-lucide="wallet" class="h-5 w-5 text-emerald-400"></i><p class="mt-3 text-[9px] font-black uppercase tracking-wider text-zinc-600">Balance</p><p class="mt-1 break-words font-orbitron text-lg font-black text-emerald-300">${{ number_format($stats['balance'], 2) }}</p></a>
                <a href="/profile" wire:navigate class="min-w-[13rem] snap-start rounded-2xl border border-cyan-500/15 bg-cyan-500/5 p-4 transition hover:bg-cyan-500/10 sm:min-w-0"><i data-lucide="shield-check" class="h-5 w-5 text-cyan-400"></i><p class="mt-3 text-[9px] font-black uppercase tracking-wider text-zinc-600">Completed</p><p class="mt-1 break-words font-orbitron text-lg font-black text-cyan-300">{{ $progression->tournaments_completed }} events</p></a>
            </section>
        </aside>
    </div>
</div>
