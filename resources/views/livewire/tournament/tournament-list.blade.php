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

    <!-- Filters Section (Glassmorphism) -->
    <div class="bg-zinc-900/40 backdrop-blur-xl border border-zinc-800/60 rounded-2xl p-4 md:p-6 shadow-2xl shadow-black/60 relative overflow-hidden group">
        <div class="absolute inset-0 bg-gradient-to-r from-cyan-500/5 to-violet-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 relative z-10">
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

            <!-- Status Filter -->
            <div>
                <label for="status" class="block text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-2.5 ml-1">Status</label>
                <select wire:model.live="status" id="status"
                    class="block w-full px-4 py-3 bg-zinc-950/80 border border-zinc-800 rounded-xl text-sm text-zinc-300 focus:outline-none focus:ring-1 focus:ring-violet-500/50 focus:border-violet-500/50 transition-all duration-300 appearance-none cursor-pointer">
                    <option value="">All Statuses</option>
                    <option value="REGISTRATION_OPEN">Registration Open</option>
                    <option value="CHECKIN_OPEN">Check-in Open</option>
                    <option value="ONGOING">Ongoing</option>
                    <option value="COMPLETED">Completed</option>
                </select>
            </div>

            <!-- Game Filter -->
            <div>
                <label for="gameId" class="block text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-2.5 ml-1">Game Category</label>
                <select wire:model.live="gameId" id="gameId"
                    class="block w-full px-4 py-3 bg-zinc-950/80 border border-zinc-800 rounded-xl text-sm text-zinc-300 focus:outline-none focus:ring-1 focus:ring-fuchsia-500/50 focus:border-fuchsia-500/50 transition-all duration-300 appearance-none cursor-pointer">
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
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($tournaments as $tournament)
                <!-- Tournament Card (Neon) -->
                <div class="group relative bg-zinc-900/40 backdrop-blur-md border border-zinc-800/80 rounded-3xl p-6 shadow-xl transition-all duration-500 hover:-translate-y-2 hover:border-violet-500/50 hover:shadow-[0_20px_40px_-15px_rgba(124,77,255,0.25)] flex flex-col justify-between overflow-hidden">
                    <!-- Animated Gradient Border Glow -->
                    <div class="absolute inset-0 bg-gradient-to-br from-cyan-500/0 via-violet-500/0 to-fuchsia-500/0 opacity-0 group-hover:opacity-10 transition-opacity duration-500 pointer-events-none"></div>
                    
                    <div class="relative z-10 space-y-5">
                        <!-- Top Row: Game Badge & Status -->
                        <div class="flex items-center justify-between">
                            <span class="text-[9px] font-black text-cyan-400 uppercase tracking-widest bg-cyan-950/30 border border-cyan-800/50 rounded-full px-3 py-1">
                                {{ $tournament->game->translations->where('locale', 'en')->first()?->name ?? $tournament->game->slug }}
                            </span>
                            @php
                                $statusColors = [
                                    'DRAFT' => 'text-zinc-500 border-zinc-800 bg-zinc-900/50',
                                    'PUBLISHED' => 'text-blue-400 border-blue-900/50 bg-blue-950/20',
                                    'REGISTRATION_OPEN' => 'text-emerald-400 border-emerald-900/50 bg-emerald-950/20 shadow-[0_0_15px_rgba(52,211,153,0.1)]',
                                    'REGISTRATION_CLOSED' => 'text-amber-400 border-amber-900/50 bg-amber-950/20',
                                    'CHECKIN_OPEN' => 'text-fuchsia-400 border-fuchsia-900/50 bg-fuchsia-950/20',
                                    'CHECKIN_CLOSED' => 'text-rose-400 border-rose-900/50 bg-rose-950/20',
                                    'BRACKET_GENERATED' => 'text-indigo-400 border-indigo-900/50 bg-indigo-950/20',
                                    'ONGOING' => 'text-violet-400 border-violet-800/50 bg-violet-950/30 animate-pulse shadow-[0_0_20px_rgba(124,77,255,0.2)]',
                                    'COMPLETED' => 'text-zinc-400 border-zinc-800 bg-zinc-900/80',
                                    'CANCELLED' => 'text-red-400 border-red-900/50 bg-red-950/20',
                                    'REFUNDED' => 'text-orange-400 border-orange-900/50 bg-orange-950/20',
                                ];
                                $colorClass = $statusColors[$tournament->status->value ?? $tournament->status] ?? 'text-zinc-500 border-zinc-800 bg-zinc-900/50';
                            @endphp
                            <span class="text-[9px] font-black uppercase tracking-widest border rounded-full px-3 py-1 {{ $colorClass }}">
                                {{ str_replace('_', ' ', $tournament->status->value ?? $tournament->status) }}
                            </span>
                        </div>

                        <!-- Info Area -->
                        <div class="space-y-2">
                            <h3 class="text-xl font-black text-white group-hover:text-cyan-400 transition-colors duration-300 font-orbitron tracking-wide leading-tight line-clamp-2">
                                {{ $tournament->name }}
                            </h3>
                            <div class="flex items-center space-x-2">
                                <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Entry Fee:</span>
                                <span class="text-xs font-black font-orbitron text-violet-400 tracking-wider">
                                    {{ (float)$tournament->entry_fee > 0 ? '$'.number_format((float)$tournament->entry_fee, 2) : 'FREE ENTRY' }}
                                </span>
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
                                <span class="block text-[9px] font-bold text-zinc-600 uppercase tracking-[0.2em]">Slots Left</span>
                                <div class="flex items-baseline justify-end space-x-1">
                                    <span class="text-sm font-bold text-zinc-200">{{ $tournament->registrations()->whereNotIn('status', ['cancelled', 'refunded'])->count() }}</span>
                                    <span class="text-[10px] text-zinc-600">/</span>
                                    <span class="text-[10px] text-zinc-500">{{ $tournament->max_participants }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="mt-8">
                        <a href="/tournaments/{{ $tournament->uuid }}" wire:navigate
                            class="w-full relative flex items-center justify-center space-x-2 py-3.5 px-6 rounded-2xl bg-zinc-950 border border-zinc-800 group-hover:border-cyan-500/50 text-xs font-black uppercase tracking-[0.2em] text-zinc-400 group-hover:text-white group-hover:bg-cyan-500/10 transition-all duration-300 overflow-hidden">
                            <div class="absolute inset-0 translate-x-[-100%] group-hover:translate-x-0 bg-gradient-to-r from-transparent via-cyan-500/10 to-transparent transition-transform duration-700 pointer-events-none"></div>
                            <span>Join Tournament</span>
                            <i data-lucide="zap" class="w-3.5 h-3.5 text-cyan-400 group-hover:scale-125 transition-transform duration-300"></i>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination (Custom Styling) -->
        <div class="mt-12 py-6 border-t border-zinc-900/50">
            {{ $tournaments->links() }}
        </div>
    @else
        <div class="bg-zinc-900/40 backdrop-blur-md border border-zinc-800 rounded-3xl p-16 text-center shadow-2xl relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-b from-transparent to-violet-950/5 pointer-events-none"></div>
            <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-zinc-950 border border-zinc-800 text-zinc-600 mb-6">
                <i data-lucide="ghost" class="w-10 h-10"></i>
            </div>
            <h3 class="text-xl font-black text-zinc-200 font-orbitron tracking-wider">NO MATCHES FOUND</h3>
            <p class="mt-2 text-sm text-zinc-500 max-w-sm mx-auto font-medium">
                Our sensors detect no active tournaments matching these coordinates. Try recalibrating your search filters.
            </p>
        </div>
    @endif
</div>
