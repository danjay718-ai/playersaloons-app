@props(['at', 'mode' => 'text'])
{{-- Live countdown to an ISO timestamp. mode=text for compact, mode=boxes for large tiles. --}}
<span x-data="grCountdown(@js($at->toIso8601String()), @js(['started' => __('Starting now'), 'd' => __('d'), 'h' => __('h'), 'm' => __('m'), 's' => __('s')]))" {{ $attributes->class(['gr-countdown', 'gr-countdown-'.$mode]) }}>
    @if($mode === 'boxes')
        <template x-for="part in parts" :key="part.label"><span class="gr-count-box"><b x-text="part.value"></b><small x-text="part.label"></small></span></template>
        <noscript>{{ $at->diffForHumans() }}</noscript>
    @else
        <time datetime="{{ $at->toIso8601String() }}" x-text="text">{{ $at->diffForHumans() }}</time>
    @endif
</span>
