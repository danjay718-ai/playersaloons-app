<div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div>
            <h1 class="text-3xl md:text-5xl font-black font-orbitron tracking-tighter bg-gradient-to-r from-cyan-400 via-violet-500 to-fuchsia-500 bg-clip-text text-transparent filter drop-shadow-[0_0_10px_rgba(124,77,255,0.3)]">
                TOURNAMENTS
            </h1>
            <p class="text-sm text-zinc-400 mt-2 font-medium">
                Browse active tournaments, register to compete, and track current brackets.
            </p>
        </div>
    </div>

    <!-- Tab Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <button wire:click="$set('status', '')"
            class="group relative overflow-hidden rounded-xl border p-4 text-left transition-all duration-300 {{ $status === '' ? 'border-cyan-500/60 bg-cyan-500/10 shadow-lg shadow-cyan-500/10' : 'border-zinc-800/60 bg-zinc-900/40 hover:border-zinc-700 hover:bg-zinc-800/40' }}">
            <div class="flex items-center gap-2 mb-1.5">
                <i data-lucide="layout-grid" class="w-4 h-4 {{ $status === '' ? 'text-cyan-400' : 'text-zinc-500 group-hover:text-zinc-300' }} transition-colors"></i>
                <span class="text-[10px] font-bold uppercase tracking-widest {{ $status === '' ? 'text-cyan-300' : 'text-zinc-500 group-hover:text-zinc-400' }} transition-colors">All</span>
            </div>
            <p class="text-lg font-black {{ $status === '' ? 'text-white' : 'text-zinc-400 group-hover:text-zinc-200' }} transition-colors">Tournaments</p>
            @if($status === '')
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-cyan-400 to-violet-500"></div>
            @endif
        </button>

        <button wire:click="$set('status', 'REGISTRATION_OPEN')"
            class="group relative overflow-hidden rounded-xl border p-4 text-left transition-all duration-300 {{ $status === 'REGISTRATION_OPEN' ? 'border-emerald-500/60 bg-emerald-500/10 shadow-lg shadow-emerald-500/10' : 'border-zinc-800/60 bg-zinc-900/40 hover:border-zinc-700 hover:bg-zinc-800/40' }}">
            <div class="flex items-center gap-2 mb-1.5">
                <i data-lucide="door-open" class="w-4 h-4 {{ $status === 'REGISTRATION_OPEN' ? 'text-emerald-400' : 'text-zinc-500 group-hover:text-zinc-300' }} transition-colors"></i>
                <span class="text-[10px] font-bold uppercase tracking-widest {{ $status === 'REGISTRATION_OPEN' ? 'text-emerald-300' : 'text-zinc-500 group-hover:text-zinc-400' }} transition-colors">Open</span>
            </div>
            <p class="text-lg font-black {{ $status === 'REGISTRATION_OPEN' ? 'text-white' : 'text-zinc-400 group-hover:text-zinc-200' }} transition-colors">Registration</p>
            @if($status === 'REGISTRATION_OPEN')
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-emerald-400 to-cyan-400"></div>
            @endif
        </button>

        <button wire:click="$set('status', 'ONGOING')"
            class="group relative overflow-hidden rounded-xl border p-4 text-left transition-all duration-300 {{ $status === 'ONGOING' ? 'border-violet-500/60 bg-violet-500/10 shadow-lg shadow-violet-500/10' : 'border-zinc-800/60 bg-zinc-900/40 hover:border-zinc-700 hover:bg-zinc-800/40' }}">
            <div class="flex items-center gap-2 mb-1.5">
                <i data-lucide="swords" class="w-4 h-4 {{ $status === 'ONGOING' ? 'text-violet-400' : 'text-zinc-500 group-hover:text-zinc-300' }} transition-colors"></i>
                <span class="text-[10px] font-bold uppercase tracking-widest {{ $status === 'ONGOING' ? 'text-violet-300' : 'text-zinc-500 group-hover:text-zinc-400' }} transition-colors">Live</span>
            </div>
            <p class="text-lg font-black {{ $status === 'ONGOING' ? 'text-white' : 'text-zinc-400 group-hover:text-zinc-200' }} transition-colors">Ongoing</p>
            @if($status === 'ONGOING')
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-violet-400 to-fuchsia-500"></div>
            @endif
        </button>

        <button wire:click="$set('status', 'CHECKIN_OPEN')"
            class="group relative overflow-hidden rounded-xl border p-4 text-left transition-all duration-300 {{ $status === 'CHECKIN_OPEN' ? 'border-amber-500/60 bg-amber-500/10 shadow-lg shadow-amber-500/10' : 'border-zinc-800/60 bg-zinc-900/40 hover:border-zinc-700 hover:bg-zinc-800/40' }}">
            <div class="flex items-center gap-2 mb-1.5">
                <i data-lucide="clock" class="w-4 h-4 {{ $status === 'CHECKIN_OPEN' ? 'text-amber-400' : 'text-zinc-500 group-hover:text-zinc-300' }} transition-colors"></i>
                <span class="text-[10px] font-bold uppercase tracking-widest {{ $status === 'CHECKIN_OPEN' ? 'text-amber-300' : 'text-zinc-500 group-hover:text-zinc-400' }} transition-colors">Soon</span>
            </div>
            <p class="text-lg font-black {{ $status === 'CHECKIN_OPEN' ? 'text-white' : 'text-zinc-400 group-hover:text-zinc-200' }} transition-colors">Preparing</p>
            @if($status === 'CHECKIN_OPEN')
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-amber-400 to-orange-400"></div>
            @endif
        </button>
    </div>

    <!-- Filters Section (Glassmorphism) -->
    <div class="bg-zinc-900/40 backdrop-blur-xl border border-zinc-800/60 rounded-2xl p-4 md:p-6 shadow-2xl shadow-black/60 relative overflow-hidden group">
        <div class="absolute inset-0 bg-gradient-to-r from-cyan-500/5 to-violet-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 relative z-10">
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

            <!-- Frequency Filter -->
            <div>
                <label for="frequency" class="block text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-2.5 ml-1">Schedule / Frequency</label>
                <select wire:model.live="frequency" id="frequency"
                    class="block w-full px-4 py-3 bg-zinc-950/80 border border-zinc-800 rounded-xl text-sm text-zinc-300 focus:outline-none focus:ring-1 focus:ring-cyan-500/50 focus:border-cyan-500/50 transition-all duration-300 appearance-none cursor-pointer">
                    <option value="">All Schedules</option>
                    <option value="one-time">One-time / Single Event</option>
                    <option value="daily">Daily Recurring</option>
                    <option value="weekly">Weekly Recurring</option>
                    <option value="monthly">Monthly Recurring</option>
                </select>
            </div>

            <!-- Platform Filter -->
            <div>
                <label for="platformId" class="block text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-2.5 ml-1">Platform</label>
                <select wire:model.live="platformId" id="platformId"
                    class="block w-full px-4 py-3 bg-zinc-950/80 border border-zinc-800 rounded-xl text-sm text-zinc-300 focus:outline-none focus:ring-1 focus:ring-violet-500/50 focus:border-violet-500/50 transition-all duration-300 appearance-none cursor-pointer">
                    <option value="">All Platforms</option>
                    @foreach($platforms as $platform)
                        <option value="{{ $platform->id }}">{{ $platform->name }}</option>
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

        <!-- Pagination (Custom Styling) -->
        <div class="mt-12 py-6 border-t border-zinc-900/50">
            {{ $tournaments->links() }}
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
