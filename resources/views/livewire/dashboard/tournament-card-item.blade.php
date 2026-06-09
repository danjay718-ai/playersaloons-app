<div class="group relative bg-[#0c081d]/50 border border-purple-500/15 hover:border-purple-500/35 rounded-2xl shadow-xl transition-all duration-300 hover:-translate-y-1.5 flex flex-col justify-between overflow-hidden">
    <!-- Image banner -->
    <div class="relative h-36 w-full overflow-hidden">
        <img src="{{ $tournament->banner_url ?? 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=600&auto=format&fit=crop' }}" 
             alt="{{ $tournament->name }}" 
             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
        <div class="absolute inset-0 bg-gradient-to-t from-[#0c081d] via-transparent to-[#0c081d]/50"></div>
        
        <!-- Game badge & status on top of banner -->
        <div class="absolute top-3 left-3 right-3 flex justify-between items-center">
            <span class="text-[9px] font-black font-orbitron uppercase tracking-widest text-cyan-400 bg-cyan-950/80 border border-cyan-800/60 px-2.5 py-1 rounded">
                {{ $tournament->game->translations->where('locale', 'en')->first()?->name ?? $tournament->game->slug }}
            </span>
            @php
                $statusColors = [
                    'DRAFT' => 'text-zinc-400 bg-zinc-950/80 border-zinc-800',
                    'PUBLISHED' => 'text-blue-400 bg-blue-950/80 border-blue-900/50',
                    'REGISTRATION_OPEN' => 'text-emerald-400 bg-emerald-950/80 border-emerald-900/50 shadow-[0_0_15px_rgba(52,211,153,0.2)]',
                    'REGISTRATION_CLOSED' => 'text-amber-400 bg-amber-950/80 border-amber-900/50',
                    'CHECKIN_OPEN' => 'text-fuchsia-400 bg-fuchsia-950/80 border-fuchsia-900/50',
                    'CHECKIN_CLOSED' => 'text-rose-400 bg-rose-950/80 border-rose-900/50',
                    'BRACKET_GENERATED' => 'text-indigo-400 bg-indigo-950/80 border-indigo-900/50',
                    'ONGOING' => 'text-violet-400 bg-violet-950/80 border-violet-800/50 animate-pulse shadow-[0_0_20px_rgba(124,77,255,0.3)]',
                    'COMPLETED' => 'text-zinc-400 bg-zinc-950/80 border-zinc-800',
                    'CANCELLED' => 'text-red-400 bg-red-950/80 border-red-900/50',
                    'REFUNDED' => 'text-orange-400 bg-orange-950/80 border-orange-900/50',
                ];
                $statusVal = $tournament->status->value ?? $tournament->status;
                $colorClass = $statusColors[$statusVal] ?? 'text-zinc-400 bg-zinc-950/80 border-zinc-800';
            @endphp
            <span class="text-[9px] font-black font-orbitron uppercase tracking-widest border px-2.5 py-1 rounded {{ $colorClass }}">
                {{ str_replace('_', ' ', $statusVal) }}
            </span>
        </div>
    </div>

    <!-- Content padding -->
    <div class="p-5 flex-grow flex flex-col justify-between space-y-4">
        <!-- Main details -->
        <div class="space-y-1.5">
            <h4 class="text-sm font-black font-orbitron tracking-wider text-white uppercase group-hover:text-purple-300 transition-colors line-clamp-1">
                {{ $tournament->name }}
            </h4>
            <div class="flex items-center justify-between text-[10px] text-zinc-400 font-medium">
                <div class="flex items-center space-x-1.5">
                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-purple-400"></i>
                    <span>{{ $tournament->start_at ? $tournament->start_at->format('M d, H:i') : 'TBD' }}</span>
                </div>
                <div class="flex items-center space-x-1.5 font-mono">
                    <i data-lucide="users" class="w-3.5 h-3.5 text-purple-400"></i>
                    <span>
                        {{ $tournament->registrations()->whereNotIn('status', ['cancelled', 'refunded'])->count() }} / {{ $tournament->max_participants }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Prize Pool & Fee Row -->
        <div class="border-t border-purple-500/10 pt-3 flex items-center justify-between">
            <div>
                <span class="block text-[8px] text-zinc-500 font-bold uppercase tracking-wider">PRIZE POOL</span>
                <span class="text-sm font-black text-emerald-400 font-orbitron tracking-wider">
                    ${{ number_format((float)$tournament->prize_pool, 2) }}
                </span>
            </div>
            <div class="text-right">
                <span class="block text-[8px] text-zinc-500 font-bold uppercase tracking-wider">ENTRY FEE</span>
                <span class="text-xs font-bold text-zinc-200 font-orbitron tracking-wider">
                    {{ (float)$tournament->entry_fee > 0 ? '$'.number_format((float)$tournament->entry_fee, 2) : 'FREE' }}
                </span>
            </div>
        </div>

        <!-- Action button -->
        <a href="/tournaments/{{ $tournament->uuid }}" wire:navigate
           class="w-full text-center bg-gradient-to-r from-purple-600 via-fuchsia-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-[10px] font-black font-orbitron uppercase tracking-widest text-white py-2.5 rounded-xl transition-all duration-300 shadow-[0_4px_15px_rgba(168,85,247,0.25)] hover:shadow-[0_4px_20px_rgba(217,70,239,0.5)]">
            VIEW TOURNAMENT
        </a>
    </div>
</div>
