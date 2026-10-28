@props([
    'tournament',
    'actionLabel' => 'Join',
    'actionIcon' => 'arrow-right',
    'publicView' => false,
])

@php
    $statusValue = $tournament->status->value ?? $tournament->status;
    $gameTranslation = $tournament->game?->translations?->where('locale', app()->getLocale())->first()
        ?? $tournament->game?->translations?->where('locale', 'en')->first();
    $gameName = $gameTranslation?->name ?? $tournament->game?->slug ?? __('Game');
    $registrationsCount = (int) ($tournament->getAttribute('registrations_count')
        ?? ($tournament->relationLoaded('registrations') ? $tournament->registrations->count() : 0));
    $maxParticipants = max(1, (int) $tournament->max_participants);
    $isHeadToHead = ($tournament->competition_type?->value ?? $tournament->competition_type) === 'head_to_head';
    $platformNames = $tournament->platform_names ?: $tournament->platform?->name ?: __('Any supported platform');
    $viewQuery = $publicView ? '?view=guest' : '';
    $cleanTitle = $tournament->display_name;
    $pipTotal = min(10, $maxParticipants);
    $pipFilled = (int) round(($registrationsCount / $maxParticipants) * $pipTotal);
    $symbol = \App\Shared\Support\Currency::symbol();

    if ($tournament->finalized_first_prize !== null) {
        $prizeAmount = (float) $tournament->finalized_first_prize + (float) ($tournament->finalized_second_prize ?? 0);
    } elseif ((int) $tournament->workflow_version === 2) {
        $v2Calc = app(\App\Modules\Tournament\Services\V2PrizePolicy::class)->calculate($tournament, $maxParticipants);
        $prizeAmount = ((int) ($v2Calc['first_minor'] ?? 0) + (int) ($v2Calc['second_minor'] ?? 0)) / 100;
    } else {
        $prizeAmount = (float) ($tournament->advertised_prize_pool ?? $tournament->prize_pool ?? $tournament->prize_1st ?? 0);
        if ($prizeAmount <= 0 && (float) $tournament->entry_fee > 0) {
            $prizeAmount = round((float) $tournament->entry_fee * $maxParticipants * 0.9, 2);
        }
    }

    $entryLabel = (float) $tournament->entry_fee > 0
        ? $symbol.number_format((float) $tournament->entry_fee, 2)
        : __('Free');
    $prizeLabel = $symbol.number_format($prizeAmount, 2);
    $detailUrl = '/tournaments/'.$tournament->uuid.'/view'.$viewQuery;
@endphp

