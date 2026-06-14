<div class="space-y-8 min-w-0">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Dashboard Widgets -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6">
                <h3 class="text-lg font-black text-white font-orbitron">Welcome back, {{ auth()->user()->username }}!</h3>
                <p class="text-zinc-500 text-sm mt-2">Check your active tournaments and latest match results.</p>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6">
                    <span class="text-zinc-500 text-xs">Active Tournaments</span>
                    <div class="text-2xl font-black text-white mt-1">{{ $activeTournaments->count() }}</div>
                </div>
                <div class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6">
                    <span class="text-zinc-500 text-xs">Wallet Balance</span>
                    <div class="text-2xl font-black text-emerald-400 mt-1">${{ number_format($playerStats['earnings'], 2) }}</div>
                </div>
            </div>
        </div>

        <!-- Mini Stats -->
        <div class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6">
            <h3 class="text-sm font-black text-zinc-300 font-orbitron uppercase">Stats</h3>
            <div class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between text-zinc-500"><span>Matches:</span> <span class="text-white">{{ $playerStats['total_matches'] }}</span></div>
                <div class="flex justify-between text-zinc-500"><span>Wins:</span> <span class="text-emerald-400">{{ $playerStats['wins'] }}</span></div>
                <div class="flex justify-between text-zinc-500"><span>Losses:</span> <span class="text-red-400">{{ $playerStats['losses'] }}</span></div>
            </div>
        </div>
    </div>
</div>
