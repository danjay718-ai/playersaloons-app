@auth
    @include('livewire.landing.authenticated-home')
@elseif(config('features.landing.version') === 'v3')
    {{-- Previous guest design, retained. Re-enable with LANDING_VERSION=v3. --}}
    @include('livewire.landing.landing-page-v3')
@else
@php
    $hero = $sections->get('hero');
    $gamesSection = $sections->get('games');
    $howSection = $sections->get('how_it_works');
    $statsSection = $sections->get('stats');
    $topPlayersSection = $sections->get('top_players');
    $reviewsSection = $sections->get('reviews');
    $faqSection = $sections->get('faq');
    $byGame = $registrationTournaments->groupBy('game_id');
    $review = $playerReviews->first();
@endphp
<main id="guest-main" class="gr-home" x-data="grHome">
    @if($hero)
        <x-guest.hero :hero="$hero" :tournament="$registrationTournaments->first()" />
    @endif

    <livewire:landing.guest-pulse placement="activity" key="guest-recent-results" />

    @if($games->isNotEmpty())
    <section id="games" class="gr-section gr-wrap">
        <x-guest.section-head :eyebrow="__('Games')" :title="$gamesSection?->title ?: __('Pick your table')" :body="__('Tap a table to see what starts next.')" href="/tournaments" :link-label="__('Browse Tournaments')" />
        <div class="gr-tables">
            @foreach($games as $game)
                <x-guest.table-card :game="$game" :tournaments="$byGame->get($game->id, collect())" :index="$loop->index" />
            @endforeach
        </div>
    </section>
    @endif

    @if($howSection && $howSection->activeItems->isNotEmpty())
    <section id="how" class="gr-section gr-wrap">
        <x-guest.section-head :title="$howSection->title" :body="$howSection->body" />
        <ol class="gr-steps" data-gr-steps>
            @foreach($howSection->activeItems as $item)
                <x-guest.step :number="$loop->iteration" :title="$item->title" :body="$item->body" />
            @endforeach
        </ol>
    </section>
    @endif

    @if($statsSection || $topPlayersSection)
    <section id="proof" class="gr-section gr-wrap gr-proof">
        @if($statsSection && count($stats))
            <dl class="gr-stats">@foreach($stats as $stat)<x-guest.stat :value="$stat['value']" :label="$stat['label']" />@endforeach</dl>
        @endif
        @if($topPlayersSection)
            <x-guest.section-head :eyebrow="now()->subWeek()->format('d M').' — '.now()->format('d M')" :title="$topPlayersSection->title" :body="$topPlayersSection->body" />
            <div class="gr-honours">
                @forelse($topPlayers as $player)<x-guest.honour :player="$player" />
                @empty
                    <div class="gr-empty"><p>{{ __('The next name could be yours.') }}</p><span>{{ __('No weekly leaderboard results yet. Completed tournament matches will shape this board.') }}</span><a href="/tournaments">{{ __('Find a tournament') }} ↗</a></div>
                @endforelse
            </div>
        @endif
        @if($reviewsSection && $review)
            <figure class="gr-quote gr-reveal"><blockquote>{{ $review->review }}</blockquote><figcaption><span translate="no">{{ $review->user?->profile?->display_name ?? $review->user?->username }}</span><span>{{ __(':rating out of 5', ['rating' => $review->rating]) }}</span></figcaption></figure>
        @endif
    </section>
    @endif

    <x-guest.faq :section="$faqSection" />

    <x-guest.cta-band />
    <x-guest.join-bar />
</main>
@endauth
