@props([
    'title',
    'meta' => null,
    'href' => null,
    'action' => 'View',
])

<div {{ $attributes->class(['player-list-row']) }}>
    <span class="min-w-0 truncate font-bold text-white">{{ $title }}</span>

    @if($href)
        <a href="{{ $href }}" wire:navigate class="player-list-action">{{ $action }}</a>
    @elseif($meta)
        <span class="shrink-0 text-xs text-zinc-500">{{ $meta }}</span>
    @endif
</div>
