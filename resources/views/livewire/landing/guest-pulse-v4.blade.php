<div wire:poll.30s.visible x-data="{ paused: false }" :class="{ 'is-paused': paused }" class="gr-wire" data-ui>
    @if($winSummary && $winSummary['matches_won'] > 0)
        <div class="gr-wire-summary" aria-label="{{ __('Platform win totals') }}">
            <span class="gr-wire-badge">
                <i class="gr-wire-dot" aria-hidden="true"></i>
                <strong>{{ $winSummary['matches_won'] }}</strong>
                <span>{{ __('won matches') }}</span>
            </span>
            <span class="gr-wire-total">
                <span>{{ __('Won:') }}</span>
                <strong>{{ $winSummary['total_won'] }}</strong>
            </span>
        </div>
    @endif
    <div class="gr-wire-window" tabindex="0" role="region" aria-label="{{ __('Recent public results') }}">
        @if($activity->isNotEmpty())
            <div class="gr-wire-track">
                @foreach([false, true] as $duplicate)
                    <div class="gr-wire-group" @if($duplicate) aria-hidden="true" inert @endif>
                        @foreach($activity as $event)
                            <a href="{{ $event['url'] }}" title="{{ $event['text'] }} · {{ $event['at']->diffForHumans() }}">
                                <span class="gr-wire-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($event['name'], 0, 1)) }}</span>
                                <strong translate="no">{{ $event['name'] }}</strong>
                                <span>{{ $event['action'] }}</span>
                                <small>{{ $event['context'] }}</small>
                                @if($event['reward'])
                                    <b class="gr-wire-reward">{{ $event['reward'] }}</b>
                                @else
                                    <b class="gr-wire-reward is-match">{{ __('Match Win') }}</b>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @else
            <span class="gr-wire-empty">{{ __('The next win could be yours. Confirmed player wins appear here.') }}</span>
        @endif
    </div>
    @if($activity->isNotEmpty())
        <button type="button" x-cloak @click="paused = !paused" :aria-pressed="paused.toString()" :aria-label="paused ? @js(__('Resume activity')) : @js(__('Pause activity'))" x-text="paused ? '▶' : '❚❚'">❚❚</button>
    @endif
</div>
