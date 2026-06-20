<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    {{-- Bell Button --}}
    <button @click="open = !open"
            class="relative p-2 rounded-lg bg-zinc-900/50 border border-zinc-800 hover:border-purple-500/40 text-zinc-400 hover:text-purple-300 transition-all duration-200">
        <i data-lucide="bell" class="w-5 h-5"></i>
        @if($unreadCount > 0)
            <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-fuchsia-500 shadow-[0_0_6px_rgba(244,63,94,0.8)]"></span>
        @endif
    </button>

    {{-- Dropdown Panel --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="transform opacity-0 scale-95"
         x-transition:enter-end="transform opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="transform opacity-100 scale-100"
         x-transition:leave-end="transform opacity-0 scale-95"
         class="absolute right-0 mt-3 w-80 bg-[#0e0a24] border border-purple-500/20 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.8)] z-50 py-2"
         x-cloak>

        {{-- Header --}}
        <div class="px-4 py-2 border-b border-purple-500/10 flex justify-between items-center">
            <span class="font-orbitron font-bold text-xs text-zinc-300 uppercase tracking-wider">SYSTEM LOGS</span>
            @if($unreadCount > 0)
                <span class="text-[9px] bg-purple-950 text-purple-400 border border-purple-900 px-2 py-0.5 rounded-full font-bold">
                    {{ $unreadCount }} NEW
                </span>
            @endif
        </div>

        {{-- Notification List --}}
        <div class="max-h-60 overflow-y-auto">
            @forelse($notifications as $notification)
                <button wire:click="markAsRead({{ $notification->id }})"
                        class="w-full text-left block px-4 py-3 hover:bg-purple-950/20 border-b border-purple-500/5 transition-colors {{ is_null($notification->read_at) ? 'bg-purple-950/10' : '' }}">
                    <div class="flex items-start space-x-3">
                        <div class="p-1.5 bg-purple-900/30 rounded-lg text-purple-400 mt-0.5 flex-shrink-0">
                            @php
                                $icon = match(true) {
                                    str_contains($notification->type, 'wallet'), str_contains($notification->type, 'deposit'), str_contains($notification->type, 'prize') => 'wallet',
                                    str_contains($notification->type, 'tournament') => 'trophy',
                                    str_contains($notification->type, 'match') => 'swords',
                                    str_contains($notification->type, 'kyc') => 'shield-check',
                                    default => 'bell',
                                };
                            @endphp
                            <i data-lucide="{{ $icon }}" class="w-3.5 h-3.5"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold {{ is_null($notification->read_at) ? 'text-zinc-200' : 'text-zinc-400' }} truncate">
                                {{ $notification->title }}
                            </p>
                            <p class="text-[10px] text-zinc-500 mt-0.5 line-clamp-2">{{ $notification->message }}</p>
                            <p class="text-[9px] text-zinc-600 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                        @if(is_null($notification->read_at))
                            <div class="flex-shrink-0 w-1.5 h-1.5 rounded-full bg-fuchsia-500 mt-1.5"></div>
                        @endif
                    </div>
                </button>
            @empty
                <div class="px-4 py-6 text-center">
                    <i data-lucide="bell-off" class="w-6 h-6 text-zinc-600 mx-auto mb-2"></i>
                    <p class="text-xs text-zinc-600">No notifications yet.</p>
                </div>
            @endforelse
        </div>

        {{-- Footer --}}
        @if($unreadCount > 0)
            <div class="px-4 py-1.5 text-center border-t border-purple-500/10">
                <button wire:click="markAllRead"
                        class="text-[10px] font-bold text-purple-400 hover:text-purple-300 uppercase tracking-widest">
                    Mark all as read
                </button>
            </div>
        @endif
    </div>
</div>
