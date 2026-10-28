@props(['eyebrow' => null, 'title', 'body' => null, 'href' => null, 'linkLabel' => null])
<div {{ $attributes->class(['gr-head']) }}>
    <div>
        @if($eyebrow)<p class="gr-eyebrow">{{ $eyebrow }}</p>@endif
        <h2>{{ $title }}</h2>
        @if($body)<p class="gr-head-body">{{ $body }}</p>@endif
    </div>
    @if($href && $linkLabel)<a class="gr-head-link" href="{{ $href }}">{{ $linkLabel }} <span aria-hidden="true">↗</span></a>@endif
</div>
