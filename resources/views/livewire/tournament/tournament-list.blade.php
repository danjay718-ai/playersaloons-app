<div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div>
            <h1 class="text-3xl md:text-4xl font-black font-orbitron tracking-wider bg-gradient-to-r from-violet-400 via-fuchsia-400 to-indigo-400 bg-clip-text text-transparent">
                TOURNAMENTS
            </h1>
            <p class="text-sm text-zinc-400 mt-1">
                Browse active tournaments, register to compete, and track current brackets.
            </p>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 md:p-6 shadow-lg shadow-black/40">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- Search -->
            <div>
                <label for="search" class="block text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2">Search</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-zinc-500">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </span>
                    <input wire:model.live.debounce.300ms="search" id="search" type="text"
                        class="block w-full pl-9 pr-3 py-2 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-200 placeholder-zinc-650 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200"
                        placeholder="Search tournament name...">
                </div>
            </div>

            <!-- Status Filter -->
            <div>
                <label for="status" class="block text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2">Status</label>
                <select wire:model.live="status" id="status"
                    class="block w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-300 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200">
                    <option value="">All Statuses</option>
                    <option value="REGISTRATION_OPEN">Registration Open</option>
                    <option value="CHECKIN_OPEN">Check-in Open</option>
                    <option value="ONGOING">Ongoing</option>
                    <option value="COMPLETED">Completed</option>
                </select>
            </div>

            <!-- Game Filter -->
            <div>
                <label for="gameId" class="block text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2">Game</label>
                <select wire:model.live="gameId" id="gameId"
                    class="block w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-300 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200">
                    <option value="">All Games</option>
                    @foreach($games as $game)
                        <option value="{{ $game->id }}">
                            {{ $game->translations->where('locale', 'en')->first()?->name ?? $game->slug }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Tournament Grid -->
    @if($tournaments->count() > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($tournaments as $tournament)
                <!-- Card -->
                <div class="bg-zinc-900 border border-zinc-800 hover:border-zinc-700/80 rounded-xl p-5 shadow-lg shadow-black/20 flex flex-col justify-between transition-all duration-300 relative group overflow-hidden">
                    <!-- Glow effect on hover -->
                    <div class="absolute inset-0 bg-gradient-to-br from-violet-600/0 via-violet-600/0 to-violet-600/0 group-hover:to-violet-600/5 transition-all duration-300 pointer-events-none"></div>
                    
                    <div class="space-y-4">
                        <!-- Top tag & Badge -->
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-violet-400 uppercase tracking-wider bg-violet-950/40 border border-violet-900/60 rounded px-2.5 py-0.5">
                                {{ $tournament->game->translations->where('locale', 'en')->first()?->name ?? $tournament->game->slug }}
                            </span>
                            @php
                                $statusColors = [
                                    'DRAFT' => 'bg-zinc-800 text-zinc-400 border-zinc-700',
                                    'PUBLISHED' => 'bg-blue-950/30 text-blue-400 border-blue-900/40',
                                    'REGISTRATION_OPEN' => 'bg-emerald-950/30 text-emerald-400 border-emerald-900/40 shadow-sm shadow-emerald-500/5',
                                    'REGISTRATION_CLOSED' => 'bg-amber-950/30 text-amber-400 border-amber-900/40',
                                    'CHECKIN_OPEN' => 'bg-fuchsia-950/30 text-fuchsia-400 border-fuchsia-900/40',
                                    'CHECKIN_CLOSED' => 'bg-rose-950/30 text-rose-400 border-rose-900/40',
                                    'BRACKET_GENERATED' => 'bg-indigo-950/30 text-indigo-400 border-indigo-900/40',
                                    'ONGOING' => 'bg-violet-950/40 text-violet-300 border-violet-850/60 animate-pulse',
                                    'COMPLETED' => 'bg-zinc-800/80 text-zinc-400 border-zinc-800',
                                    'CANCELLED' => 'bg-red-950/30 text-red-400 border-red-900/40',
                                    'REFUNDED' => 'bg-orange-950/30 text-orange-400 border-orange-900/40',
                                ];
                                $colorClass = $statusColors[$tournament->status->value ?? $tournament->status] ?? 'bg-zinc-800 text-zinc-400 border-zinc-700';
                            @endphp
                            <span class="text-[10px] font-bold uppercase tracking-widest border rounded px-2 py-0.5 {{ $colorClass }}">
                                {{ str_replace('_', ' ', $tournament->status->value ?? $tournament->status) }}
                            </span>
                        </div>

                        <!-- Title -->
                        <div>
                            <h3 class="text-lg font-bold text-zinc-100 group-hover:text-white transition-colors line-clamp-1">
                                {{ $tournament->name }}
                            </h3>
                            <p class="text-xs text-zinc-500 mt-1">
                                Entry: <span class="text-zinc-400 font-semibold font-orbitron">{{ (float)$tournament->entry_fee > 0 ? '$'.number_format((float)$tournament->entry_fee, 2) : 'FREE' }}</span>
                            </p>
                        </div>

                        <!-- Stats grid -->
                        <div class="grid grid-cols-2 gap-4 py-2 border-t border-b border-zinc-800/60">
                            <div>
                                <span class="block text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Prize Pool</span>
                                <span class="text-md font-black text-fuchsia-400 font-orbitron mt-0.5 block">
                                    ${{ number_format((float)$tournament->prize_pool, 2) }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Participants</span>
                                <span class="text-sm font-bold text-zinc-300 mt-0.5 block">
                                    {{ $tournament->registrations()->whereNotIn('status', ['cancelled', 'refunded'])->count() }} / {{ $tournament->max_participants }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Button -->
                    <div class="mt-5">
                        <a href="/tournaments/{{ $tournament->uuid }}" wire:navigate
                            class="w-full flex items-center justify-center space-x-2 py-2 px-4 rounded-lg bg-zinc-950 border border-zinc-850 hover:border-zinc-700 text-xs font-bold text-zinc-300 hover:text-white transition-all duration-200">
                            <span>View Details</span>
                            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-8">
            {{ $tournaments->links() }}
        </div>
    @else
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-12 text-center shadow-lg shadow-black/20">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-zinc-950 border border-zinc-850 text-zinc-500">
                <i data-lucide="folder-open" class="w-6 h-6"></i>
            </div>
            <h3 class="mt-4 text-sm font-bold text-zinc-300">No Tournaments Found</h3>
            <p class="mt-1 text-xs text-zinc-500 max-w-sm mx-auto">
                No tournaments matched your filters. Try adjusting your search query, status, or game options.
            </p>
        </div>
    @endif
</div>
