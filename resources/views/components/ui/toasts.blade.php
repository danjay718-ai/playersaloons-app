@php
    $toastMap = [
        'success' => ['type' => 'success', 'icon' => 'circle-check'],
        'message' => ['type' => 'success', 'icon' => 'circle-check'],
        'info' => ['type' => 'info', 'icon' => 'info'],
        'h2h_status' => ['type' => 'info', 'icon' => 'swords'],
        'error' => ['type' => 'error', 'icon' => 'alert-triangle'],
        'h2h_error' => ['type' => 'error', 'icon' => 'alert-triangle'],
    ];

    $toasts = collect($toastMap)
        ->filter(fn (array $config, string $key): bool => session()->has($key))
        ->map(fn (array $config, string $key): array => [
            'id' => $key,
            'type' => $config['type'],
            'icon' => $config['icon'],
            'message' => session($key),
        ])
        ->values();
@endphp

@if($toasts->isNotEmpty())
    <div class="fixed right-3 top-20 z-[120] flex w-[calc(100vw-1.5rem)] max-w-sm flex-col gap-3 sm:right-5 sm:top-24"
         aria-live="polite"
         aria-atomic="true">
        @foreach($toasts as $toast)
            <div x-data="{ show: true }"
                 x-show="show"
                 x-init="setTimeout(() => show = false, 5200)"
                 x-transition:enter="transform ease-out duration-300"
                 x-transition:enter-start="translate-x-6 opacity-0"
                 x-transition:enter-end="translate-x-0 opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="player-toast player-toast-{{ $toast['type'] }}">
                <div class="flex items-start gap-3">
                    <div class="player-toast-icon">
                        <i data-lucide="{{ $toast['icon'] }}" class="h-4 w-4"></i>
                    </div>
                    <p class="min-w-0 flex-1 text-xs font-bold leading-relaxed text-zinc-100">{{ $toast['message'] }}</p>
                    <button type="button"
                            @click="show = false"
                            class="grid h-6 w-6 shrink-0 place-items-center rounded-md text-zinc-500 transition-colors hover:bg-white/5 hover:text-white"
                            aria-label="Dismiss notification">
                        <i data-lucide="x" class="h-3.5 w-3.5"></i>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
@endif
