@props(['tournament' => null])
@php
    $symbol = \App\Shared\Support\Currency::symbol();
    $arenaTimezone = app(\App\Modules\Tournament\Services\TournamentTimezone::class)->value();
@endphp
<aside {{ $attributes->class(['gr-ticket']) }} aria-label="{{ __('Open registration tournaments') }}">
    @if($tournament)
        <div class="gr-ticket-top">
            <span class="gr-live"><i aria-hidden="true"></i>{{ __('Live now') }}</span>
            <span class="gr-ticket-game">{{ $tournament->game->localizedName() }}</span>
        </div>
        <h2 class="gr-ticket-name">{{ $tournament->display_name }}@if($tournament->display_name !== $tournament->name)<span class="sr-only"> {{ $tournament->name }}</span>@endif</h2>
        <p class="gr-ticket-time">{{ $tournament->start_at->copy()->setTimezone($arenaTimezone)->format('d M · H:i') }} {{ $arenaTimezone }}</p>
        <p class="gr-ticket-label">{{ __('Starts in') }}</p>
        <x-guest.countdown :at="$tournament->start_at" mode="boxes" />
        <div class="gr-ticket-tear" aria-hidden="true"></div>
        <dl class="gr-ticket-facts">
            <div><dt>{{ __('Entry') }}</dt><dd>{{ (float) $tournament->entry_fee > 0 ? $symbol.number_format((float) $tournament->entry_fee, 2) : __('Free') }}</dd></div>
            <div><dt>{{ __('Players') }}</dt><dd>{{ $tournament->registrations_count }}<span> / {{ $tournament->max_participants }}</span></dd></div>
        </dl>
        <x-guest.button :href="route('tournaments.view', $tournament->uuid)" class="gr-block">{{ __('Take a seat') }}</x-guest.button>
    @else
        <div class="gr-ticket-top"><span class="gr-live is-idle"><i aria-hidden="true"></i>{{ __('Next seat') }}</span></div>
        <h2 class="gr-ticket-name">{{ __('Your next competition starts here.') }}</h2>
        <p class="gr-ticket-time">{{ __('New tournaments will appear when registration opens. Explore the games while you wait.') }}</p>
        <div class="gr-ticket-tear" aria-hidden="true"></div>
        <x-guest.button href="/tournaments" class="gr-block">{{ __('Browse Tournaments') }}</x-guest.button>
    @endif
</aside>
