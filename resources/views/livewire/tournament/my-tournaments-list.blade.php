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
        <!-- Active -->
        <div class="relative group overflow-hidden bg-zinc-900/40 backdrop-blur-md border border-zinc-800/80 rounded-2xl p-5 shadow-lg">
            <div class="absolute -bottom-10 -right-10 w-24 h-24 bg-cyan-500/10 rounded-full blur-2xl group-hover:bg-cyan-500/20 transition-all"></div>
            <div class="flex items-center space-x-3 text-cyan-400">
                <i data-lucide="zap" class="w-5 h-5"></i>
                <span class="text-[9px] font-black uppercase tracking-[0.2em]">Active</span>
            </div>
            <div class="text-3xl font-black font-orbitron text-white mt-2">{{ $activeCount }}</div>
        </div>

        <!-- History -->
        <div class="relative group overflow-hidden bg-zinc-900/40 backdrop-blur-md border border-zinc-800/80 rounded-2xl p-5 shadow-lg">
            <div class="absolute -bottom-10 -right-10 w-24 h-24 bg-violet-500/10 rounded-full blur-2xl group-hover:bg-violet-500/20 transition-all"></div>
            <div class="flex items-center space-x-3 text-violet-400">
                <i data-lucide="calendar" class="w-5 h-5"></i>
                <span class="text-[9px] font-black uppercase tracking-[0.2em]">History</span>
            </div>
            <div class="text-3xl font-black font-orbitron text-white mt-2">{{ $historyCount }}</div>
        </div>

        <!-- Victories -->
        <div class="relative group overflow-hidden bg-zinc-900/40 backdrop-blur-md border border-zinc-800/80 rounded-2xl p-5 shadow-lg">
            <div class="absolute -bottom-10 -right-10 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-all"></div>
            <div class="flex items-center space-x-3 text-emerald-400">
                <i data-lucide="trophy" class="w-5 h-5"></i>
                <span class="text-[9px] font-black uppercase tracking-[0.2em]">Victories</span>
            </div>
            <div class="text-3xl font-black font-orbitron text-white mt-2">{{ $matchWins }}</div>
        </div>

        <!-- Defeats -->
        <div class="relative group overflow-hidden bg-zinc-900/40 backdrop-blur-md border border-zinc-800/80 rounded-2xl p-5 shadow-lg">
            <div class="absolute -bottom-10 -right-10 w-24 h-24 bg-rose-500/10 rounded-full blur-2xl group-hover:bg-rose-500/20 transition-all"></div>
            <div class="flex items-center space-x-3 text-rose-400">
                <i data-lucide="skull" class="w-5 h-5"></i>
                <span class="text-[9px] font-black uppercase tracking-[0.2em]">Defeats</span>
            </div>
            <div class="text-3xl font-black font-orbitron text-white mt-2">{{ $matchLosses }}</div>
        </div>
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
                    <!-- Tournament Card (Neon) -->
                    <div class="group relative bg-zinc-900/40 backdrop-blur-md border border-zinc-800/80 rounded-3xl hover:border-violet-500/50 hover:shadow-[0_20px_40px_-15px_rgba(124,77,255,0.25)] transition-all duration-500 hover:-translate-y-2 flex flex-col justify-between overflow-hidden">
                        <!-- Image Banner -->
                        <div class="relative h-44 w-full overflow-hidden">
                            <img src="{{ $tournament->banner_url ?? 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=600&auto=format&fit=crop' }}" 
                                 alt="{{ $tournament->name }}" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                            <div class="absolute inset-0 bg-gradient-to-t from-zinc-950/95 via-transparent to-zinc-950/40"></div>
                            
                            <!-- Badges on top of image -->
                            <div class="absolute top-4 left-4 right-4 flex items-center justify-between">
                                <span class="text-[9px] font-black text-cyan-400 uppercase tracking-widest bg-zinc-950/85 border border-cyan-800/50 rounded-full px-3 py-1.5">
                                    {{ $tournament->game->translations->where('locale', 'en')->first()?->name ?? $tournament->game->slug }}
                                </span>
                                @php
                                    $statusColors = [
                                        'REGISTRATION_OPEN' => 'text-emerald-400 border-emerald-900/50 bg-emerald-950/85 shadow-[0_0_15px_rgba(52,211,153,0.15)]',
                                        'REGISTRATION_CLOSED' => 'text-amber-400 border-amber-900/50 bg-amber-950/85',
                                        'CHECKIN_OPEN' => 'text-fuchsia-400 border-fuchsia-900/50 bg-fuchsia-950/85',
                                        'CHECKIN_CLOSED' => 'text-rose-400 border-rose-900/50 bg-rose-950/85',
                                        'BRACKET_GENERATED' => 'text-indigo-400 border-indigo-900/50 bg-indigo-950/85',
                                        'ONGOING' => 'text-violet-400 border-violet-850/50 bg-violet-950/85 animate-pulse shadow-[0_0_20px_rgba(124,77,255,0.25)]',
                                    ];
                                    $statusVal = $tournament->status->value ?? $tournament->status;
                                    $colorClass = $statusColors[$statusVal] ?? 'text-zinc-505 border-zinc-800 bg-zinc-950/85';
                                @endphp
                                <span class="text-[9px] font-black uppercase tracking-widest border rounded-full px-3 py-1.5 {{ $colorClass }}">
                                    {{ str_replace('_', ' ', $statusVal) }}
                                </span>
                            </div>
                        </div>

                        <!-- Content Area -->
                        <div class="p-6 flex-grow flex flex-col justify-between space-y-5">
                            <!-- Info Area -->
                            <div class="space-y-3">
                                <h3 class="text-xl font-black text-white group-hover:text-cyan-400 transition-colors duration-300 font-orbitron tracking-wide leading-tight line-clamp-2">
                                    {{ $tournament->name }}
                                </h3>
                                <div class="flex items-center justify-between text-[10px] text-zinc-400 font-bold uppercase tracking-wider">
                                    <div class="flex items-center space-x-1.5">
                                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-violet-400"></i>
                                        <span>{{ $tournament->start_at ? $tournament->start_at->format('M d, H:i') : 'TBD' }}</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span>Entry Fee:</span>
                                        <span class="text-xs font-black font-orbitron text-violet-400 tracking-wider">
                                            {{ (float)$tournament->entry_fee > 0 ? '$'.number_format((float)$tournament->entry_fee, 2) : 'FREE ENTRY' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Stats Block -->
                            <div class="grid grid-cols-2 gap-4 pt-4 border-t border-zinc-800/40">
                                <div class="space-y-1">
                                    <span class="block text-[9px] font-bold text-zinc-600 uppercase tracking-[0.2em]">Prize Pool</span>
                                    <div class="flex items-baseline space-x-1">
                                        <span class="text-lg font-black text-fuchsia-500 font-orbitron leading-none">${{ number_format((float)$tournament->prize_pool, 2) }}</span>
                                    </div>
                                </div>
                                <div class="space-y-1 text-right">
                                    <span class="block text-[9px] font-bold text-zinc-650 uppercase tracking-[0.2em]">Slots Left</span>
                                    <div class="flex items-baseline justify-end space-x-1 font-mono">
                                        <span class="text-sm font-bold text-zinc-200">{{ $tournament->registrations()->whereNotIn('status', ['cancelled', 'refunded'])->count() }}</span>
                                        <span class="text-[10px] text-zinc-650">/</span>
                                        <span class="text-[10px] text-zinc-550">{{ $tournament->max_participants }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Action -->
                            <div class="pt-2">
                                <a href="/tournaments/{{ $tournament->uuid }}/view" wire:navigate
                                    class="w-full relative flex items-center justify-center space-x-2 py-3.5 px-6 rounded-2xl bg-zinc-950 border border-zinc-800 group-hover:border-cyan-500/50 text-xs font-black uppercase tracking-[0.2em] text-zinc-400 group-hover:text-white group-hover:bg-cyan-500/10 transition-all duration-300 overflow-hidden">
                                    <div class="absolute inset-0 translate-x-[-100%] group-hover:translate-x-0 bg-gradient-to-r from-transparent via-cyan-500/10 to-transparent transition-transform duration-700 pointer-events-none"></div>
                                    <span>Enter Tournament Hub</span>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-cyan-400 group-hover:translate-x-1 transition-transform duration-300"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-zinc-900/40 backdrop-blur-md border border-zinc-800 rounded-3xl p-16 text-center shadow-2xl relative overflow-hidden">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-zinc-950 border border-zinc-800 text-zinc-600 mb-6">
                    <i data-lucide="ghost" class="w-10 h-10"></i>
                </div>
                <h3 class="text-xl font-black text-zinc-200 font-orbitron tracking-wider">NO ACTIVE TOURNAMENTS</h3>
                <p class="mt-2 text-sm text-zinc-500 max-w-sm mx-auto font-medium">
                    You haven't joined any active tournaments yet. Head over to browse page to find one!
                </p>
                <a href="/tournaments" wire:navigate class="mt-6 inline-flex items-center space-x-2 px-6 py-3 bg-indigo-650 hover:bg-indigo-500 text-xs font-bold uppercase tracking-widest text-white rounded-xl transition-all shadow-lg">
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
                        $userMatches = [];
                        if ($userReg) {
                            $userMatches = \App\Modules\Match\Models\GameMatch::where('tournament_id', $tournament->id)
                                ->where(function($q) use ($userReg) {
                                    $q->where('player_a_registration_id', $userReg->id)
                                      ->orWhere('player_b_registration_id', $userReg->id);
                                })
                                ->with(['round', 'playerARegistration.user', 'playerBRegistration.user', 'winnerRegistration'])
                                ->orderBy('id', 'desc')
                                ->get();
                        }
                    @endphp

                    <div class="bg-zinc-900/40 backdrop-blur-md border border-zinc-800/80 rounded-3xl p-6 md:p-8 flex flex-col lg:flex-row lg:items-center justify-between gap-6 hover:border-zinc-700/60 transition-all duration-300">
                        <!-- Tournament Info -->
                        <div class="space-y-3 min-w-[280px] lg:max-w-xs">
                            <div class="flex items-center space-x-3">
                                <span class="text-[9px] font-black text-cyan-400 uppercase tracking-widest bg-zinc-950/85 border border-cyan-800/50 rounded-full px-2.5 py-1">
                                    {{ $tournament->game->translations->where('locale', 'en')->first()?->name ?? $tournament->game->slug }}
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
                            @if(count($userMatches) > 0)
                                <div class="space-y-3">
                                    <span class="block text-[9px] font-bold text-zinc-650 uppercase tracking-[0.2em] mb-1">Match History</span>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        @foreach($userMatches as $match)
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
            <div class="bg-zinc-900/40 backdrop-blur-md border border-zinc-800 rounded-3xl p-16 text-center shadow-2xl relative overflow-hidden">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-zinc-950 border border-zinc-800 text-zinc-600 mb-6">
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
