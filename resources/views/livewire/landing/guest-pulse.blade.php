@if($placement === 'header')
<div class="guest-live-counts" wire:poll.30s.visible data-open-total="{{ $summary['total'] }}"
    x-data="{ index: 0, timer: null, hovered: false, focused: false, init() { if (!matchMedia('(prefers-reduced-motion: reduce)').matches) this.timer = setInterval(() => { if (!document.hidden &amp;&amp; !this.hovered &amp;&amp; !this.focused) this.index++; }, 5000); }, destroy() { clearInterval(this.timer); } }" @mouseenter="hovered = true" @mouseleave="hovered = false" @focusin="focused = true" @focusout="focused = false">
    <a class="guest-open-total" href="/tournaments"><span class="guest-status-dot" aria-hidden="true"></span><strong>{{ $summary['total'] }}</strong> {{ __('open tournaments') }} <span aria-hidden="true">↗</span></a>
    @if(count($summary['games']))
        <div class="guest-game-counts">
            @foreach($summary['games'] as $game)
                <a href="{{ $game['url'] }}" x-show="index % {{ count($summary['games']) }} === {{ $loop->index }}" @if(!$loop->first) x-cloak @endif>
                    @if($game['image'])<img src="{{ $game['image'] }}" width="24" height="24" alt="">@endif
                    {{ __(':count open for :game', ['count' => $game['count'], 'game' => $game['name']]) }}
                </a>
            @endforeach
        </div>
    @else
        <span class="guest-count-empty">{{ __('New competitions are on the way.') }}</span>
    @endif
</div>
@else
<div wire:poll.30s.visible x-data="{ paused: false, ready: false, init() { this.ready = true; } }" :class="{ 'is-paused': paused, 'has-news-motion': ready }" class="guest-pulse guest-results" data-ui>
    <div class="guest-activity">
        <div class="guest-activity-window" tabindex="0" role="region" aria-label="{{ __('Recent public results') }}">
            @if($activity->isNotEmpty())
                <div class="guest-news-track">
                    @foreach([false, true] as $duplicate)
                        <div class="guest-news-group" @if($duplicate) aria-hidden="true" inert @endif>
                            @foreach($activity as $event)
                                <a href="{{ $event['url'] }}" title="{{ $event['text'] }} · {{ $event['at']->diffForHumans() }}">
                                    <span class="guest-event-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($event['name'], 0, 1)) }}</span>
                                    <strong translate="no">{{ $event['name'] }}</strong>
                                    <span>{{ $event['action'] }}</span>
                                    <small>{{ $event['context'] }}</small>
                                    @if($event['reward'])<b class="guest-event-reward">{{ $event['reward'] }}</b>@endif
                                    <span class="guest-event-arrow" aria-hidden="true">↗</span>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @else
                <span class="guest-feed-empty">{{ __('The next win could be yours. Confirmed player wins appear here.') }}</span>
            @endif
        </div>
        @if($activity->isNotEmpty())
            <button type="button" x-cloak x-show="ready" @click="paused = !paused" :aria-pressed="paused.toString()" :aria-label="paused ? @js(__('Resume activity')) : @js(__('Pause activity'))">
                <svg x-show="!paused" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M8 5v14M16 5v14" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <svg x-show="paused" x-cloak viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="m8 5 11 7-11 7Z" fill="currentColor"/></svg>
            </button>
        @endif
    </div>
</div>
@endif
