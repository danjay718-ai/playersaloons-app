@props(['game', 'tournaments', 'index' => 0])
@php
    $symbol = \App\Shared\Support\Currency::symbol();
    $arenaTimezone = app(\App\Modules\Tournament\Services\TournamentTimezone::class)->value();
    $next = $tournaments->first();
@endphp
<article class="gr-table gr-a{{ $index % 5 }} gr-reveal" x-data="grTable" :class="{ 'is-open': open }" @pointermove="move($event)" @pointerleave="leave()">
    @if($game->bannerUrl())<img class="gr-table-art" src="{{ $game->bannerUrl() }}" alt="" width="600" height="700" loading="lazy" draggable="false">@else<img class="gr-table-art is-brand" src="/gamersrival-logo.webp" alt="" width="500" height="384" loading="lazy" draggable="false">@endif
    <button type="button" class="gr-table-head" @click="open = !open" :aria-expanded="open.toString()" aria-controls="gr-table-{{ $game->id }}">
        <span class="gr-table-count">{{ $tournaments->count() }} <small>{{ __('open') }}</small></span>
        <span class="gr-table-name">{{ $game->localizedName() }}</span>
        <span class="gr-table-next">@if($next){{ __('Next starts in') }} <x-guest.countdown :at="$next->start_at" />@else{{ __('No open seats yet') }}@endif</span>
        <span class="gr-table-toggle" aria-hidden="true" x-text="open ? '−' : '+'">+</span>
    </button>
    <div class="gr-table-panel" id="gr-table-{{ $game->id }}" x-show="open" x-cloak x-transition.opacity>
        @foreach($tournaments->take(2) as $tournament)
            <a href="{{ route('tournaments.view', $tournament->uuid) }}" class="gr-table-row">
                <span><strong>{{ $tournament->display_name }}@if($tournament->display_name !== $tournament->name)<span class="sr-only"> {{ $tournament->name }}</span>@endif</strong><small>{{ $tournament->start_at->copy()->setTimezone($arenaTimezone)->format('d M · H:i') }} {{ $arenaTimezone }}</small></span>
                <b>{{ (float) $tournament->entry_fee > 0 ? $symbol.number_format((float) $tournament->entry_fee, 2) : __('Free') }}</b>
            </a>
        @endforeach
        <a href="{{ route('games.show', $game) }}" class="gr-table-explore">{{ __('Explore :game', ['game' => $game->localizedName()]) }} <span aria-hidden="true">↗</span></a>
    </div>
</article>
