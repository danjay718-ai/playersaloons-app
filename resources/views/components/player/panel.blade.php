@props([
    'title' => null,
    'padding' => 'p-6',
])

<section {{ $attributes->class(['player-panel', $padding]) }}>
    @if($title)
        <h3 class="player-panel-title">{{ $title }}</h3>
    @endif

    {{ $slot }}
</section>