<article {{ $attributes->class(['player-tournament-card group']) }} data-start-time="{{ $tournament->start_at?->getTimestampMs() }}">
    {{-- Full-card stretch link so hovering/clicking anywhere activates the row and lights up the arrow --}}
    <a
        href="{{ $detailUrl }}"
        wire:navigate
        class="absolute inset-0 z-10 rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-400"
        aria-label="{{ $cleanTitle }}"
    >
        <span class="sr-only">
            {{ $isHeadToHead ? __('Join Head-to-Head') : __($actionLabel) }} · {{ __('Starts in') }} · {{ __('Joined players') }} {{ $registrationsCount }} / {{ $isHeadToHead ? __('Max players') : __('Max teams') }} {{ $tournament->max_participants }} · {{ __('Platforms') }}: {{ $platformNames }}
        </span>
    </a>

    {{-- 1. Compact Square Game Thumbnail --}}
    <div class="flex items-center gap-3 md:block">
        <div class="relative h-13 w-13 shrink-0 overflow-hidden rounded-xl border border-white/10 bg-zinc-950 transition-colors group-hover:border-lime-400/50">
            <img src="{{ $tournament->banner_url ?? $tournament->game?->cardImageUrl() ?? 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=300&auto=format&fit=crop' }}"
                 alt="{{ $cleanTitle }}"
                 class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                 loading="lazy">
        </div>
        <div class="min-w-0 flex-1 md:hidden">
            <h3 title="{{ $cleanTitle }}" class="truncate font-orbitron text-sm font-black text-white transition-colors group-hover:text-lime-300">
                {{ $cleanTitle }}@if($cleanTitle !== $tournament->name)<span class="sr-only"> {{ $tournament->name }}</span>@endif
            </h3>
            <p class="mt-0.5 truncate text-xs text-zinc-400">
                <span class="font-bold text-cyan-300">{{ $gameName }}</span>
                <span class="text-zinc-600">·</span>
                <span>{{ $isHeadToHead ? __('1v1 Head-to-Head') : __('Tournament') }}</span>
                @if(! empty($tournament->frequency))
                    <span class="text-zinc-600">·</span>
                    <span>{{ __(ucfirst((string) $tournament->frequency)) }}</span>
                @endif
            </p>
        </div>
    </div>

    {{-- 2. Competition Title + Game/Type (Desktop) --}}
    <div class="hidden min-w-0 md:block">
        <h3 title="{{ $cleanTitle }}" class="truncate font-orbitron text-sm font-black tracking-wide text-white transition-colors group-hover:text-lime-300 lg:text-base">
            {{ $cleanTitle }}@if($cleanTitle !== $tournament->name)<span class="sr-only"> {{ $tournament->name }}</span>@endif
        </h3>
        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-zinc-400">
            <span class="font-bold text-cyan-300">{{ $gameName }}</span>
            <span class="text-zinc-600">·</span>
            <span>{{ $isHeadToHead ? __('1v1 Head-to-Head') : __('Tournament') }}</span>
            @if(! empty($tournament->frequency))
                <span class="text-zinc-600">·</span>
                <span>{{ __(ucfirst((string) $tournament->frequency)) }}</span>
            @endif
        </div>
    </div>

    {{-- Mobile Compact Metrics + Glowing Arrow --}}
    <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-2.5 md:hidden">
        <div class="flex flex-wrap items-center gap-3 text-xs">
            <span class="font-orbitron font-black text-white">{{ $entryLabel }}</span>
            <span class="text-zinc-600">·</span>
            <span class="font-orbitron font-black text-lime-300">{{ $prizeLabel }}</span>
            <span class="text-zinc-600">·</span>
            <span class="font-mono font-bold tabular-nums text-zinc-200">{{ $registrationsCount }}/{{ $tournament->max_participants }}</span>
        </div>
        <span class="player-card-action !h-8 !w-8 shrink-0" aria-hidden="true">
            <i data-lucide="arrow-right" class="h-4 w-4"></i>
        </span>
    </div>

    {{-- 3. Entry Fee (Desktop — no inner label) --}}
    <div class="hidden md:block">
        <span class="font-orbitron text-sm font-black text-white">
            {{ $entryLabel }}
        </span>
    </div>

    {{-- 4. Prize (Desktop — right beside Entry Fee, no inner label) --}}
    <div class="hidden md:block">
        <span class="font-orbitron text-sm font-black text-lime-300">
            {{ $prizeLabel }}
        </span>
    </div>

    {{-- 5. Full Capacity / Seats (Desktop — no inner label) --}}
    <div class="hidden items-center gap-2.5 md:flex">
        <div class="flex items-center gap-1" aria-hidden="true">
            @for($i = 0; $i < $pipTotal; $i++)
                <span class="h-2 w-1.5 rounded-sm {{ $i < $pipFilled ? 'bg-cyan-400 shadow-[0_0_6px_#22e6ff]' : ($i === $pipFilled && $statusValue === 'REGISTRATION_OPEN' ? 'bg-lime-400/80 animate-pulse' : 'bg-white/10') }}"></span>
            @endfor
        </div>
        <span class="font-mono text-xs font-bold tabular-nums text-zinc-100">
            <span>{{ $registrationsCount }}</span>
            <span class="text-zinc-500">/</span>
            <span>{{ $tournament->max_participants }}</span>
        </span>
    </div>

    {{-- 6. Glowing Arrow on Card Hover (Desktop) --}}
    <div class="hidden items-center justify-end md:flex" aria-hidden="true">
        <span class="player-card-action">
            <i data-lucide="arrow-right" class="h-4 w-4"></i>
        </span>
    </div>
</article>
