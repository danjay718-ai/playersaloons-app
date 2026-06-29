@props([
    'tournament',
    'actionLabel' => 'View Tournament',
    'actionIcon' => 'arrow-right',
])

@php
    $statusColors = [
        'REGISTRATION_OPEN' => 'text-emerald-400 border-emerald-900/50 bg-emerald-950/85 shadow-[0_0_15px_rgba(52,211,153,0.15)]',
        'REGISTRATION_CLOSED' => 'text-amber-400 border-amber-900/50 bg-amber-950/85',
        'CHECKIN_OPEN' => 'text-fuchsia-400 border-fuchsia-900/50 bg-fuchsia-950/85',
        'CHECKIN_CLOSED' => 'text-rose-400 border-rose-900/50 bg-rose-950/85',
        'BRACKET_GENERATED' => 'text-indigo-400 border-indigo-900/50 bg-indigo-950/85',
        'ONGOING' => 'text-violet-400 border-violet-800/50 bg-violet-950/85 animate-pulse shadow-[0_0_20px_rgba(124,77,255,0.25)]',
        'COMPLETED' => 'text-zinc-400 border-zinc-800 bg-zinc-950/85',
        'CANCELLED' => 'text-red-400 border-red-900/50 bg-red-950/85',
        'REFUNDED' => 'text-orange-400 border-orange-900/50 bg-orange-950/85',
    ];

    $statusValue = $tournament->status->value ?? $tournament->status;
    $gameTranslation = $tournament->game?->translations?->where('locale', app()->getLocale())->first()
        ?? $tournament->game?->translations?->where('locale', 'en')->first();
    $gameName = $gameTranslation?->name ?? $tournament->game?->slug ?? __('Game');
    $registrationsCount = $tournament->getAttribute('registrations_count')
        ?? ($tournament->relationLoaded('registrations') ? $tournament->registrations->count() : 0);
@endphp

<article {{ $attributes->class(['player-tournament-card group']) }}>
    <div class="relative h-44 w-full overflow-hidden">
        <img src="{{ $tournament->banner_url ?? 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=600&auto=format&fit=crop' }}"
             alt="{{ $tournament->name }}"
             class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105">
        <div class="absolute inset-0 bg-gradient-to-t from-zinc-950/95 via-transparent to-zinc-950/40"></div>

        <div class="absolute left-4 right-4 top-4 flex items-center justify-between gap-3">
            <span class="player-badge border-cyan-800/50 bg-zinc-950/85 text-cyan-400">
                {{ $gameName }}
            </span>
            <span class="player-badge {{ $statusColors[$statusValue] ?? 'text-zinc-400 border-zinc-800 bg-zinc-950/85' }}">
                {{ str_replace('_', ' ', $statusValue) }}
            </span>
        </div>
    </div>

    <div class="flex grow flex-col justify-between space-y-5 p-6">
        <div class="space-y-3">
            <h3 class="line-clamp-2 font-orbitron text-xl font-black leading-tight tracking-wide text-white transition-colors duration-300 group-hover:text-cyan-400">
                {{ $tournament->name }}
            </h3>
            <div class="flex items-center justify-between gap-4 text-[10px] font-bold uppercase tracking-wider text-zinc-400">
                <div class="flex items-center gap-1.5">
                    <i data-lucide="calendar" class="h-3.5 w-3.5 text-violet-400"></i>
                    <span>{{ $tournament->start_at ? $tournament->start_at->format('M d, H:i') : 'TBD' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span>Entry Fee:</span>
                    <span class="font-orbitron text-xs font-black tracking-wider text-violet-400">
                        {{ (float) $tournament->entry_fee > 0 ? '$'.number_format((float) $tournament->entry_fee, 2) : 'FREE ENTRY' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 border-t border-zinc-800/40 pt-4">
            <div class="space-y-1">
                <span class="block text-[9px] font-bold uppercase tracking-widest text-zinc-600">Prize Pool</span>
                <span class="font-orbitron text-lg font-black leading-none text-fuchsia-500">
                    ${{ number_format((float) $tournament->prize_pool, 2) }}
                </span>
            </div>
            <div class="space-y-1 text-right">
                <span class="block text-[9px] font-bold uppercase tracking-widest text-zinc-500">Slots</span>
                <div class="flex items-baseline justify-end gap-1 font-mono">
                    <span class="text-sm font-bold text-zinc-200">{{ $registrationsCount }}</span>
                    <span class="text-[10px] text-zinc-600">/</span>
                    <span class="text-[10px] text-zinc-500">{{ $tournament->max_participants }}</span>
                </div>
            </div>
        </div>

        <a href="/tournaments/{{ $tournament->uuid }}/view" wire:navigate class="player-card-action">
            <span>{{ $actionLabel }}</span>
            <i data-lucide="{{ $actionIcon }}" class="h-3.5 w-3.5 text-cyan-400 transition-transform duration-300 group-hover:translate-x-1"></i>
        </a>
    </div>
</article>
