<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <!-- Tournament Widget -->
    <a href="/my-tournaments" wire:navigate class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6 hover:border-violet-500/50 transition-all flex flex-col justify-between">
        <div class="flex items-center gap-3">
            <i data-lucide="trophy" class="w-6 h-6 text-violet-400"></i>
            <h3 class="text-lg font-black text-white font-orbitron">My Tournaments</h3>
        </div>
        <div class="mt-4 text-3xl font-black text-white">{{ $activeTournamentsCount }} <span class="text-sm font-normal text-zinc-500">Active</span></div>
    </a>

    <!-- H2H Widget -->
    <a href="/head-to-head" wire:navigate class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6 hover:border-fuchsia-500/50 transition-all flex flex-col justify-between">
        <div class="flex items-center gap-3">
            <i data-lucide="swords" class="w-6 h-6 text-fuchsia-400"></i>
            <h3 class="text-lg font-black text-white font-orbitron">H2H Duels</h3>
        </div>
        <div class="mt-4 text-sm text-zinc-400">Enter quick duel queue</div>
    </a>

    <!-- Leaderboard Widget -->
    <a href="/leaderboards" wire:navigate class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6 hover:border-amber-500/50 transition-all flex flex-col justify-between">
        <div class="flex items-center gap-3">
            <i data-lucide="award" class="w-6 h-6 text-amber-400"></i>
            <h3 class="text-lg font-black text-white font-orbitron">Leaderboards</h3>
        </div>
        <div class="mt-4 text-sm text-zinc-400">View global standings</div>
    </a>

    <!-- Wallet Widget -->
    <div class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6 flex flex-col justify-between">
        <div class="flex items-center gap-3">
            <i data-lucide="wallet" class="w-6 h-6 text-emerald-400"></i>
            <h3 class="text-lg font-black text-white font-orbitron">Earnings</h3>
        </div>
        <div class="mt-4 text-2xl font-black text-emerald-400">${{ number_format($earnings, 2) }}</div>
    </div>

    <!-- Streams Widget -->
    <a href="/streams" wire:navigate class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6 hover:border-cyan-500/50 transition-all flex flex-col justify-between">
        <div class="flex items-center gap-3">
            <i data-lucide="tv" class="w-6 h-6 text-cyan-400"></i>
            <h3 class="text-lg font-black text-white font-orbitron">Streams</h3>
        </div>
        <div class="mt-4 text-sm text-zinc-400">Live broadcasts</div>
    </a>

    <!-- Chat Widget -->
    <a href="/chat" wire:navigate class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6 hover:border-purple-500/50 transition-all flex flex-col justify-between">
        <div class="flex items-center gap-3">
            <i data-lucide="message-square" class="w-6 h-6 text-purple-400"></i>
            <h3 class="text-lg font-black text-white font-orbitron">Chat</h3>
        </div>
        <div class="mt-4 text-sm text-zinc-400">Global communications</div>
    </a>
</div>
