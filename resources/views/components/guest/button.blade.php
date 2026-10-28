@props(['href' => '#', 'variant' => 'primary', 'arrow' => '↗'])
<a href="{{ $href }}" {{ $attributes->class(['gr-btn', 'gr-btn-'.$variant]) }}>
    <span>{{ $slot }}</span>@if($arrow)<span class="gr-btn-arrow" aria-hidden="true">{{ $arrow }}</span>@endif
</a>
