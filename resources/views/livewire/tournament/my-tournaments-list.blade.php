<div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div>
            <h1 class="text-3xl md:text-5xl font-black font-orbitron tracking-tighter bg-gradient-to-r from-cyan-400 via-violet-500 to-fuchsia-500 bg-clip-text text-transparent filter drop-shadow-[0_0_10px_rgba(124,77,255,0.3)]">
                MY TOURNAMENTS
            </h1>
            <p class="text-sm text-zinc-400 mt-2 font-medium">
                Track your active tournaments, Match Rooms, and completed results.
            </p>
        </div>
    </div>

    <!-- Stats Banner -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
        <x-player.metric-card label="Active" :value="$activeCount" tone="text-cyan-400" icon="zap" />
        <x-player.metric-card label="History" :value="$historyCount" tone="text-violet-400" icon="calendar" />
        <x-player.metric-card label="Victories" :value="$matchWins" tone="text-emerald-400" icon="trophy" />
        <x-player.metric-card label="Defeats" :value="$matchLosses" tone="text-rose-400" icon="skull" />
    </div>

    <!-- Tabs -->
    <div class="flex items-center space-x-2 bg-zinc-900/40 backdrop-blur-md border border-zinc-800/60 p-1.5 rounded-2xl max-w-fit">
        <button wire:click="$set('tSubTab', 'active')" 
                class="px-8 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 {{ $tSubTab === 'active' ? 'bg-zinc-800 text-white shadow-lg' : 'text-zinc-500 hover:text-zinc-300' }}">
            Active ({{ $activeCount }})
        </button>
        <button wire:click="$set('tSubTab', 'history')" 
                class="px-8 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 {{ $tSubTab === 'history' ? 'bg-zinc-800 text-white shadow-lg' : 'text-zinc-500 hover:text-zinc-300' }}">
            History ({{ $historyCount }})
        </button>
    </div>

    <!-- Active Tournaments Tab -->
    @if($tSubTab === 'active')
        @if($activeMatchRooms->isNotEmpty())
            <section class="rounded-2xl border border-cyan-500/25 bg-cyan-500/5 p-4 sm:p-5">
                <div class="mb-3 flex items-center gap-2"><i data-lucide="swords" class="h-4 w-4 text-cyan-300"></i><h2 class="text-xs font-black uppercase tracking-widest text-white">Your Match Rooms</h2></div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($activeMatchRooms as $match)
                        <a href="/matches/{{ $match->uuid }}" wire:navigate class="flex items-center justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-950/70 p-3 transition hover:border-cyan-500/40">
                            <div class="min-w-0"><p class="truncate text-xs font-bold text-zinc-100">{{ $match->tournament->name }}</p><p class="mt-1 text-[9px] font-black uppercase tracking-wider text-cyan-400">{{ str_replace('_', ' ', $match->status->value) }}</p></div>
                            <span class="shrink-0 text-[9px] font-black uppercase text-cyan-300">Open →</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
        @if($tournaments->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($tournaments as $tournament)
                    <x-player.tournament-card :tournament="$tournament" action-label="View Tournament" />
                @endforeach
            </div>
        @else
            <div class="player-empty-state">
                <div class="player-empty-icon">
                    <i data-lucide="ghost" class="w-10 h-10"></i>
                </div>
                <h3 class="text-xl font-black text-zinc-200 font-orbitron tracking-wider">NO ACTIVE TOURNAMENTS</h3>
                <p class="mt-2 text-sm text-zinc-500 max-w-sm mx-auto font-medium">
                    You haven't joined any active tournaments yet. Head over to browse page to find one!
                </p>
                <a href="/tournaments/browse" wire:navigate class="mt-6 inline-flex items-center space-x-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-xs font-bold uppercase tracking-widest text-white rounded-xl transition-all shadow-lg">
                    <span>Browse Tournaments</span>
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
            </div>
        @endif
    @else
        <!-- Unified Match History -->
        @if($historyMatches->count() > 0)
            <div class="grid gap-4 md:grid-cols-2">
                @foreach($historyMatches as $historyMatch)
                    @php
                        $isTournamentMatch = $historyMatch['type'] === 'tournament';
                        $resultClasses = match($historyMatch['result']) {
                            'won' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400',
                            'lost' => 'border-red-500/30 bg-red-500/10 text-red-400',
                            default => 'border-zinc-700 bg-zinc-800 text-zinc-400',
                        };
                    @endphp
                    <a href="{{ $historyMatch['href'] }}" wire:navigate class="group rounded-2xl border border-zinc-800/80 bg-zinc-900/50 p-5 transition hover:border-cyan-500/35 hover:bg-zinc-900/80">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-widest {{ $isTournamentMatch ? 'border-cyan-700/60 bg-cyan-950/30 text-cyan-300' : 'border-fuchsia-700/60 bg-fuchsia-950/30 text-fuchsia-300' }}">
                                    {{ $historyMatch['label'] }}
                                </span>
                                <span class="rounded-full border {{ $resultClasses }} px-2.5 py-1 text-[9px] font-black uppercase tracking-widest">{{ $historyMatch['result'] }}</span>
                            </div>
                            <i data-lucide="arrow-up-right" class="h-4 w-4 text-zinc-600 transition-colors group-hover:text-cyan-300"></i>
                        </div>
                        <div class="mt-4">
                            <p class="text-[10px] font-black uppercase tracking-wider text-zinc-500">{{ $historyMatch['game'] }}{{ $historyMatch['round'] ? ' · Round '.$historyMatch['round'] : '' }}</p>
                            <h3 class="mt-1 truncate text-base font-black text-white">vs {{ $historyMatch['opponent'] }}</h3>
                            @if($historyMatch['tournament'])<p class="mt-1 truncate text-xs text-zinc-500">{{ $historyMatch['tournament'] }}</p>@endif
                        </div>
                        <div class="mt-4 flex items-center justify-between border-t border-zinc-800/70 pt-3 text-[10px] font-bold uppercase tracking-wider text-zinc-600">
                            <span>{{ $historyMatch['date']?->format('M d, Y · g:i A') }}</span>
                            <span class="text-cyan-400">View Match</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="player-empty-state">
                <div class="player-empty-icon">
                    <i data-lucide="ghost" class="w-10 h-10"></i>
                </div>
                <h3 class="text-xl font-black text-zinc-200 font-orbitron tracking-wider">NO HISTORY FOUND</h3>
                <p class="mt-2 text-sm text-zinc-500 max-w-sm mx-auto font-medium">
                    Completed tournament matches and head-to-head duels will appear here.
                </p>
            </div>
        @endif
    @endif

    <!-- Pagination -->
    <div class="mt-12 py-6 border-t border-zinc-900/50">
        {{ $tSubTab === 'active' ? $tournaments->links() : $historyMatches->links() }}
    </div>
</div>
