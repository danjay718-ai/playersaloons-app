@props([
    'tournament',
    'actionLabel' => 'Join',
    'actionIcon' => 'arrow-right',
    'publicView' => false,
])

@php
    $statusColors = [
        'REGISTRATION_OPEN' => 'text-emerald-400 border-emerald-900/50 bg-emerald-950/85',
        'REGISTRATION_CLOSED' => 'text-amber-400 border-amber-900/50 bg-amber-950/85',
        'CHECKIN_OPEN' => 'text-fuchsia-400 border-fuchsia-900/50 bg-fuchsia-950/85',
        'CHECKIN_CLOSED' => 'text-rose-400 border-rose-900/50 bg-rose-950/85',
        'BRACKET_GENERATED' => 'text-indigo-400 border-indigo-900/50 bg-indigo-950/85',
        'ONGOING' => 'text-violet-400 border-violet-800/50 bg-violet-950/85',
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
    $isHeadToHead = ($tournament->competition_type?->value ?? $tournament->competition_type) === 'head_to_head';
    $platformNames = $tournament->platform_names ?: $tournament->platform?->name ?: __('Any supported platform');
    $viewQuery = $publicView ? '?view=guest' : '';
@endphp

<article
    x-data="tournamentCountdown(@js(now()->getTimestampMs()), @js([
        'startsIn' => __('Starts in'),
        'ongoing' => __('In progress'),
        'completed' => __('Completed'),
        'cancelled' => __('Cancelled'),
        'refunded' => __('Refunded'),
        'pending' => __('Start time pending'),
        'reached' => __('Start time reached'),
    ]))"
    {{ $attributes->class(['player-tournament-card group overflow-hidden']) }}
>
    <div class="relative h-32 w-full overflow-hidden sm:h-36">
        <img src="{{ $tournament->banner_url ?? 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=600&auto=format&fit=crop' }}"
             alt="{{ $tournament->name }}"
             class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105">
        <div class="absolute inset-0 bg-gradient-to-t from-zinc-950 via-zinc-950/15 to-black/30"></div>

        <div class="absolute left-3 right-3 top-3 flex items-center justify-between gap-2">
            <span class="player-badge max-w-[55%] truncate border-cyan-800/50 bg-zinc-950/85 text-cyan-400">{{ $gameName }}</span>
            <span class="player-badge {{ $statusColors[$statusValue] ?? 'text-zinc-400 border-zinc-800 bg-zinc-950/85' }}">
                {{ str_replace('_', ' ', $statusValue) }}
            </span>
        </div>

        <div class="absolute bottom-3 left-3 right-3">
            <span class="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-cyan-500/25 bg-zinc-950/90 px-2.5 py-1.5 text-[11px] font-bold tabular-nums text-cyan-300 shadow-lg backdrop-blur-sm">
                <i data-lucide="timer" class="h-3.5 w-3.5 shrink-0" aria-hidden="true"></i>
                <span class="truncate" x-text="countdown(@js($tournament->start_at?->getTimestampMs()), @js($statusValue))"></span>
            </span>
        </div>
    </div>

    <div class="flex min-h-[245px] grow flex-col gap-4 p-4 sm:p-5">
        <h3 title="{{ $tournament->name }}" class="line-clamp-2 min-h-10 font-orbitron text-base font-black leading-tight tracking-wide text-white transition-colors duration-300 group-hover:text-cyan-400 sm:text-lg">
            {{ $tournament->name }}
        </h3>

        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 border-y border-zinc-800/60 py-3">
            <div>
                <dt class="text-[9px] font-bold uppercase tracking-widest text-zinc-600">{{ __('Entry fee') }}</dt>
                <dd class="mt-1 font-orbitron text-sm font-black text-violet-300">{{ (float) $tournament->entry_fee > 0 ? '$'.number_format((float) $tournament->entry_fee, 2) : __('Free') }}</dd>
            </div>
            <div class="text-right">
                <dt class="text-[9px] font-bold uppercase tracking-widest text-zinc-600">{{ __('Joined players') }}</dt>
                <dd class="mt-1 font-mono text-sm font-bold text-zinc-200">{{ $registrationsCount }}</dd>
            </div>
            <div>
                <dt class="text-[9px] font-bold uppercase tracking-widest text-zinc-600">{{ $isHeadToHead ? __('Max players') : __('Max teams') }}</dt>
                <dd class="mt-1 font-mono text-sm font-bold text-zinc-200">{{ $tournament->max_participants }}</dd>
            </div>
            <div class="min-w-0 text-right">
                <dt class="text-[9px] font-bold uppercase tracking-widest text-zinc-600">{{ __('Platforms') }}</dt>
                <dd title="{{ $platformNames }}" class="mt-1 truncate text-xs font-semibold text-cyan-300">{{ $platformNames }}</dd>
            </div>
        </dl>

        <a href="/tournaments/{{ $tournament->uuid }}/view{{ $viewQuery }}" wire:navigate class="player-card-action mt-auto">
            <span>{{ $isHeadToHead ? __('Join Head-to-Head') : __($actionLabel) }}</span>
            <i data-lucide="{{ $actionIcon }}" class="h-3.5 w-3.5 text-cyan-400 transition-transform duration-300 group-hover:translate-x-1"></i>
        </a>
    </div>
</article>
