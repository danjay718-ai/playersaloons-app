@if(config('features.landing.version') === 'v3')
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
    $ctaSection = $sections->get('cta');
    $emptyLeaderboard = $sections->get('leaderboard_empty');
    $byGame = $registrationTournaments->groupBy('game_id');
    $review = $playerReviews->first();
    $gamesBaseUrl = $gamesSection?->cta_url ?: '/tournaments';
    $gamesBrowseUrl = $category !== ''
        ? $gamesBaseUrl.(str_contains($gamesBaseUrl, '?') ? '&' : '?').http_build_query(['category' => $category])
        : $gamesBaseUrl;
@endphp
<main id="guest-main" class="gr-home" x-data="grHome">
    @if($hero)
        <x-guest.hero :hero="$hero" :empty-section="$sections->get('competition_empty')" :tournament="$heroTournament ?? $registrationTournaments->first()" />
    @endif

    <livewire:landing.guest-pulse placement="activity" key="guest-recent-results" />

    @if($gamesSection && ($allGamesCount ?? $games->count()) > 0)
    <section id="games" class="gr-section gr-wrap">
        <x-guest.section-head :eyebrow="$gamesSection->subtitle" :title="$gamesSection->title" :body="$gamesSection->body" :href="$gamesBrowseUrl" :link-label="$gamesSection->cta_label" />

        <div class="gr-cat-bar">
            <div role="tablist" aria-label="{{ __('Game categories') }}" x-data="categoryTablist()" @keydown="onKeydown($event)" class="gr-cat-tabs">
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $category === '' ? 'true' : 'false' }}"
                    wire:click="selectCategory('')"
                    class="gr-cat-tab {{ $category === '' ? 'is-active' : '' }}"
                >
                    <span>{{ __('All Games') }}</span>
                    <span class="gr-cat-count">{{ $allGamesCount ?? $games->count() }}</span>
                </button>
                @foreach($gameCategories ?? collect() as $gameCategory)
                    @php($isSelectedCategory = $category === $gameCategory->slug)
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $isSelectedCategory ? 'true' : 'false' }}"
                        wire:key="home-cat-{{ $gameCategory->id }}"
                        wire:click="selectCategory('{{ $gameCategory->slug }}')"
                        class="gr-cat-tab {{ $isSelectedCategory ? 'is-active' : '' }}"
                    >
                        <span>{{ $gameCategory->name }}</span>
                        <span class="gr-cat-count">{{ (int) $gameCategory->active_games_count }}</span>
                    </button>
                @endforeach
            </div>

            <div class="gr-cat-tools">
                <span wire:loading wire:target="selectCategory,gameSearch,clearGameFilters" role="status" class="gr-cat-loading">{{ __('Loading…') }}</span>
                <label class="gr-cat-search">
                    <span class="sr-only">{{ __('Search game') }}</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                    <input
                        type="search"
                        wire:model.live.debounce.250ms="gameSearch"
                        placeholder="{{ __('Search games') }}"
                        autocomplete="off"
                    >
                </label>
            </div>
        </div>

        <div class="gr-tables" wire:loading.class="is-loading" wire:target="selectCategory,gameSearch,clearGameFilters">
            @forelse($games as $game)
                <x-guest.table-card :game="$game" :tournaments="$byGame->get($game->id, collect())" :index="$loop->index" />
            @empty
                <div class="gr-empty" role="status">
                    <p>{{ __('No games found in this view.') }}</p>
                    <span>{{ __('Try another category or clear your game search to explore every open table.') }}</span>
                    <button type="button" wire:click="clearGameFilters" class="gr-empty-btn">{{ __('Show All Games') }}</button>
                </div>
            @endforelse
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
                    @if($emptyLeaderboard)
                        <div class="gr-empty">
                            <p>{{ $emptyLeaderboard->title }}</p>
                            <span>{{ $emptyLeaderboard->body }}</span>
                            @if($emptyLeaderboard->cta_label && $emptyLeaderboard->cta_url)
                                <a href="{{ $emptyLeaderboard->cta_url }}">{{ $emptyLeaderboard->cta_label }} ↗</a>
                            @endif
                        </div>
                    @endif
                @endforelse
            </div>
        @endif
        @if($reviewsSection && $review)
            <figure class="gr-quote gr-reveal"><blockquote>{{ $review->review }}</blockquote><figcaption><span translate="no">{{ $review->user?->profile?->display_name ?? $review->user?->username }}</span><span>{{ __(':rating out of 5', ['rating' => $review->rating]) }}</span></figcaption></figure>
        @endif
    </section>
    @endif

    @if($faqSection)
        <x-guest.faq :section="$faqSection" />
    @endif

    @if($ctaSection)
        <x-guest.cta-band :section="$ctaSection" />
        <x-guest.join-bar :section="$ctaSection" />
    @endif
</main>
@endif
