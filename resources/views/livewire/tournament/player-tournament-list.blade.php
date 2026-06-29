<div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div>
            <h1 class="text-3xl md:text-5xl font-black font-orbitron tracking-tighter bg-gradient-to-r from-cyan-400 via-violet-500 to-fuchsia-500 bg-clip-text text-transparent filter drop-shadow-[0_0_10px_rgba(124,77,255,0.3)]">
                BROWSE TOURNAMENTS
            </h1>
            <p class="text-sm text-zinc-400 mt-2 font-medium">
                Browse active tournaments, register to compete, and track current brackets.
            </p>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-zinc-900/40 backdrop-blur-xl border border-zinc-800/60 rounded-2xl p-6 shadow-2xl relative overflow-hidden">
        
        <!-- Frequency Tabs -->
        <div class="flex gap-2 mb-6 border-b border-zinc-800 pb-2">
            @foreach(['all' => 'All', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'one-time' => 'One-time'] as $key => $label)
                <button wire:click="$set('activeTab', '{{ $key }}')" 
                        class="px-4 py-2 text-xs font-bold uppercase tracking-widest rounded-lg transition-colors {{ $activeTab === $key ? 'bg-indigo-900 text-white' : 'text-zinc-400 hover:text-white hover:bg-zinc-800' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 relative z-10">
            <!-- Search -->
            <div>
                <label for="search" class="block text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-2.5 ml-1">Search</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-zinc-500">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </span>
                    <input wire:model.live.debounce.300ms="search" id="search" type="text"
                        class="block w-full pl-10 pr-4 py-3 bg-zinc-950/80 border border-zinc-800 rounded-xl text-sm text-zinc-200 placeholder-zinc-700 focus:outline-none focus:ring-1 focus:ring-cyan-500/50 focus:border-cyan-500/50 transition-all duration-300"
                        placeholder="Search tournament name...">
                </div>
            </div>

            <!-- Game Filter -->
            <div>
                <label for="gameId" class="block text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-2.5 ml-1">Game Category</label>
                <select wire:model.live="gameId" id="gameId"
                    class="block w-full px-4 py-3 bg-zinc-950/80 border border-zinc-800 rounded-xl text-sm text-zinc-300 focus:outline-none focus:ring-1 focus:ring-fuchsia-500/50 focus:border-fuchsia-500/50 transition-all duration-300 appearance-none cursor-pointer">
                    <option value="">All Games</option>
                    @foreach($games as $game)
                        <option value="{{ $game->id }}">
                            {{ $game->localizedName() }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Tournament Grid -->
    @if($tournaments->count() > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($tournaments as $tournament)
                <x-player.tournament-card :tournament="$tournament" action-label="Join Tournament" action-icon="zap" />
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-12 py-6 border-t border-zinc-900/50">
            {{ $tournaments->links('vendor.livewire.custom-pagination') }}
        </div>
    @else
        <div class="player-empty-state">
            <div class="absolute inset-0 bg-gradient-to-b from-transparent to-violet-950/5 pointer-events-none"></div>
            <div class="player-empty-icon">
                <i data-lucide="ghost" class="w-10 h-10"></i>
            </div>
            <h3 class="text-xl font-black text-zinc-200 font-orbitron tracking-wider">NO MATCHES FOUND</h3>
            <p class="mt-2 text-sm text-zinc-500 max-w-sm mx-auto font-medium">
                Our sensors detect no active tournaments matching these coordinates. Try recalibrating your search filters.
            </p>
        </div>
    @endif
</div>
