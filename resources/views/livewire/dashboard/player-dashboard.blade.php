<div class="space-y-8 min-w-0" x-data="{
    activeTab: @entangle('tab'),
    adMuted: true,
    adPaused: false,
    adLoaded: false,
    init() {
        setTimeout(() => {
            this.adLoaded = true;
        }, 1500);
    },
    playAd() {
        this.adPaused = false;
        let iframe = this.$refs.adIframe;
        if (iframe) {
            iframe.src = 'https://www.youtube.com/embed/5D-M5M_Lw1M?autoplay=1&mute=' + (this.adMuted ? 1 : 0) + '&loop=1&playlist=5D-M5M_Lw1M&controls=0&modestbranding=1';
        }
    },
    toggleMute() {
        this.adMuted = !this.adMuted;
        this.playAd();
    },
    togglePlay() {
        this.adPaused = !this.adPaused;
        let iframe = this.$refs.adIframe;
        if (iframe) {
            if (this.adPaused) {
                iframe.src = '';
            } else {
                this.playAd();
            }
        }
    }
}">

    <!-- Top Navigation Alert / Status Ribbon -->
    <div class="bg-gradient-to-r from-purple-900/20 via-fuchsia-950/20 to-transparent border border-purple-500/15 rounded-xl p-3 flex items-center justify-between text-xs text-purple-300 shadow-[inset_0_0_10px_rgba(168,85,247,0.05)]">
        <div class="flex items-center space-x-2">
            <span class="flex h-2 w-2 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-fuchsia-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-fuchsia-500"></span>
            </span>
            <span class="font-orbitron tracking-widest text-[10px] uppercase font-bold text-fuchsia-400">NETWORK ONLINE</span>
            <span class="text-zinc-400 hidden sm:inline">|</span>
            <span class="text-zinc-400 font-medium hidden sm:inline">Viking Esports Open Tournament registrations close in 2 hours.</span>
        </div>
        <a href="/dashboard?tab=tournaments" class="text-fuchsia-400 hover:text-fuchsia-300 font-black uppercase tracking-wider text-[10px] flex items-center space-x-1">
            <span>Register Now</span>
            <i data-lucide="arrow-right" class="w-3 h-3"></i>
        </a>
    </div>

    <!-- ---------------------------------------------------- -->
    <!-- TAB 1: OVERVIEW                                      -->
    <!-- ---------------------------------------------------- -->
    <template x-if="activeTab === 'overview'">
        <div class="space-y-6">
            
            <!-- Greeting Banner -->
            <div class="bg-gradient-to-r from-[#170e30] via-[#0e0a24] to-transparent border border-purple-500/20 rounded-2xl p-6 md:p-8 shadow-[0_10px_30px_rgba(0,0,0,0.5),inset_0_0_20px_rgba(168,85,247,0.05)] relative overflow-hidden">
                <!-- Glowing sci-fi elements -->
                <div class="absolute -top-20 -right-20 w-60 h-60 bg-purple-600/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute top-0 right-0 w-24 h-24 border-t-2 border-r-2 border-purple-500/20 rounded-tr-2xl pointer-events-none"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 border-b-2 border-l-2 border-purple-500/20 rounded-bl-2xl pointer-events-none"></div>
                
                <div class="max-w-2xl">
                    <span class="text-[9px] font-bold text-fuchsia-400 uppercase tracking-[0.3em] font-orbitron bg-fuchsia-950/50 border border-fuchsia-900/60 px-3 py-1 rounded-full">
                        ACTIVE SOLDIER // TIER 1
                    </span>
                    <h2 class="text-3xl md:text-5xl font-black font-orbitron tracking-wider text-white uppercase mt-4 filter drop-shadow-[0_0_8px_rgba(168,85,247,0.3)]">
                        WELCOME BACK, {{ $user->username }}!
                    </h2>
                    <p class="text-xs md:text-sm text-zinc-400 mt-2 font-medium">
                        Your battle station is ready. Join head-to-head duels, enter ongoing leagues, track your stats and claim real cash prizes instantly.
                    </p>
                </div>
            </div>

            <!-- Ad Section & Main Player Stats Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Ad Video Display Container -->
                <div class="lg:col-span-2 bg-[#0c081d] border border-purple-500/15 rounded-2xl shadow-xl overflow-hidden relative group/ad min-h-[280px] flex flex-col justify-between">
                    <!-- High-tech HUD Overlay -->
                    <div class="absolute top-3 left-3 z-30 bg-[#0a0718]/80 border border-purple-500/30 px-3 py-1 rounded-md flex items-center space-x-2 text-[9px] font-orbitron text-purple-300 font-bold uppercase tracking-widest shadow-lg">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-ping"></span>
                        <span>SPONSORED BROADCAST</span>
                    </div>

                    <!-- HUD Graphic Elements -->
                    <div class="absolute inset-0 border border-purple-500/10 pointer-events-none z-20 m-2 rounded-xl"></div>
                    <div class="absolute top-2 right-2 z-20 text-[8px] font-mono text-purple-400/40 pointer-events-none">SYS.LOC // AD-209</div>
                    <div class="absolute bottom-2 left-2 z-20 text-[8px] font-mono text-purple-400/40 pointer-events-none">TARGET: GENERAL // EN_US</div>

                    <!-- Video Source Container -->
                    <div class="absolute inset-0 z-10 w-full h-full bg-[#05030b]">
                        <template x-if="adLoaded && !adPaused">
                            <iframe 
                                x-ref="adIframe"
                                src="https://www.youtube.com/embed/5D-M5M_Lw1M?autoplay=1&mute=1&loop=1&playlist=5D-M5M_Lw1M&controls=0&modestbranding=1" 
                                class="w-full h-full object-cover scale-[1.3] pointer-events-none opacity-80"
                                frameborder="0" 
                                allow="autoplay; encrypted-media" 
                                allowfullscreen>
                            </iframe>
                        </template>
                        <template x-if="!adLoaded">
                            <div class="w-full h-full flex flex-col items-center justify-center bg-zinc-950/80 border border-purple-500/10 relative">
                                <div class="absolute inset-0 scanlines opacity-20 pointer-events-none"></div>
                                <div class="animate-pulse flex flex-col items-center">
                                    <i data-lucide="zap" class="w-10 h-10 text-fuchsia-500 mb-2"></i>
                                    <span class="text-[9px] font-bold text-zinc-500 font-orbitron tracking-[0.25em] uppercase">CONNECTING LINK...</span>
                                </div>
                            </div>
                        </template>
                        <template x-if="adLoaded && adPaused">
                            <div class="w-full h-full flex flex-col items-center justify-center bg-zinc-950 border border-purple-500/10">
                                <i data-lucide="play-circle" class="w-16 h-16 text-purple-500 animate-pulse"></i>
                                <span class="text-xs text-zinc-500 mt-2 font-orbitron font-bold tracking-widest">TRANSMISSION PAUSED</span>
                            </div>
                        </template>
                    </div>

                    <!-- Bottom Controls & Overlay text -->
                    <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black via-black/60 to-transparent p-4 z-30 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-bold text-white font-orbitron tracking-wider">SALOONS PRO LEAGUE V</h3>
                            <p class="text-[10px] text-purple-300">Join the fight next weekend. Prize pool $10,000.</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <!-- Play/Pause Toggle -->
                            <button @click="togglePlay()" class="p-1.5 bg-[#0a0718]/80 hover:bg-purple-950 border border-purple-500/30 rounded-lg text-purple-400 hover:text-white transition-colors">
                                <i :data-lucide="adPaused ? 'play' : 'pause'" class="w-4 h-4"></i>
                            </button>
                            <!-- Audio Toggle -->
                            <button @click="toggleMute()" class="p-1.5 bg-[#0a0718]/80 hover:bg-purple-950 border border-purple-500/30 rounded-lg text-purple-400 hover:text-white transition-colors">
                                <i :data-lucide="adMuted ? 'volume-x' : 'volume-2'" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Player Profile Stats -->
                <div class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 shadow-xl flex flex-col gap-5 relative overflow-hidden">
                    <div class="absolute -top-20 -left-20 w-40 h-40 bg-fuchsia-600/5 rounded-full blur-2xl pointer-events-none"></div>

                    <!-- Ranking Section — not yet implemented -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-zinc-500 font-bold uppercase tracking-wider font-orbitron">PLAYER RANKING</span>
                            <span class="text-[9px] text-zinc-600 font-bold font-orbitron bg-zinc-900 border border-zinc-800 px-2 py-0.5 rounded-full">COMING SOON</span>
                        </div>
                        <!-- Rank bar — greyed out, system not yet active -->
                        <div class="space-y-1">
                            <div class="h-2 w-full bg-zinc-950 rounded-full border border-zinc-800 overflow-hidden p-[1px]">
                                <div class="h-full bg-zinc-800 rounded-full" style="width: 0%;"></div>
                            </div>
                            <div class="flex justify-between text-[8px] text-zinc-600 font-bold font-mono">
                                <span>0 XP</span>
                                <span>Ranking system not yet active</span>
                            </div>
                        </div>
                    </div>

                    <!-- Real Stats Grid -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-zinc-950/60 border border-purple-500/10 rounded-xl p-3 text-center">
                            <span class="block text-[9px] text-zinc-500 font-bold uppercase tracking-wider">WIN RATE</span>
                            @if($playerStats['total_matches'] > 0)
                                <span class="text-lg font-black text-cyan-400 font-orbitron block mt-1 filter drop-shadow-[0_0_5px_rgba(34,211,238,0.2)]">{{ $playerStats['win_rate'] }}%</span>
                            @else
                                <span class="text-lg font-black text-zinc-600 font-orbitron block mt-1">—</span>
                            @endif
                        </div>
                        <div class="bg-zinc-950/60 border border-purple-500/10 rounded-xl p-3 text-center">
                            <span class="block text-[9px] text-zinc-500 font-bold uppercase tracking-wider">STREAK</span>
                            <span class="text-lg font-black text-zinc-600 font-orbitron block mt-1">—</span>
                            <span class="text-[8px] text-zinc-700 font-mono">Coming soon</span>
                        </div>
                        <div class="bg-zinc-950/60 border border-purple-500/10 rounded-xl p-3 text-center">
                            <span class="block text-[9px] text-zinc-500 font-bold uppercase tracking-wider">PRIZE WON</span>
                            <span class="text-lg font-black text-emerald-400 font-orbitron block mt-1 filter drop-shadow-[0_0_5px_rgba(16,185,129,0.2)]">${{ number_format($playerStats['earnings'], 2) }}</span>
                        </div>
                        <div class="bg-zinc-950/60 border border-purple-500/10 rounded-xl p-3 text-center">
                            <span class="block text-[9px] text-zinc-500 font-bold uppercase tracking-wider">BATTLES</span>
                            <span class="text-lg font-black text-purple-400 font-orbitron block mt-1">{{ $playerStats['total_matches'] }}</span>
                        </div>
                    </div>

                    <!-- Win / Loss breakdown -->
                    <div class="bg-zinc-950/40 border border-purple-500/10 rounded-xl p-3.5">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-[9px] font-black uppercase tracking-wider font-orbitron text-zinc-400">MATCH RECORD</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 text-center">
                                <span class="block text-xl font-black font-orbitron text-emerald-400">{{ $playerStats['wins'] }}</span>
                                <span class="text-[9px] text-zinc-500 font-bold uppercase tracking-wider">WINS</span>
                            </div>
                            <div class="w-px h-10 bg-zinc-800"></div>
                            <div class="flex-1 text-center">
                                <span class="block text-xl font-black font-orbitron text-red-400">{{ $playerStats['losses'] }}</span>
                                <span class="text-[9px] text-zinc-500 font-bold uppercase tracking-wider">LOSSES</span>
                            </div>
                            <div class="w-px h-10 bg-zinc-800"></div>
                            <div class="flex-1 text-center">
                                <span class="block text-xl font-black font-orbitron text-zinc-400">{{ $playerStats['total_matches'] }}</span>
                                <span class="text-[9px] text-zinc-500 font-bold uppercase tracking-wider">TOTAL</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Tournaments & Recent Matches Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Left: Active Tournaments -->
                <div class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-purple-500/10 pb-3">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="trophy" class="w-4.5 h-4.5 text-purple-400"></i>
                            <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase">
                                MY ACTIVE TOURNAMENTS
                            </h3>
                        </div>
                        <span class="text-[10px] bg-purple-950 text-purple-400 px-2 py-0.5 rounded font-bold font-mono">{{ $activeTournaments->count() }}</span>
                    </div>

                    @if($activeTournaments->count() > 0)
                        <div class="space-y-3">
                            @foreach($activeTournaments as $tournament)
                                @php
                                    $statusColors = [
                                        'REGISTRATION_OPEN'   => 'text-emerald-400 bg-emerald-950/40 border-emerald-900/60',
                                        'REGISTRATION_CLOSED' => 'text-yellow-400 bg-yellow-950/40 border-yellow-900/60',
                                        'CHECKIN_OPEN'        => 'text-cyan-400 bg-cyan-950/40 border-cyan-900/60',
                                        'CHECKIN_CLOSED'      => 'text-orange-400 bg-orange-950/40 border-orange-900/60',
                                        'BRACKET_GENERATED'   => 'text-blue-400 bg-blue-950/40 border-blue-900/60',
                                        'ONGOING'             => 'text-fuchsia-400 bg-fuchsia-950/40 border-fuchsia-900/60',
                                        'PUBLISHED'           => 'text-violet-400 bg-violet-950/40 border-violet-900/60',
                                    ];
                                    $statusLabel = match($tournament->status->value) {
                                        'REGISTRATION_OPEN'   => 'REG OPEN',
                                        'REGISTRATION_CLOSED' => 'REG CLOSED',
                                        'CHECKIN_OPEN'        => 'CHECK-IN',
                                        'CHECKIN_CLOSED'      => 'CHECKIN CLOSED',
                                        'BRACKET_GENERATED'   => 'BRACKET SET',
                                        'ONGOING'             => 'LIVE',
                                        'PUBLISHED'           => 'UPCOMING',
                                        default               => $tournament->status->value,
                                    };
                                    $statusClass = $statusColors[$tournament->status->value] ?? 'text-zinc-400 bg-zinc-900/40 border-zinc-800';
                                @endphp
                                <div class="bg-zinc-950/60 border border-purple-500/10 hover:border-purple-500/25 rounded-xl p-4 flex items-center justify-between gap-4 transition-all duration-200 group/row">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 mb-1.5">
                                            <span class="text-[8px] font-bold text-fuchsia-400 uppercase tracking-widest bg-fuchsia-950/50 border border-fuchsia-900/60 rounded px-2 py-0.5 font-orbitron flex-shrink-0">
                                                {{ $tournament->game->translations->where('locale', 'en')->first()?->name ?? $tournament->game->slug }}
                                            </span>
                                            <span class="text-[8px] font-bold uppercase tracking-widest rounded px-2 py-0.5 font-orbitron border flex-shrink-0 {{ $statusClass }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </div>
                                        <h4 class="text-xs font-bold text-zinc-200 truncate">
                                            {{ $tournament->name }}
                                        </h4>
                                        @if($tournament->start_at)
                                            <span class="text-[9px] text-zinc-500 font-mono mt-1 block">
                                                Starts {{ $tournament->start_at->format('M d, H:i') }}
                                            </span>
                                        @endif
                                    </div>
                                    <a href="/tournaments/{{ $tournament->uuid }}" wire:navigate class="flex-shrink-0 bg-gradient-to-r from-purple-900 to-fuchsia-950 hover:from-purple-800 hover:to-fuchsia-800 border border-purple-500/30 text-[9px] font-black font-orbitron uppercase tracking-widest text-purple-200 py-2 px-3 rounded-lg transition-all flex items-center space-x-1.5 shadow-[0_0_10px_rgba(168,85,247,0.1)] group-hover/row:shadow-[0_0_15px_rgba(168,85,247,0.3)]">
                                        <span>VIEW</span>
                                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12 text-zinc-500 bg-zinc-950/30 border border-dashed border-purple-500/10 rounded-xl">
                            <i data-lucide="trophy" class="w-8 h-8 mx-auto text-zinc-750 mb-3 animate-bounce"></i>
                            <p class="text-xs font-bold">No active tournament registrations.</p>
                            <button @click="activeTab = 'tournaments'" class="text-[10px] font-black text-fuchsia-400 hover:text-fuchsia-300 mt-2 uppercase tracking-widest font-orbitron">
                                Browse Tournaments &rarr;
                            </button>
                        </div>
                    @endif
                </div>

                <!-- Right: Recent Matches -->
                <div class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-purple-500/10 pb-3">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="swords" class="w-4.5 h-4.5 text-purple-400"></i>
                            <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase">
                                RECENT BATTLE LOGS
                            </h3>
                        </div>
                        <span class="text-[10px] bg-purple-950 text-purple-400 px-2 py-0.5 rounded font-bold font-mono">{{ $activeMatches->count() }}</span>
                    </div>

                    @if($activeMatches->count() > 0)
                        <div class="space-y-3">
                            @foreach($activeMatches as $match)
                                @php
                                    $isPlayerA = auth()->id() === $match->playerARegistration?->user_id;
                                    $opponent  = $isPlayerA ? $match->playerBRegistration?->user : $match->playerARegistration?->user;
                                    $isWinner  = $match->winnerRegistration && auth()->id() === $match->winnerRegistration->user_id;
                                    $isLoser   = $match->winnerRegistration && !$isWinner && in_array($match->status->value, ['completed', 'forfeited']);
                                    $matchStatusMap = [
                                        'pending'          => ['label' => 'PENDING',    'class' => 'text-zinc-400 bg-zinc-900/60 border-zinc-800'],
                                        'ready'            => ['label' => 'READY',      'class' => 'text-cyan-400 bg-cyan-950/40 border-cyan-900/60'],
                                        'in_progress'      => ['label' => 'LIVE',       'class' => 'text-fuchsia-400 bg-fuchsia-950/40 border-fuchsia-900/60'],
                                        'result_submitted' => ['label' => 'SUBMITTED',  'class' => 'text-yellow-400 bg-yellow-950/40 border-yellow-900/60'],
                                        'completed'        => $isWinner ? ['label' => 'WIN ✓', 'class' => 'text-emerald-400 bg-emerald-950/40 border-emerald-900/60'] : ['label' => 'LOSS', 'class' => 'text-red-400 bg-red-950/40 border-red-900/60'],
                                        'disputed'         => ['label' => 'DISPUTED',   'class' => 'text-orange-400 bg-orange-950/40 border-orange-900/60'],
                                        'forfeited'        => ['label' => 'FORFEIT',    'class' => 'text-red-400 bg-red-950/40 border-red-900/60'],
                                    ];
                                    $matchStatusInfo = $matchStatusMap[$match->status->value] ?? ['label' => strtoupper($match->status->value), 'class' => 'text-zinc-400 bg-zinc-900/60 border-zinc-800'];
                                @endphp
                                <div class="bg-zinc-950/60 border border-purple-500/10 hover:border-purple-500/25 rounded-xl p-4 flex items-center justify-between gap-4 transition-all duration-200 group/row">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-[8px] text-zinc-500 font-bold uppercase tracking-wider font-orbitron truncate">
                                                {{ $match->tournament->name }} · R{{ $match->round->round_number }}
                                            </span>
                                            <span class="flex-shrink-0 text-[8px] font-bold uppercase tracking-widest rounded px-2 py-0.5 font-orbitron border {{ $matchStatusInfo['class'] }}">
                                                {{ $matchStatusInfo['label'] }}
                                            </span>
                                        </div>
                                        <span class="block text-xs font-bold text-zinc-200 font-orbitron uppercase">
                                            VS {{ $opponent?->username ?? 'TBD' }}
                                        </span>
                                    </div>
                                    <a href="/matches/{{ $match->uuid }}" wire:navigate class="flex-shrink-0 bg-gradient-to-r from-purple-900 to-fuchsia-950 hover:from-purple-800 hover:to-fuchsia-800 border border-purple-500/30 text-[9px] font-black font-orbitron uppercase tracking-widest text-purple-200 py-2 px-3 rounded-lg transition-all flex items-center space-x-1.5 shadow-[0_0_10px_rgba(168,85,247,0.1)] group-hover/row:shadow-[0_0_15px_rgba(168,85,247,0.3)]">
                                        <span>VIEW</span>
                                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12 text-zinc-500 bg-zinc-950/30 border border-dashed border-purple-500/10 rounded-xl">
                            <i data-lucide="shield" class="w-8 h-8 mx-auto text-zinc-750 mb-3 animate-pulse"></i>
                            <p class="text-xs font-bold">No match history logged yet.</p>
                            <button @click="activeTab = 'head-to-head'" class="text-[10px] font-black text-cyan-400 hover:text-cyan-300 mt-2 uppercase tracking-widest font-orbitron">
                                Queue a Duel &rarr;
                            </button>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </template>

    <!-- ---------------------------------------------------- -->
    <!-- TAB 2: TOURNAMENTS                                   -->
    <!-- ---------------------------------------------------- -->
    <template x-if="activeTab === 'tournaments'">
        <div class="space-y-6" x-data="{ 
            searchFilter: '', 
            activeGame: 'all',
            tournaments: [
                { id: 1, name: 'Apex Legends: Void Run', game: 'Apex Legends', prize: '$1,500', players: '42/64', time: 'June 12, 18:00', fee: 'Free', status: 'OPEN' },
                { id: 2, name: 'Viking Valorant Clash', game: 'Valorant', prize: '$3,000', players: '128/128', time: 'June 10, 19:30', fee: '$5.00', status: 'FULL' },
                { id: 3, name: 'Dota 2 Saloon Championship', game: 'Dota 2', prize: '$5,000', players: '24/32 Teams', time: 'June 15, 15:00', fee: '$10.00', status: 'OPEN' },
                { id: 4, name: 'CS2 Dust2 Duelists', game: 'CS2', prize: '$1,000', players: '16/32 Teams', time: 'June 18, 20:00', fee: '$2.00', status: 'OPEN' },
                { id: 5, name: 'FIFA Arena Showdown', game: 'FIFA 24', prize: '$800', players: '60/64', time: 'June 11, 17:00', fee: 'Free', status: 'OPEN' },
                { id: 6, name: 'Tekken 8 Iron Fist Lobby', game: 'Tekken 8', prize: '$500', players: '8/16', time: 'June 14, 21:00', fee: 'Free', status: 'OPEN' }
            ]
        }">
            <!-- Filters & Search -->
            <div class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-4 flex flex-col md:flex-row items-center justify-between gap-4">
                <!-- Game Tabs -->
                <div class="flex flex-wrap items-center gap-2">
                    <button @click="activeGame = 'all'" :class="activeGame === 'all' ? 'bg-purple-600 text-white' : 'bg-zinc-950 hover:bg-zinc-900 border border-purple-500/10 text-zinc-400'" class="px-3.5 py-1.5 rounded-lg text-xs font-bold font-orbitron uppercase tracking-widest transition-all">
                        All Games
                    </button>
                    <button @click="activeGame = 'Valorant'" :class="activeGame === 'Valorant' ? 'bg-purple-600 text-white' : 'bg-zinc-950 hover:bg-zinc-900 border border-purple-500/10 text-zinc-400'" class="px-3.5 py-1.5 rounded-lg text-xs font-bold font-orbitron uppercase tracking-widest transition-all">
                        Valorant
                    </button>
                    <button @click="activeGame = 'CS2'" :class="activeGame === 'CS2' ? 'bg-purple-600 text-white' : 'bg-zinc-950 hover:bg-zinc-900 border border-purple-500/10 text-zinc-400'" class="px-3.5 py-1.5 rounded-lg text-xs font-bold font-orbitron uppercase tracking-widest transition-all">
                        CS2
                    </button>
                    <button @click="activeGame = 'FIFA 24'" :class="activeGame === 'FIFA 24' ? 'bg-purple-600 text-white' : 'bg-zinc-950 hover:bg-zinc-900 border border-purple-500/10 text-zinc-400'" class="px-3.5 py-1.5 rounded-lg text-xs font-bold font-orbitron uppercase tracking-widest transition-all">
                        FIFA 24
                    </button>
                </div>

                <!-- Search Input -->
                <div class="relative w-full md:w-72">
                    <input type="text" x-model="searchFilter" placeholder="SEARCH TOURNAMENTS..." class="w-full bg-zinc-950 border border-purple-500/20 hover:border-purple-500/40 focus:border-purple-500 focus:outline-none rounded-xl py-2 px-4 text-xs font-bold font-orbitron uppercase tracking-widest text-purple-300 placeholder-zinc-600">
                    <div class="absolute right-3 top-2.5 text-zinc-650">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </div>
                </div>
            </div>

            <!-- Tournaments List Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                
                <template x-for="t in tournaments" :key="t.id">
                    <div x-show="(activeGame === 'all' || t.game === activeGame) && (t.name.toLowerCase().includes(searchFilter.toLowerCase()))"
                         class="bg-[#0c081d] border border-purple-500/15 hover:border-purple-500/35 rounded-2xl p-5 shadow-xl transition-all duration-300 relative group flex flex-col justify-between overflow-hidden">
                        
                        <!-- Glow effect -->
                        <div class="absolute -bottom-20 -right-20 w-40 h-40 bg-purple-500/5 group-hover:bg-purple-500/10 rounded-full blur-2xl transition-all duration-300"></div>

                        <!-- Top Ribbon -->
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-[9px] font-bold text-fuchsia-400 bg-fuchsia-950/40 border border-fuchsia-900/60 px-2 py-0.5 rounded font-orbitron" x-text="t.game"></span>
                            <span :class="t.status === 'OPEN' ? 'text-emerald-400 bg-emerald-950/40 border-emerald-900/60' : 'text-red-400 bg-red-950/40 border-red-900/60'" class="text-[9px] font-bold px-2 py-0.5 rounded font-orbitron border" x-text="t.status"></span>
                        </div>

                        <!-- Main Details -->
                        <div class="space-y-2 mb-5">
                            <h4 class="text-sm font-black font-orbitron tracking-wider text-white uppercase group-hover:text-purple-350 transition-colors" x-text="t.name"></h4>
                            <div class="grid grid-cols-2 gap-2 text-[10px] text-zinc-400 pt-2 font-medium">
                                <div class="flex items-center space-x-1.5">
                                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-purple-400"></i>
                                    <span x-text="t.time"></span>
                                </div>
                                <div class="flex items-center space-x-1.5 justify-end">
                                    <i data-lucide="users" class="w-3.5 h-3.5 text-purple-400"></i>
                                    <span x-text="t.players"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Prize Pool and Action Row -->
                        <div class="border-t border-purple-500/10 pt-4 flex items-center justify-between">
                            <div>
                                <span class="block text-[8px] text-zinc-500 font-bold uppercase tracking-wider">PRIZE POOL</span>
                                <span class="text-base font-black text-emerald-400 font-orbitron tracking-wider" x-text="t.prize"></span>
                            </div>
                            <div>
                                <span class="block text-[8px] text-zinc-500 font-bold uppercase tracking-wider text-right">ENTRY FEE</span>
                                <span class="block text-xs font-bold text-zinc-200 font-orbitron tracking-wider text-right" x-text="t.fee"></span>
                            </div>
                        </div>

                        <!-- Join Button -->
                        <button :disabled="t.status === 'FULL'" 
                                class="w-full mt-4 bg-gradient-to-r from-purple-600 via-fuchsia-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 disabled:from-zinc-900 disabled:to-zinc-900 disabled:text-zinc-650 disabled:border-zinc-800 disabled:shadow-none border border-fuchsia-400/20 text-[10px] font-black font-orbitron uppercase tracking-widest text-white py-2.5 rounded-xl transition-all duration-300 shadow-[0_4px_15px_rgba(168,85,247,0.25)] hover:shadow-[0_4px_20px_rgba(217,70,239,0.5)] cursor-pointer">
                            <span x-text="t.status === 'OPEN' ? 'REGISTER TOURNAMENT' : 'REGISTRATION CLOSED'"></span>
                        </button>
                    </div>
                </template>

            </div>
        </div>
    </template>

    <!-- ---------------------------------------------------- -->
    <!-- TAB 3: HEAD-TO-HEAD                                  -->
    <!-- ---------------------------------------------------- -->
    <template x-if="activeTab === 'head-to-head'">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8" x-data="{
            searching: @entangle('isSearching'),
            selectedGame: @entangle('selectedGame'),
            stake: @entangle('stakeAmount'),
            step: 0,
            matchedUser: @entangle('matchedOpponent'),
            
            init() {
                this.$watch('searching', value => {
                    if (value) {
                        this.step = 1;
                        setTimeout(() => {
                            if (this.searching) {
                                this.step = 2;
                                $wire.simulateMatchFound();
                            }
                        }, 3000);
                    } else {
                        this.step = 0;
                    }
                });
            }
        }">
            
            <!-- Left Side: Initiate Duel & Matchmaker -->
            <div class="lg:col-span-1 bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 shadow-xl flex flex-col justify-between relative overflow-hidden">
                <div class="absolute -top-20 -right-20 w-40 h-40 bg-purple-500/5 rounded-full blur-2xl pointer-events-none"></div>

                <!-- Setup form -->
                <div class="space-y-6">
                    <div class="flex items-center space-x-2 border-b border-purple-500/10 pb-3">
                        <i data-lucide="zap" class="w-5 h-5 text-fuchsia-400"></i>
                        <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase">INITIATE CHALLENGE</h3>
                    </div>

                    <!-- Select Game -->
                    <div class="space-y-2">
                        <label class="text-[9px] text-zinc-500 font-bold uppercase tracking-wider font-orbitron">Select Title</label>
                        <select x-model="selectedGame" class="w-full bg-zinc-950 border border-purple-500/20 focus:border-purple-500 focus:outline-none rounded-xl py-3 px-4 text-xs font-bold font-orbitron tracking-wider text-purple-300">
                            <option value="Valorant">VALORANT</option>
                            <option value="CS2">COUNTER-STRIKE 2</option>
                            <option value="FIFA 24">FIFA 24</option>
                            <option value="Tekken 8">TEKKEN 8</option>
                            <option value="Dota 2">DOTA 2</option>
                        </select>
                    </div>

                    <!-- Stake Amount -->
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <label class="text-[9px] text-zinc-500 font-bold uppercase tracking-wider font-orbitron">STAKE WEIGHT ($)</label>
                            <span class="text-xs font-black text-emerald-400 font-orbitron" x-text="'$' + stake"></span>
                        </div>
                        <input type="range" x-model="stake" min="5" max="100" step="5" class="w-full h-1 bg-zinc-900 rounded-lg appearance-none cursor-pointer accent-fuchsia-500 border border-purple-500/10">
                        <div class="flex justify-between text-[8px] text-zinc-600 font-mono">
                            <span>$5 (MIN)</span>
                            <span>$50</span>
                            <span>$100 (MAX)</span>
                        </div>
                    </div>
                </div>

                <!-- Matchmaker Action Button -->
                <div class="pt-6">
                    <button @click="$wire.findDuel()" 
                            class="w-full bg-gradient-to-r from-purple-600 via-fuchsia-600 to-cyan-500 hover:from-purple-500 hover:to-cyan-400 border border-fuchsia-400/20 text-xs font-black font-orbitron uppercase tracking-widest text-white py-3 rounded-xl transition-all duration-300 shadow-[0_0_15px_rgba(217,70,239,0.35)] hover:shadow-[0_0_25px_rgba(34,211,238,0.6)] cursor-pointer">
                        ENTER QUICK DUEL QUEUE
                    </button>
                    
                    <button @click="$wire.createChallenge()"
                            class="w-full mt-3 bg-zinc-950 hover:bg-zinc-900 border border-purple-500/20 text-[9px] font-black font-orbitron uppercase tracking-widest text-purple-300 hover:text-white py-2.5 rounded-xl transition-colors cursor-pointer">
                        BROADCAST CUSTOM LOBBY CHALLENGE
                    </button>
                </div>
            </div>

            <!-- Right Side: Matchmaking Display Terminal (RADAR / OPPONENT CARD) -->
            <div class="lg:col-span-2 bg-zinc-950 border border-purple-500/15 rounded-2xl shadow-2xl relative overflow-hidden flex flex-col items-center justify-center min-h-[350px]">
                
                <!-- Radar background visual grid -->
                <div class="absolute inset-0 cyber-grid opacity-[0.2] pointer-events-none"></div>

                <!-- State 0: Empty Idle State -->
                <div x-show="step === 0" class="text-center p-6 space-y-4 relative z-10">
                    <div class="w-16 h-16 rounded-full bg-purple-950/40 border border-purple-500/35 flex items-center justify-center mx-auto shadow-[0_0_15px_rgba(168,85,247,0.1)]">
                        <i data-lucide="shield-alert" class="w-8 h-8 text-purple-400 animate-pulse"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-black font-orbitron tracking-widest text-zinc-300 uppercase">MATCHMAKER OFFLINE</h4>
                        <p class="text-[10px] text-zinc-500 mt-1 max-w-xs mx-auto">Initiate a duel search from the control panel to seek active players in your region.</p>
                    </div>
                </div>

                <!-- State 1: Matching Radar Animation -->
                <div x-show="step === 1" class="text-center p-6 space-y-6 relative z-10 flex flex-col items-center" x-cloak>
                    
                    <!-- Radar Circle -->
                    <div class="relative w-36 h-36 border border-purple-500/30 rounded-full flex items-center justify-center shadow-[0_0_20px_rgba(168,85,247,0.1)]">
                        <!-- Scanning Sweeper Line -->
                        <div class="absolute inset-0 rounded-full border-t border-l border-fuchsia-500/50 animate-spin" style="animation-duration: 2s;"></div>
                        <!-- Inner circles -->
                        <div class="w-24 h-24 border border-purple-500/20 rounded-full flex items-center justify-center">
                            <div class="w-12 h-12 border border-purple-500/10 rounded-full bg-purple-500/5 flex items-center justify-center">
                                <span class="w-2.5 h-2.5 rounded-full bg-fuchsia-400 animate-ping"></span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <h4 class="text-sm font-black font-orbitron tracking-widest text-fuchsia-400 uppercase neon-pulse-purple">LOCATING OPPONENT</h4>
                        <div class="text-[8px] font-mono text-zinc-500 uppercase tracking-widest space-y-0.5">
                            <div>PING: 14MS // SERVER: US-EAST</div>
                            <div class="animate-pulse">LOOKING FOR STAKE WEIGHT DEPOSIT...</div>
                        </div>
                    </div>

                    <button @click="$wire.cancelSearch()" class="px-5 py-1.5 bg-red-950/60 border border-red-500/30 text-[8px] font-black font-orbitron uppercase tracking-widest text-red-400 hover:bg-red-900 rounded-lg transition-colors cursor-pointer">
                        CANCEL MATCHMAKING
                    </button>
                </div>

                <!-- State 2: Opponent Found Screen -->
                <div x-show="step === 2" class="w-full p-6 space-y-6 relative z-10" x-cloak>
                    <template x-if="matchedUser">
                        <div class="space-y-6 text-center">
                            
                            <!-- Banner Title -->
                            <div>
                                <span class="text-[9px] font-bold text-emerald-400 bg-emerald-950 border border-emerald-900 px-3 py-1 rounded-full font-orbitron uppercase tracking-widest">
                                    MATCH DETECTED
                                </span>
                                <h4 class="text-lg font-black font-orbitron tracking-widest text-white uppercase mt-3 filter drop-shadow-[0_0_8px_rgba(168,85,247,0.3)]">READY FOR DUEL</h4>
                            </div>

                            <!-- Opponent Match VS Panel -->
                            <div class="flex flex-col sm:flex-row items-center justify-center gap-6 sm:gap-12 py-4">
                                <!-- Self -->
                                <div class="text-center">
                                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-purple-500 to-fuchsia-500 p-[1.5px] mx-auto shadow-[0_0_15px_rgba(168,85,247,0.3)]">
                                        <div class="w-full h-full bg-[#0a0718] rounded-full flex items-center justify-center text-purple-400 text-base font-bold font-orbitron">
                                            {{ strtoupper(substr(auth()->user()->username, 0, 2)) }}
                                        </div>
                                    </div>
                                    <span class="block text-xs font-bold font-orbitron text-zinc-300 mt-2 uppercase tracking-wide">{{ auth()->user()->username }}</span>
                                    <span class="block text-[8px] font-mono text-zinc-500">{{ strtoupper(auth()->user()->username) }} // YOU</span>
                                </div>

                                <!-- VS Shield -->
                                <div class="relative bg-zinc-950 border-2 border-purple-500/30 w-12 h-12 rounded-xl flex items-center justify-center shadow-[0_0_15px_rgba(168,85,247,0.2)]">
                                    <span class="text-xs font-black font-orbitron text-fuchsia-400 animate-pulse">VS</span>
                                </div>

                                <!-- Opponent -->
                                <div class="text-center">
                                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-cyan-500 to-indigo-600 p-[1.5px] mx-auto shadow-[0_0_15px_rgba(34,211,238,0.3)]">
                                        <div class="w-full h-full bg-[#0a0718] rounded-full flex items-center justify-center text-cyan-400 text-base font-bold font-orbitron" x-text="matchedUser.avatar">
                                        </div>
                                    </div>
                                    <span class="block text-xs font-bold font-orbitron text-cyan-400 mt-2 uppercase tracking-wide" x-text="matchedUser.username"></span>
                                    <span class="block text-[8px] font-mono text-zinc-500" x-text="'LEVEL ' + matchedUser.level + ' // WINRATE ' + matchedUser.winrate"></span>
                                </div>
                            </div>

                            <!-- Info Stake Board -->
                            <div class="max-w-xs mx-auto bg-[#0c081d] border border-purple-500/15 rounded-xl p-3.5 flex justify-between items-center text-[10px]">
                                <div>
                                    <span class="text-zinc-500 font-bold uppercase tracking-wider block">CHALLENGE GAME</span>
                                    <span class="font-bold text-white font-orbitron tracking-wider block mt-0.5" x-text="matchedUser.game"></span>
                                </div>
                                <div class="text-right">
                                    <span class="text-zinc-500 font-bold uppercase tracking-wider block">ESCROW STAKE</span>
                                    <span class="font-black text-emerald-400 font-orbitron tracking-widest block mt-0.5" x-text="'$' + (stake * 2) + '.00 ($' + stake + '/PLAYER)'"></span>
                                </div>
                            </div>

                            <!-- Buttons -->
                            <div class="flex items-center justify-center space-x-3 pt-2">
                                <button @click="$wire.cancelSearch()" class="px-5 py-2.5 bg-zinc-900 border border-zinc-800 text-[10px] font-black font-orbitron uppercase tracking-widest text-zinc-400 hover:text-white rounded-xl transition-all cursor-pointer">
                                    DECLINE BATTLE
                                </button>
                                <button @click="alert('Connecting to Lobby Server... Please prepare your client!')" class="px-6 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-500 border border-emerald-400/20 text-[10px] font-black font-orbitron uppercase tracking-widest text-white rounded-xl shadow-[0_0_15px_rgba(16,185,129,0.4)] transition-all cursor-pointer">
                                    COMMENCE BATTLE
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Bottom: Public Open Lobby Board -->
            <div class="lg:col-span-3 bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-purple-500/10 pb-3">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="globe" class="w-4.5 h-4.5 text-purple-400"></i>
                        <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase">BROADCASTED STAGED DUELS</h3>
                    </div>
                    <span class="text-[9px] bg-purple-950 text-purple-400 px-2 py-0.5 rounded font-bold font-mono">3 DUELS OPEN</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <template x-for="ch in $wire.challenges" :key="ch.id">
                        <div class="bg-zinc-950/60 border border-purple-500/10 hover:border-purple-500/20 rounded-xl p-4 flex items-center justify-between gap-4 transition-all duration-200">
                            <div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-6 h-6 rounded-full bg-purple-900/30 border border-purple-500/20 flex items-center justify-center text-[9px] font-bold font-orbitron text-purple-400" x-text="ch.avatar"></div>
                                    <span class="text-xs font-bold text-zinc-300 font-orbitron" x-text="ch.username"></span>
                                </div>
                                <div class="mt-2.5 flex items-center space-x-2">
                                    <span class="text-[8px] font-bold text-fuchsia-400 bg-fuchsia-950/40 border border-fuchsia-900/40 px-2 py-0.5 rounded font-orbitron" x-text="ch.game"></span>
                                    <span class="text-xs font-black text-emerald-400 font-mono" x-text="'$' + ch.stake + '.00'"></span>
                                </div>
                            </div>
                            <button @click="alert('Challenge Accepted! Processing Stake Escrow...')" class="bg-purple-950 border border-purple-500/30 hover:bg-purple-900 text-[8px] font-black font-orbitron uppercase tracking-widest text-purple-300 hover:text-white py-1.5 px-3 rounded-lg transition-colors cursor-pointer">
                                ACCEPT CHALLENGE
                            </button>
                        </div>
                    </template>
                </div>
            </div>

        </div>
    </template>

    <!-- ---------------------------------------------------- -->
    <!-- TAB 4: LEADERBOARDS                                  -->
    <!-- ---------------------------------------------------- -->
    <template x-if="activeTab === 'leaderboards'">
        <div class="space-y-8" x-data="{
            searchFilter: '',
            topPlayers: [
                { rank: 1, name: 'SaloonsKing', level: 64, wins: 142, losses: 28, cash: '$1,820.00', tier: 'Challenger', avatar: 'SK' },
                { rank: 2, name: 'ViperZero', level: 45, wins: 98, losses: 18, cash: '$1,240.00', tier: 'Challenger', avatar: 'VZ' },
                { rank: 3, name: 'ShadowBlade', level: 52, wins: 110, losses: 26, cash: '$1,190.00', tier: 'Challenger', avatar: 'SB' },
                { rank: 4, name: 'HyperDrift', level: 38, wins: 76, losses: 22, cash: '$840.00', tier: 'Diamond', avatar: 'HD' },
                { rank: 5, name: 'GamerGod', level: 42, wins: 80, losses: 28, cash: '$780.00', tier: 'Diamond', avatar: 'GG' },
                { rank: 6, name: 'NeonSpecter', level: 38, wins: 72, losses: 26, cash: '$690.00', tier: 'Diamond', avatar: 'NS' },
                { rank: 7, name: 'CyberKnight', level: 32, wins: 56, losses: 22, cash: '$490.00', tier: 'Platinum', avatar: 'CK' }
            ]
        }">
            
            <!-- Podium Grid Top 3 -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end pt-4">
                
                <!-- 2nd Place Podium Card -->
                <div class="order-2 md:order-1 bg-[#0c081d]/80 border border-purple-500/15 rounded-2xl p-5 text-center flex flex-col justify-between h-[280px] relative">
                    <div class="absolute top-2 left-2 text-xs font-black font-orbitron text-zinc-500">#2</div>
                    <div class="space-y-4">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-zinc-300 to-zinc-500 p-[1.5px] mx-auto shadow-[0_0_15px_rgba(200,200,200,0.25)]">
                            <div class="w-full h-full bg-[#0a0718] rounded-full flex items-center justify-center text-zinc-300 text-lg font-bold font-orbitron">VZ</div>
                        </div>
                        <div>
                            <h4 class="text-sm font-black font-orbitron text-zinc-200 tracking-wider">VIPERZERO</h4>
                            <span class="text-[9px] font-bold text-zinc-400 bg-zinc-950 px-2 py-0.5 border border-zinc-800 rounded font-orbitron">CHALLENGER</span>
                        </div>
                    </div>
                    <div class="bg-zinc-950 border border-zinc-900 rounded-xl py-2 px-4 flex justify-between text-[10px] mt-4 font-orbitron">
                        <span class="text-zinc-500 font-bold">CASH: $1,240</span>
                        <span class="text-cyan-400 font-bold">W/L: 98-18</span>
                    </div>
                </div>

                <!-- 1st Place Podium Card -->
                <div class="order-1 md:order-2 bg-[#120729]/90 border-2 border-purple-500/40 rounded-2xl p-6 text-center flex flex-col justify-between h-[320px] relative shadow-[0_10px_40px_rgba(168,85,247,0.2),0_0_15px_rgba(168,85,247,0.15)] neon-border-glow">
                    <div class="absolute top-2 left-2 text-xs font-black font-orbitron text-purple-400">#1</div>
                    <div class="space-y-4">
                        <div class="relative w-20 h-20 rounded-full bg-gradient-to-br from-purple-500 via-fuchsia-500 to-yellow-500 p-[2px] mx-auto shadow-[0_0_20px_rgba(217,70,239,0.5)]">
                            <!-- Golden crown badge -->
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-yellow-500 text-black px-1.5 py-0.5 rounded text-[8px] font-black font-orbitron">CROWN</div>
                            <div class="w-full h-full bg-[#0a0718] rounded-full flex items-center justify-center text-purple-300 text-xl font-bold font-orbitron">SK</div>
                        </div>
                        <div>
                            <h4 class="text-base font-black font-orbitron text-white tracking-widest neon-pulse-purple">SALOONSKING</h4>
                            <span class="text-[9px] font-bold text-fuchsia-400 bg-fuchsia-950/60 px-3 py-1 border border-fuchsia-900/60 rounded-full font-orbitron">CHALLENGER T1</span>
                        </div>
                    </div>
                    <div class="bg-zinc-950 border border-purple-500/10 rounded-xl py-2 px-4 flex justify-between text-[11px] mt-4 font-orbitron">
                        <span class="text-emerald-400 font-bold">CASH: $1,820</span>
                        <span class="text-fuchsia-400 font-bold">W/L: 142-28</span>
                    </div>
                </div>

                <!-- 3rd Place Podium Card -->
                <div class="order-3 md:order-3 bg-[#0c081d]/80 border border-purple-500/15 rounded-2xl p-5 text-center flex flex-col justify-between h-[260px] relative">
                    <div class="absolute top-2 left-2 text-xs font-black font-orbitron text-zinc-650">#3</div>
                    <div class="space-y-4">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-amber-700 to-amber-900 p-[1.5px] mx-auto shadow-[0_0_15px_rgba(180,100,50,0.25)]">
                            <div class="w-full h-full bg-[#0a0718] rounded-full flex items-center justify-center text-amber-500 text-lg font-bold font-orbitron">SB</div>
                        </div>
                        <div>
                            <h4 class="text-sm font-black font-orbitron text-zinc-200 tracking-wider">SHADOWBLADE</h4>
                            <span class="text-[9px] font-bold text-zinc-400 bg-zinc-950 px-2 py-0.5 border border-zinc-800 rounded font-orbitron">CHALLENGER</span>
                        </div>
                    </div>
                    <div class="bg-zinc-950 border border-zinc-900 rounded-xl py-2 px-4 flex justify-between text-[10px] mt-4 font-orbitron">
                        <span class="text-zinc-500 font-bold">CASH: $1,190</span>
                        <span class="text-cyan-400 font-bold">W/L: 110-26</span>
                    </div>
                </div>

            </div>

            <!-- Table section -->
            <div class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 space-y-4">
                <div class="flex flex-col md:flex-row items-center justify-between border-b border-purple-500/10 pb-4 gap-4">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="award" class="w-5 h-5 text-purple-400"></i>
                        <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase font-bold">STANDINGS</h3>
                    </div>
                    
                    <!-- Search inside leaderboard -->
                    <div class="relative w-full md:w-64">
                        <input type="text" x-model="searchFilter" placeholder="SEARCH PLAYER..." class="w-full bg-zinc-950 border border-purple-500/20 hover:border-purple-500/40 focus:border-purple-500 focus:outline-none rounded-xl py-1.5 px-3.5 text-[10px] font-bold font-orbitron uppercase tracking-widest text-purple-300">
                    </div>
                </div>

                <!-- Leaderboard Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[11px] font-medium">
                        <thead>
                            <tr class="border-b border-purple-500/10 text-zinc-500 font-bold font-orbitron uppercase tracking-widest">
                                <th class="py-3.5 px-2">RANK</th>
                                <th class="py-3.5 px-2">PLAYER</th>
                                <th class="py-3.5 px-2">TIER</th>
                                <th class="py-3.5 px-2 text-center">LEVEL</th>
                                <th class="py-3.5 px-2 text-center">WINS</th>
                                <th class="py-3.5 px-2 text-center">LOSSES</th>
                                <th class="py-3.5 px-2 text-right">TOTAL CASH</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="p in topPlayers" :key="p.rank">
                                <tr x-show="p.name.toLowerCase().includes(searchFilter.toLowerCase())"
                                    class="border-b border-purple-500/5 hover:bg-purple-950/10 transition-colors">
                                    <td class="py-3.5 px-2 font-black font-orbitron text-purple-400 text-xs" x-text="'#' + p.rank"></td>
                                    <td class="py-3.5 px-2 font-bold font-orbitron text-white text-xs" x-text="p.name"></td>
                                    <td class="py-3.5 px-2 font-bold font-orbitron text-fuchsia-400" x-text="p.tier"></td>
                                    <td class="py-3.5 px-2 text-center font-mono text-zinc-300" x-text="p.level"></td>
                                    <td class="py-3.5 px-2 text-center font-mono text-emerald-400 font-bold" x-text="p.wins"></td>
                                    <td class="py-3.5 px-2 text-center font-mono text-red-400" x-text="p.losses"></td>
                                    <td class="py-3.5 px-2 text-right font-black font-orbitron text-emerald-400" x-text="p.cash"></td>
                                </tr>
                            </template>
                            
                            <!-- Highlight Row: Current User Place -->
                            <tr class="bg-purple-950/20 border-t-2 border-b-2 border-purple-500/30">
                                <td class="py-4 px-2 font-black font-orbitron text-purple-300 text-xs">—</td>
                                <td class="py-4 px-2 font-bold font-orbitron text-white text-xs">{{ $user->username }} (YOU)</td>
                                <td class="py-4 px-2 font-bold font-orbitron text-zinc-500">—</td>
                                <td class="py-4 px-2 text-center font-mono text-zinc-300">{{ $playerStats['total_matches'] }}</td>
                                <td class="py-4 px-2 text-center font-mono text-emerald-450 font-bold">{{ $playerStats['wins'] }}</td>
                                <td class="py-4 px-2 text-center font-mono text-red-450">{{ $playerStats['losses'] }}</td>
                                <td class="py-4 px-2 text-right font-black font-orbitron text-emerald-450">${{ number_format($playerStats['earnings'], 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </template>

    <!-- ---------------------------------------------------- -->
    <!-- TAB 5: STREAMS                                       -->
    <!-- ---------------------------------------------------- -->
    <template x-if="activeTab === 'streams'">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6" x-data="{
            activeStream: 'https://www.youtube.com/embed/5D-M5M_Lw1M?autoplay=1&mute=1&playlist=5D-M5M_Lw1M&loop=1',
            activeTitle: 'VALORANT CHAMPIONS TOUR // EMEA LEAGUE LIVE',
            streamsList: [
                { id: 1, title: 'VALORANT CHAMPIONS TOUR // EMEA LEAGUE LIVE', game: 'Valorant', code: 'https://www.youtube.com/embed/5D-M5M_Lw1M?autoplay=1&mute=1&playlist=5D-M5M_Lw1M&loop=1', views: '1.2K Watching', avatar: 'VCT' },
                { id: 2, title: 'CS2 SUPREME ESPORTS DUELS // PRO FINALS', game: 'CS2', code: 'https://www.youtube.com/embed/tgbNymZ7vqY?autoplay=1&mute=1&playlist=tgbNymZ7vqY&loop=1', views: '840 Watching', avatar: 'CS2' },
                { id: 3, title: 'FIFA eWORLD CUP // SALOON CHALLENGE', game: 'FIFA 24', code: 'https://www.youtube.com/embed/XqC946Vf7XU?autoplay=1&mute=1&playlist=XqC946Vf7XU&loop=1', views: '320 Watching', avatar: 'FIFA' }
            ]
        }">
            
            <!-- Large Video Player Frame -->
            <div class="lg:col-span-3 flex flex-col space-y-4">
                <div class="bg-black border border-purple-500/15 rounded-2xl overflow-hidden shadow-2xl aspect-video relative">
                    <iframe :src="activeStream" class="w-full h-full" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                </div>
                <div class="bg-[#0c081d] border border-purple-500/15 rounded-xl p-4">
                    <span class="text-[9px] font-bold text-fuchsia-400 bg-fuchsia-950/40 border border-fuchsia-900/60 px-2 py-0.5 rounded font-orbitron">LIVE NOW</span>
                    <h3 class="text-sm md:text-base font-black font-orbitron tracking-wider text-white uppercase mt-2.5" x-text="activeTitle"></h3>
                </div>
            </div>

            <!-- Streaming Chat Panel Simulator -->
            <div class="lg:col-span-1 h-[450px] lg:h-auto flex flex-col">
                <div x-data="{
                    chatList: [
                        { user: 'HypeMachine', text: 'LEEEEET\'S GO!', color: 'text-fuchsia-450' },
                        { user: 'GhostDog', text: 'unreal double kill', color: 'text-purple-400' },
                        { user: 'Sniper007', text: 'wow no way', color: 'text-cyan-400' }
                    ],
                    pool: [
                        { user: 'AlphaWolf', text: 'Saloon streaming details are clean', color: 'text-blue-400' },
                        { user: 'EsportsPro', text: 'POGGERS!!!', color: 'text-fuchsia-400' },
                        { user: 'Viking09', text: 'that defuse was close', color: 'text-yellow-400' },
                        { user: 'CyberLord', text: 'Stake weight on this match was huge', color: 'text-emerald-400' },
                        { user: 'GamerGrl', text: 'insane setup', color: 'text-pink-400' },
                        { user: 'Shadow_Ninja', text: 'what server is this?', color: 'text-red-400' }
                    ],
                    init() {
                        setInterval(() => {
                            let randomMsg = this.pool[Math.floor(Math.random() * this.pool.length)];
                            this.chatList.push(randomMsg);
                            if (this.chatList.length > 25) this.chatList.shift();
                            this.$nextTick(() => {
                                const container = this.$refs.streamChatBox;
                                if (container) container.scrollTop = container.scrollHeight;
                            });
                        }, 2000);
                    }
                }" class="flex flex-col h-full bg-[#0a0718] border border-purple-500/15 rounded-2xl overflow-hidden shadow-xl">
                    
                    <div class="px-4 py-3 border-b border-purple-500/10 flex items-center justify-between bg-purple-950/20">
                        <span class="font-orbitron font-bold text-xs text-purple-300 uppercase tracking-widest">STREAM FEED</span>
                        <span class="text-[8px] bg-red-950 border border-red-900 px-2 py-0.5 rounded text-red-400 animate-pulse font-bold">LIVE CHAT</span>
                    </div>

                    <!-- Chat stream scroll area -->
                    <div x-ref="streamChatBox" class="flex-1 overflow-y-auto p-4 space-y-2.5 font-sans text-[11px] font-medium">
                        <template x-for="(c, index) in chatList" :key="index">
                            <div class="flex items-start space-x-1.5">
                                <span class="font-bold font-orbitron" :class="c.color" x-text="c.user + ':'"></span>
                                <span class="text-zinc-350" x-text="c.text"></span>
                            </div>
                        </template>
                    </div>

                    <div class="p-3 border-t border-purple-500/10 bg-zinc-950/50">
                        <input type="text" placeholder="SEND CHAT TO ROOM..." class="w-full bg-zinc-950 border border-purple-500/20 rounded-xl py-2 px-3 text-[10px] font-orbitron focus:outline-none focus:border-purple-500 uppercase tracking-widest text-purple-300">
                    </div>
                </div>
            </div>

            <!-- Alternative Stream Lists Row -->
            <div class="lg:col-span-4 bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 space-y-4">
                <h4 class="text-xs font-black font-orbitron tracking-wider text-zinc-150 uppercase border-b border-purple-500/10 pb-2.5">ACTIVE STREAMING CLIENTS</h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <template x-for="st in streamsList" :key="st.id">
                        <div @click="activeStream = st.code; activeTitle = st.title" 
                             class="bg-zinc-950 border border-purple-500/10 hover:border-purple-500/30 rounded-xl p-3.5 flex items-center justify-between cursor-pointer group transition-all duration-200">
                            <div>
                                <span class="text-[8px] text-fuchsia-400 font-bold uppercase tracking-widest font-orbitron block" x-text="st.game"></span>
                                <h5 class="text-xs font-bold text-white font-orbitron truncate w-48 mt-1 group-hover:text-purple-300" x-text="st.title"></h5>
                                <span class="text-[9px] text-zinc-500 font-mono block mt-1" x-text="st.views"></span>
                            </div>
                            <div class="p-2.5 bg-purple-950/30 rounded-lg text-purple-400 group-hover:bg-purple-900 group-hover:text-white transition-colors">
                                <i data-lucide="play" class="w-4 h-4"></i>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

        </div>
    </template>

    <!-- ---------------------------------------------------- -->
    <!-- TAB 6: CHAT                                          -->
    <!-- ---------------------------------------------------- -->
    <template x-if="activeTab === 'chat'">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6" x-data="{
            activeRoom: 'global',
            scrollToBottom() {
                this.$nextTick(() => {
                    const c = this.$refs.chatScrollArea;
                    if (c) c.scrollTop = c.scrollHeight;
                });
            },
            init() {
                this.scrollToBottom();
                document.addEventListener('chat-updated', () => this.scrollToBottom());
            }
        }">
            
            <!-- Channels List Column -->
            <div class="lg:col-span-1 bg-[#0c081d] border border-purple-500/15 rounded-2xl p-4 md:p-5 flex flex-col justify-between h-[450px] lg:h-auto">
                <div class="space-y-5">
                    <div class="flex items-center space-x-2 border-b border-purple-500/10 pb-3">
                        <i data-lucide="message-square" class="w-5 h-5 text-purple-400"></i>
                        <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase font-bold">COMM CHANNELS</h3>
                    </div>

                    <nav class="space-y-2">
                        <!-- Global Channel button -->
                        <button @click="activeRoom = 'global'; scrollToBottom()" :class="activeRoom === 'global' ? 'bg-purple-950/40 border-purple-500/30 text-purple-300' : 'bg-transparent border-transparent text-zinc-500 hover:text-zinc-350 hover:bg-zinc-900/30'" class="w-full flex items-center px-3 py-3 rounded-xl border text-left text-xs font-bold font-orbitron uppercase tracking-widest transition-all">
                            <span class="w-2 h-2 rounded-full bg-cyan-400 mr-3 animate-pulse"></span>
                            GLOBAL CHAT
                        </button>
                        <!-- Clan Channel button -->
                        <button @click="activeRoom = 'clan'; scrollToBottom()" :class="activeRoom === 'clan' ? 'bg-purple-950/40 border-purple-500/30 text-purple-300' : 'bg-transparent border-transparent text-zinc-500 hover:text-zinc-350 hover:bg-zinc-900/30'" class="w-full flex items-center px-3 py-3 rounded-xl border text-left text-xs font-bold font-orbitron uppercase tracking-widest transition-all">
                            <span class="w-2 h-2 rounded-full bg-fuchsia-400 mr-3 animate-pulse"></span>
                            CLAN BATTLECOMMS
                        </button>
                        <!-- Match Lobby button -->
                        <button @click="activeRoom = 'lobby'; scrollToBottom()" :class="activeRoom === 'lobby' ? 'bg-purple-950/40 border-purple-500/30 text-purple-300' : 'bg-transparent border-transparent text-zinc-500 hover:text-zinc-350 hover:bg-zinc-900/30'" class="w-full flex items-center px-3 py-3 rounded-xl border text-left text-xs font-bold font-orbitron uppercase tracking-widest transition-all">
                            <span class="w-2 h-2 rounded-full bg-red-400 mr-3"></span>
                            MATCH SUPPORT LOBBY
                        </button>
                    </nav>
                </div>

                <div class="bg-zinc-950/50 border border-purple-500/5 rounded-xl p-3 text-[10px] text-zinc-500 text-center font-medium">
                    Please respect other gamers. System logs monitor offensive activity.
                </div>
            </div>

            <!-- Chat Dialog Panel -->
            <div class="lg:col-span-3 bg-[#0c081d] border border-purple-500/15 rounded-2xl flex flex-col h-[500px] overflow-hidden shadow-2xl relative">
                
                <!-- Chat Window Header -->
                <div class="px-5 py-4 border-b border-purple-500/10 flex items-center justify-between bg-purple-950/15">
                    <div>
                        <h4 class="text-xs font-black font-orbitron tracking-widest text-white uppercase" x-text="activeRoom === 'global' ? 'GLOBAL CHAT LOBBY' : (activeRoom === 'clan' ? 'CLAN CHAT' : 'MATCH SUPPORT')"></h4>
                        <span class="text-[9px] text-purple-350 font-medium">Active Room Terminal</span>
                    </div>
                    <span class="text-[8px] bg-purple-950 text-purple-400 border border-purple-900 px-2 py-0.5 rounded-full font-bold font-mono">14 ONLINE</span>
                </div>

                <!-- Chat Scroll View -->
                <div x-ref="chatScrollArea" class="flex-grow overflow-y-auto p-5 space-y-4">
                    
                    <!-- If Global Chat Room -->
                    <template x-if="activeRoom === 'global'">
                        <div class="space-y-4">
                            <template x-for="(m, index) in $wire.messages" :key="index">
                                <div class="flex items-start space-x-3">
                                    <!-- Avatar circle -->
                                    <div class="w-8 h-8 rounded-lg bg-purple-900/20 border border-purple-500/20 flex items-center justify-center text-[10px] font-bold font-orbitron text-purple-400" x-text="m.avatar"></div>
                                    <div class="space-y-1">
                                        <div class="flex items-baseline space-x-2">
                                            <span class="text-xs font-bold text-white font-orbitron" x-text="m.username"></span>
                                            <span class="text-[8px] text-zinc-500 font-mono" x-text="m.time"></span>
                                        </div>
                                        <p class="text-xs text-zinc-350 font-sans" x-text="m.text"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- If Clan Chat Room (Mocked) -->
                    <template x-if="activeRoom === 'clan'">
                        <div class="space-y-4">
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-fuchsia-900/20 border border-fuchsia-500/20 flex items-center justify-center text-[10px] font-bold font-orbitron text-fuchsia-400">CL</div>
                                <div class="space-y-1">
                                    <div class="flex items-baseline space-x-2">
                                        <span class="text-xs font-bold text-white font-orbitron">ClanLeader</span>
                                        <span class="text-[8px] text-zinc-500 font-mono">10:45</span>
                                    </div>
                                    <p class="text-xs text-zinc-350 font-sans">Viking Clash tournament registration starts at 15:00. Everyone make sure you join!</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-fuchsia-900/20 border border-fuchsia-500/20 flex items-center justify-center text-[10px] font-bold font-orbitron text-fuchsia-400">MD</div>
                                <div class="space-y-1">
                                    <div class="flex items-baseline space-x-2">
                                        <span class="text-xs font-bold text-white font-orbitron">MadDog</span>
                                        <span class="text-[8px] text-zinc-500 font-mono">10:48</span>
                                    </div>
                                    <p class="text-xs text-zinc-350 font-sans">I am registered. Let\'s coordinate our lineups inside the team page.</p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- If Match Support Room (Mocked) -->
                    <template x-if="activeRoom === 'lobby'">
                        <div class="space-y-4 text-center py-12">
                            <i data-lucide="shield-alert" class="w-12 h-12 text-zinc-700 mx-auto animate-pulse"></i>
                            <h4 class="text-xs font-black font-orbitron text-zinc-400 uppercase tracking-widest mt-4">NO ACTIVE DISPUTES</h4>
                            <p class="text-[10px] text-zinc-650 max-w-xs mx-auto mt-1">If you have a problem regarding match scoring or tournaments, contact moderators by filing a dispute inside the Match Hub.</p>
                        </div>
                    </template>

                </div>

                <!-- Message Send Controls -->
                <form wire:submit.prevent="sendMessage" class="p-4 border-t border-purple-500/10 bg-zinc-950/45 flex items-center space-x-3">
                    <input type="text" 
                           wire:model="chatMessage" 
                           placeholder="Type transmission..." 
                           class="flex-1 bg-zinc-950 border border-purple-500/20 focus:border-purple-500 focus:outline-none rounded-xl py-3 px-4 text-xs font-semibold text-purple-300 placeholder-zinc-650">
                    <button type="submit" 
                            class="p-3 bg-gradient-to-br from-purple-600 to-fuchsia-600 hover:from-purple-500 hover:to-fuchsia-500 rounded-xl text-white shadow-[0_0_10px_rgba(168,85,247,0.3)] transition-all flex items-center justify-center cursor-pointer">
                        <i data-lucide="send" class="w-4.5 h-4.5"></i>
                    </button>
                </form>

            </div>
        </div>
    </template>

</div>
