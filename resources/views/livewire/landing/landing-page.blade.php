@auth
    @include('livewire.landing.authenticated-home')
@else
@php
    $hero = $sections->get('hero');
    $gamesSection = $sections->get('games');
    $howSection = $sections->get('how_it_works');
    $statsSection = $sections->get('stats');
    $topPlayersSection = $sections->get('top_players');
    $featuresSection = $sections->get('features');
    $reviewsSection = $sections->get('reviews');
@endphp
<main id="guest-main" class="guest-home" x-data="guestHome">
    @if($hero)
    <section class="guest-hero">
        <div class="guest-hero-media" wire:ignore x-data="{ playing: false, init() { if (!matchMedia('(prefers-reduced-motion: reduce)').matches) this.$refs.video?.play().then(() => this.playing = true).catch(() => {}); } }">
            @if($hero->media_path)
                <video x-ref="video" muted loop playsinline preload="metadata" poster="/images/guest-highlights.webp" @play="playing = true" @pause="playing = false" aria-label="{{ __('Competitive gaming highlights') }}"><source src="{{ $hero->media_path }}" type="video/mp4"></video>
                <button type="button" class="guest-video-control" @click="playing ? $refs.video.pause() : $refs.video.play()" x-text="playing ? @js(__('Pause video')) : @js(__('Play video'))">{{ __('Play video') }}</button>
            @endif
        </div>
        <div class="guest-hero-stage guest-wrap">
            <div class="guest-hero-intro">
                <h1>{{ $hero->title }}</h1>
                <p>{{ $hero->body }}</p>
                <div class="guest-hero-actions">
                    @if($hero->cta_label && $hero->cta_url)<a class="guest-button" href="{{ $hero->cta_url }}">{{ __($hero->cta_label) }} <span aria-hidden="true">↗</span></a>@endif
                    <a class="guest-button-secondary" href="/register">{{ __('Create Account') }} <span aria-hidden="true">→</span></a>
                </div>
                <nav class="guest-arena-links" aria-label="{{ __('Explore the platform') }}">
                    @if($gamesSection)<a href="#games">{{ __('Find your game') }} ↗</a>@endif
                    @if($topPlayersSection)<a href="#achievements">{{ __('Weekly honours') }} ↗</a>@endif
                </nav>
            </div>
            <aside class="guest-featured-slider" x-data="guestTournamentSlider" aria-label="{{ __('Open registration tournaments') }}" data-ui data-started="{{ __('Starting now') }}" data-days="{{ __('d') }}" data-hours="{{ __('h') }}" data-minutes="{{ __('m') }}" data-seconds="{{ __('s') }}">
                @if($registrationTournaments->isNotEmpty())
                    <div class="guest-slider-heading"><span>{{ __('Registration is open') }}</span><span>{{ __(':count events', ['count' => $registrationTournaments->count()]) }}</span></div>
                    <div class="guest-tournament-track" x-ref="track" tabindex="0" role="region" aria-label="{{ __('Featured tournaments. Swipe or use the arrow buttons to explore.') }}"
                        @scroll.debounce.60ms="sync()" @keydown.right.prevent="go(index + 1)" @keydown.left.prevent="go(index - 1)"
                        @pointerdown="startDrag($event)" @pointermove="drag($event)" @pointerup="endDrag($event)" @pointercancel="endDrag($event)" @lostpointercapture="endDrag($event)" @click.capture="if (dragged) { $event.preventDefault(); $event.stopPropagation(); dragged = false; }">
                        @foreach($registrationTournaments as $featured)
                        <article class="guest-featured-event" data-tournament-slide role="group" aria-label="{{ __('Tournament :position of :total', ['position' => $loop->iteration, 'total' => $registrationTournaments->count()]) }}">
                            <div class="guest-featured-cover">
                                @if($featured->game->bannerUrl())<img src="{{ $featured->game->bannerUrl() }}" alt="{{ $featured->game->localizedName() }}" width="500" height="260" draggable="false">@endif
                                <span class="guest-event-state"><span class="guest-status-dot" aria-hidden="true"></span>{{ $featured->status->label() }}</span>
                                <img class="guest-featured-crest" src="/gamersrival-icon.png" alt="" width="64" height="64" draggable="false">
                            </div>
                            <div class="guest-featured-info">
                                <p>{{ $featured->game->localizedName() }}</p>
                                <h2>{{ $featured->name }}</h2>
                                <p class="guest-featured-time">{{ $featured->start_at->format('d M · H:i') }} {{ config('app.timezone') }}</p>
                                <div class="guest-countdown"><span>{{ __('Starts in') }}</span><time datetime="{{ $featured->start_at->toIso8601String() }}" x-text="remaining($el.dateTime)">{{ $featured->start_at->diffForHumans() }}</time></div>
                                <dl><div><dt>{{ __('Entry') }}</dt><dd>{{ (float) $featured->entry_fee > 0 ? \App\Shared\Support\Currency::symbol().number_format((float) $featured->entry_fee, 2) : __('Free') }}</dd></div><div><dt>{{ __('Players') }}</dt><dd>{{ $featured->registrations_count }} <span>/ {{ $featured->max_participants }}</span></dd></div></dl>
                                <a class="guest-button" href="{{ route('tournaments.view', $featured->uuid) }}">{{ __('View tournament') }} ↗</a>
                            </div>
                        </article>
                        @endforeach
                    </div>
                    @if($registrationTournaments->count() > 1)
                        <div class="guest-slider-controls">
                            <span class="guest-drag-hint">{{ __('Drag to discover') }} <span aria-hidden="true">↔</span></span>
                            <div class="guest-slider-dots" aria-label="{{ __('Choose a tournament') }}">@foreach($registrationTournaments as $featured)<button type="button" @click="go({{ $loop->index }})" :aria-current="index === {{ $loop->index }} ? 'true' : 'false'" aria-label="{{ __('Show tournament :position', ['position' => $loop->iteration]) }}"></button>@endforeach</div>
                            <div class="guest-slider-arrows"><button type="button" @click="go(index - 1)" :disabled="index === 0" aria-label="{{ __('Previous tournament') }}">←</button><button type="button" @click="go(index + 1)" :disabled="index === count - 1" aria-label="{{ __('Next tournament') }}">→</button></div>
                        </div>
                    @endif
                @else
                    <div class="guest-featured-event guest-featured-empty"><img src="/gamersrival-logo.webp" alt="GamersRival" width="250" height="192"><p>{{ __('Your next competition starts here.') }}</p><a class="guest-button" href="/tournaments">{{ __('Browse Tournaments') }} ↗</a></div>
                @endif
            </aside>
        </div>
    </section>
    @endif
    <livewire:landing.guest-pulse placement="activity" key="guest-recent-results" />

    <section id="competitions" class="guest-section guest-wrap guest-competitions">
        <div class="guest-section-heading"><h2>{{ __('On the board') }}</h2><a href="/tournaments">{{ __('Browse Tournaments') }} ↗</a></div>
        <div class="guest-matchboard">
            @forelse($publicTournaments as $tournament)
                <a class="guest-match-row guest-reveal" href="{{ route('tournaments.view', $tournament->uuid) }}">
                    @if($tournament->game->bannerUrl())<img src="{{ $tournament->game->bannerUrl() }}" width="160" height="100" loading="lazy" alt="">@endif
                    <span class="guest-match-name"><span class="guest-match-meta">{{ $tournament->game->localizedName() }}</span><strong>{{ $tournament->name }}</strong></span>
                    <span class="guest-match-meta">{{ $tournament->status->label() }}<span>{{ $tournament->start_at?->format('d M · H:i') }} {{ config('app.timezone') }}</span></span>
                    <span class="guest-match-meta">{{ __(':joined / :max entries', ['joined' => $tournament->registrations_count, 'max' => $tournament->max_participants]) }}</span>
                    <span class="guest-match-arrow" aria-hidden="true">↗</span>
                </a>
            @empty
                <div class="guest-board-empty"><p>{{ __('Your next competition starts here.') }}</p><span>{{ __('New tournaments will appear when registration opens. Explore the games while you wait.') }}</span><a href="/tournaments">{{ __('View tournament schedule') }} ↗</a></div>
            @endforelse
        </div>
    </section>

    @if($gamesSection)
    <section id="games" class="guest-section guest-wrap">
        <div class="guest-section-heading"><h2>{{ $gamesSection->title }}</h2><p>{{ $gamesSection->body }}</p></div>
        @if($games->isNotEmpty())
            <div class="guest-game-gallery">
                @foreach($games as $game)
                    <a href="{{ route('games.show', $game) }}" class="guest-game-tile guest-reveal">
                        @if($game->bannerUrl())<img src="{{ $game->bannerUrl() }}" alt="" width="600" height="700" loading="lazy">@else<img class="guest-game-brand-cover" src="/gamersrival-logo.webp" alt="" width="500" height="384" loading="lazy">@endif
                        <div><h3>{{ $game->localizedName() }}</h3><span>{{ __('Explore competitions') }} ↗</span></div>
                    </a>
                @endforeach
            </div>
        @else<p class="guest-empty">{{ __('No active games available yet.') }}</p>@endif
    </section>
    @endif

    @if($topPlayersSection)
    <section id="achievements" class="guest-honours guest-section guest-wrap">
        <div class="guest-section-heading"><h2>{{ $topPlayersSection->title }}</h2><p>{{ $topPlayersSection->body }}</p></div>
        <div class="guest-honours-sheet">
            @forelse($topPlayers as $player)
                <article class="guest-winner guest-reveal">
                    <span class="guest-winner-rank">{{ $player['rank'] }}</span>
                    <div class="guest-winner-name"><h3 translate="no">{{ $player['name'] }}</h3><p>{{ $player['rank'] === 1 ? __('Leading the board') : __('Tournament contender') }}</p></div>
                    <dl><div><dt>{{ __('Wins') }}</dt><dd>{{ $player['wins'] }}</dd></div><div><dt>{{ __('Win rate') }}</dt><dd>{{ $player['winrate'] }}</dd></div><div><dt>{{ __('Prizes') }}</dt><dd>{{ $player['cash'] }}</dd></div></dl>
                </article>
            @empty
                <div class="guest-honours-empty"><p>{{ __('The next name could be yours.') }}</p><span>{{ __('No weekly leaderboard results yet. Completed tournament matches will shape this board.') }}</span><a href="/tournaments">{{ __('Find a tournament') }} ↗</a></div>
            @endforelse
            <p class="guest-honours-caption"><span>{{ now()->subWeek()->format('d M') }} — {{ now()->format('d M Y') }}</span></p>
        </div>
    </section>
    @endif

    @if($howSection)
    <section class="guest-section guest-wrap guest-how">
        <div><h2>{{ $howSection->title }}</h2><p>{{ $howSection->body }}</p><a href="/register" class="guest-button">{{ __('Create Account') }} ↗</a></div>
        <ol>@foreach($howSection->activeItems as $item)<li class="guest-reveal"><h3>{{ $item->title }}</h3><p>{{ $item->body }}</p></li>@endforeach</ol>
    </section>
    @endif

    @if($statsSection)
    <section class="guest-section guest-wrap guest-stats">
        <div><h2>{{ $statsSection->title }}</h2><p>{{ $statsSection->body }}</p></div>
        <dl>@foreach($stats as $stat)<div class="guest-reveal"><dd>{{ $stat['value'] }}</dd><dt>{{ $stat['label'] }}</dt></div>@endforeach</dl>
    </section>
    @endif

    @if($featuresSection)
    <section class="guest-section guest-wrap guest-features">
        <div><h2>{{ $featuresSection->title }}</h2><p>{{ $featuresSection->body }}</p></div>
        <div class="guest-feature-menu">@foreach($featuresSection->activeItems as $item)
            <a class="guest-reveal" href="{{ $item->url ?: '/tournaments' }}"><span><h3>{{ $item->title }}</h3><p>{{ $item->body }}</p></span><span aria-hidden="true">↗</span></a>
        @endforeach</div>
    </section>
    @endif

    @if($reviewsSection)
    <section class="guest-section guest-wrap guest-reviews">
        <h2>{{ $reviewsSection->title }}</h2>
        <div class="guest-review-slips">@forelse($playerReviews as $review)
            <figure class="guest-reveal"><blockquote>{{ $review->review }}</blockquote><figcaption><span translate="no">{{ $review->user?->profile?->display_name ?? $review->user?->username }}</span><span>{{ __(':rating out of 5', ['rating' => $review->rating]) }}</span></figcaption></figure>
        @empty<div class="guest-empty"><p>{{ __('No reviews yet.') }}</p><p>{{ __('Player feedback will appear here once available.') }}</p></div>@endforelse</div>
    </section>
    @endif
</main>
@endauth
