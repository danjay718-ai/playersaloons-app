@props([
    'label',
    'value',
    'tone' => 'text-purple-400',
    'icon' => null,
])

<div {{ $attributes->class(['player-metric-card group']) }}>
    <div class="flex items-center gap-3 {{ $tone }}">
        @if($icon)
            <i data-lucide="{{ $icon }}" class="h-5 w-5"></i>
        @endif
        <span class="player-metric-label">{{ $label }}</span>
    </div>
    <div class="mt-2 font-orbitron text-3xl font-black text-white">{{ $value }}</div>
</div>
