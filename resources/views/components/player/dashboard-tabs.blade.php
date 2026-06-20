@props(['items'])

<nav {{ $attributes->class(['player-tabs']) }} aria-label="Player pages">
    @foreach($items as $item)
        <a href="{{ $item['url'] }}"
           wire:navigate
           class="player-tab {{ request()->is($item['pattern']) ? 'player-tab-active' : '' }}"
           @if(request()->is($item['pattern'])) aria-current="page" @endif>
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>
