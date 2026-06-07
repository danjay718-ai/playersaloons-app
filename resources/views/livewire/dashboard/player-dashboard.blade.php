<div class="space-y-8">
    <!-- Greeting Section -->
    <div class="bg-gradient-to-r from-zinc-900 via-zinc-900 to-violet-955/10 border border-zinc-850 rounded-2xl p-6 md:p-8 shadow-xl relative overflow-hidden">
        <div class="absolute -top-20 -right-20 w-60 h-60 bg-violet-600/10 rounded-full blur-3xl pointer-events-none"></div>
        <h1 class="text-3xl md:text-5xl font-black font-orbitron tracking-wider text-white uppercase">
            WELCOME BACK, {{ $user->username }}!
        </h1>
        <p class="text-sm text-zinc-400 mt-2">
            Track your matches, view your wallet balance, and check active tournaments.
        </p>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <!-- Balance Card -->
        <a href="/wallet" wire:navigate class="bg-zinc-900 border border-zinc-850 hover:border-zinc-700/80 rounded-xl p-5 shadow-lg shadow-black/20 flex items-center justify-between group transition-all duration-200">
            <div class="space-y-1">
                <span class="block text-xs font-semibold text-zinc-500 uppercase tracking-wider">Wallet Balance</span>
                <span class="text-2xl font-black text-emerald-400 font-orbitron block">
                    ${{ number_format((float)($user->wallet?->cached_balance ?? 0.00), 2) }}
                </span>
            </div>
            <div class="bg-zinc-950 p-3 rounded-xl border border-zinc-850 group-hover:border-zinc-700 text-emerald-400 transition-colors">
                <i data-lucide="wallet" class="w-6 h-6"></i>
            </div>
        </a>

        <!-- Active Tournaments Card -->
        <div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 shadow-lg shadow-black/20 flex items-center justify-between">
            <div class="space-y-1">
                <span class="block text-xs font-semibold text-zinc-500 uppercase tracking-wider">Active Tournaments</span>
                <span class="text-2xl font-black text-violet-400 font-orbitron block">
                    {{ $activeTournaments->count() }}
                </span>
            </div>
            <div class="bg-zinc-950 p-3 rounded-xl border border-zinc-850 text-violet-400">
                <i data-lucide="trophy" class="w-6 h-6"></i>
            </div>
        </div>

        <!-- Recent Matches Card -->
        <div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 shadow-lg shadow-black/20 flex items-center justify-between">
            <div class="space-y-1">
                <span class="block text-xs font-semibold text-zinc-500 uppercase tracking-wider">Total Matches</span>
                <span class="text-2xl font-black text-indigo-400 font-orbitron block">
                    {{ $activeMatches->count() }}
                </span>
            </div>
            <div class="bg-zinc-950 p-3 rounded-xl border border-zinc-850 text-indigo-400">
                <i data-lucide="sword" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- Active Tournaments & Matches Split Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Left: Active Tournaments -->
        <div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 space-y-4">
            <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase border-b border-zinc-850 pb-3">
                MY ACTIVE TOURNAMENTS
            </h2>

            @if($activeTournaments->count() > 0)
                <div class="space-y-3">
                    @foreach($activeTournaments as $tournament)
                        <div class="bg-zinc-950 border border-zinc-850 rounded-xl p-4 flex items-center justify-between gap-4">
                            <div>
                                <span class="text-[9px] font-bold text-violet-400 uppercase tracking-wider bg-violet-950/40 border border-violet-900/60 rounded px-2.5 py-0.5">
                                    {{ $tournament->game->translations->where('locale', 'en')->first()?->name ?? $tournament->game->slug }}
                                </span>
                                <h3 class="text-sm font-bold text-zinc-200 mt-2 truncate w-56">
                                    {{ $tournament->name }}
                                </h3>
                            </div>
                            
                            <a href="/tournaments/{{ $tournament->uuid }}" wire:navigate class="bg-zinc-900 hover:bg-zinc-850 border border-zinc-800 text-xs font-bold text-zinc-300 py-1.5 px-3 rounded-lg transition-colors flex items-center space-x-1.5">
                                <span>Hub</span>
                                <i data-lucide="arrow-right" class="w-3 h-3"></i>
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 text-zinc-500">
                    <i data-lucide="trophy" class="w-8 h-8 mx-auto text-zinc-650 mb-3"></i>
                    <p class="text-xs font-semibold">You are not registered in any active tournaments.</p>
                    <a href="/tournaments" wire:navigate class="text-xs font-bold text-violet-400 hover:text-violet-300 mt-2 inline-block transition-colors">
                        Browse Tournaments &rarr;
                    </a>
                </div>
            @endif
        </div>

        <!-- Right: Recent Matches -->
        <div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 space-y-4">
            <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase border-b border-zinc-850 pb-3">
                MY RECENT MATCHES
            </h2>

            @if($activeMatches->count() > 0)
                <div class="space-y-3">
                    @foreach($activeMatches as $match)
                        @php
                            // Determine opponent
                            $isPlayerA = auth()->id() === $match->playerARegistration?->user_id;
                            $opponent = $isPlayerA ? $match->playerBRegistration?->user : $match->playerARegistration?->user;
                        @endphp
                        <div class="bg-zinc-950 border border-zinc-850 rounded-xl p-4 flex items-center justify-between gap-4">
                            <div class="truncate">
                                <span class="block text-[10px] text-zinc-500 font-semibold uppercase tracking-wider">
                                    {{ $match->tournament->name }} • R{{ $match->round->round_number }}
                                </span>
                                <span class="block text-sm font-bold text-zinc-200 mt-1.5 truncate w-52">
                                    vs. {{ $opponent?->username ?? 'TBD' }}
                                </span>
                            </div>

                            <a href="/matches/{{ $match->uuid }}" wire:navigate class="bg-zinc-900 hover:bg-zinc-850 border border-zinc-800 text-xs font-bold text-zinc-300 py-1.5 px-3 rounded-lg transition-colors flex items-center space-x-1.5">
                                <span>Match Hub</span>
                                <i data-lucide="arrow-right" class="w-3 h-3"></i>
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 text-zinc-500">
                    <i data-lucide="sword" class="w-8 h-8 mx-auto text-zinc-650 mb-3"></i>
                    <p class="text-xs font-semibold">No matches logged for you yet.</p>
                </div>
            @endif
        </div>
    </div>
</div>
