<div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div>
            <h1 class="text-3xl md:text-5xl font-black font-orbitron tracking-tighter bg-gradient-to-r from-cyan-400 via-violet-500 to-fuchsia-500 bg-clip-text text-transparent filter drop-shadow-[0_0_10px_rgba(124,77,255,0.3)]">
                MY TOURNAMENTS
            </h1>
            <p class="text-sm text-zinc-400 mt-2 font-medium">
                Track your active combat deployments and historical match archives.
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
        @if($tournaments->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($tournaments as $tournament)
                    <x-player.tournament-card :tournament="$tournament" action-label="Enter Tournament Hub" />
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
        <!-- History List View -->
        @if($tournaments->count() > 0)
            <div class="space-y-6">
                @foreach($tournaments as $tournament)
                    @php
                        $userReg = $tournament->registrations->first();
                        $matchesForTournament = $userMatches->get($tournament->id) ?? collect();
                    @endphp

                    <div class="bg-zinc-900/40 backdrop-blur-md border border-zinc-800/80 rounded-3xl p-6 md:p-8 flex flex-col lg:flex-row lg:items-center justify-between gap-6 hover:border-zinc-700/60 transition-all duration-300">
                        <!-- Tournament Info -->
                        <div class="space-y-3 min-w-[280px] lg:max-w-xs">
                            <div class="flex items-center space-x-3">
                                <span class="text-[9px] font-black text-cyan-400 uppercase tracking-widest bg-zinc-950/85 border border-cyan-800/50 rounded-full px-2.5 py-1">
                                    {{ $tournament->game->localizedName() }}
                                </span>
                                @php
                                    $histColors = [
                                        'COMPLETED' => 'text-zinc-400 border-zinc-800 bg-zinc-950/80',
                                        'CANCELLED' => 'text-red-400 border-red-900/50 bg-red-950/20',
                                        'REFUNDED' => 'text-orange-400 border-orange-900/50 bg-orange-950/20',
                                    ];
                                    $histVal = $tournament->status->value ?? $tournament->status;
                                    $histColor = $histColors[$histVal] ?? 'text-zinc-400 border-zinc-800 bg-zinc-950/80';
                                @endphp
                                <span class="text-[9px] font-black uppercase tracking-widest border rounded-full px-2.5 py-1 {{ $histColor }}">
                                    {{ str_replace('_', ' ', $histVal) }}
                                </span>
                            </div>
                            
                            <h3 class="text-xl font-black text-white font-orbitron tracking-wide leading-tight">
                                {{ $tournament->name }}
                            </h3>

                            <div class="flex items-center space-x-2 text-[10px] text-zinc-500 font-bold uppercase tracking-wider">
                                <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                <span>Ended: {{ $tournament->completed_at ? $tournament->completed_at->format('M d, Y') : ($tournament->cancelled_at ? $tournament->cancelled_at->format('M d, Y') : 'N/A') }}</span>
                            </div>
                        </div>

                        <!-- Match Records -->
                        <div class="flex-grow">
                            @if($matchesForTournament->count() > 0)
                                <div class="space-y-3">
                                    <span class="block text-[9px] font-bold text-zinc-650 uppercase tracking-[0.2em] mb-1">Match History</span>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        @foreach($matchesForTournament as $match)
                                            @php
                                                $matchStatus = $match->status->value ?? $match->status;
                                                $opponent = $match->player_a_registration_id === $userReg->id 
                                                    ? ($match->playerBRegistration?->user?->username ?? 'Waiting...')
                                                    : ($match->playerARegistration?->user?->username ?? 'Waiting...');
                                                
                                                $isWinner = $match->winner_registration_id === $userReg->id;
                                                $isLoser = $match->winner_registration_id && $match->winner_registration_id !== $userReg->id;
                                            @endphp
                                            <div class="bg-zinc-950/60 border border-zinc-800/80 rounded-2xl p-4 flex items-center justify-between gap-4">
                                                <div class="truncate">
                                                    <span class="block text-[9px] font-bold text-zinc-500 uppercase tracking-widest">Round {{ $match->round->round_number }}</span>
                                                    <span class="block text-xs font-bold text-zinc-200 truncate">vs {{ $opponent }}</span>
                                                </div>

                                                <div>
                                                    @if($isWinner)
                                                        <span class="flex items-center space-x-1 text-[9px] font-black uppercase tracking-widest bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-full px-3 py-1">
                                                            <i data-lucide="trophy" class="w-3 h-3"></i>
                                                            <span>WON</span>
                                                        </span>
                                                    @elseif($isLoser)
                                                        <span class="flex items-center space-x-1 text-[9px] font-black uppercase tracking-widest bg-red-500/10 border border-red-500/30 text-red-400 rounded-full px-3 py-1">
                                                            <i data-lucide="skull" class="w-3 h-3"></i>
                                                            <span>LOST</span>
                                                        </span>
                                                    @else
                                                        <span class="text-[9px] font-black uppercase tracking-widest bg-zinc-800 border border-zinc-700 text-zinc-400 rounded-full px-3 py-1">
                                                            {{ str_replace('_', ' ', $matchStatus) }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="bg-zinc-950/40 border border-dashed border-zinc-800/80 rounded-2xl p-4 text-center">
                                    <span class="text-xs font-medium text-zinc-500">No matches contested in this event.</span>
                                </div>
                            @endif
                        </div>

                        <!-- Action -->
                        <div class="flex items-center lg:justify-end min-w-[150px]">
                            <a href="/tournaments/{{ $tournament->uuid }}/view" wire:navigate
                               class="w-full lg:w-auto text-center px-6 py-3 rounded-xl border border-zinc-800 hover:border-cyan-500/50 text-[10px] font-black uppercase tracking-widest text-zinc-400 hover:text-white hover:bg-cyan-500/10 transition-all duration-300 whitespace-nowrap">
                                View Tournament
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="player-empty-state">
                <div class="player-empty-icon">
                    <i data-lucide="ghost" class="w-10 h-10"></i>
                </div>
                <h3 class="text-xl font-black text-zinc-200 font-orbitron tracking-wider">NO HISTORY FOUND</h3>
                <p class="mt-2 text-sm text-zinc-500 max-w-sm mx-auto font-medium">
                    You have no past tournaments logged in this console directory. Complete active events to write history files.
                </p>
            </div>
        @endif
    @endif

    <!-- Pagination -->
    <div class="mt-12 py-6 border-t border-zinc-900/50">
        {{ $tournaments->links() }}
    </div>
</div>
