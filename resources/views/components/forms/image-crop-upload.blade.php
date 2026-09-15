@props([
    'model',
    'label' => 'Image',
    'width',
    'height',
    'maxMb' => 2,
    'sourceMaxMb' => 12,
    'help' => null,
    'disabled' => false,
    'compact' => false,
])

@php
    $gcd = static function (int $a, int $b): int {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    };
    $ratioDivisor = $gcd((int) $width, (int) $height);
    $ratioLabel = ((int) $width / $ratioDivisor).':'.((int) $height / $ratioDivisor);
    $inputId = 'crop-upload-'.str_replace(['.', '[', ']'], '-', $model).'-'.uniqid();
@endphp

<div
    x-data="imageCropUpload(@js([
        'model' => $model,
        'width' => (int) $width,
        'height' => (int) $height,
    ]))"
    class="space-y-2"
>
    @if($label)
        <label for="{{ $inputId }}" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $label }}</label>
    @endif
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
        <input
            id="{{ $inputId }}"
            x-ref="input"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            @disabled($disabled)
            x-on:change="selectFile($event)"
            class="flex-1 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-xs text-slate-300 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-600 file:px-3 file:py-1.5 file:text-[10px] file:font-black file:uppercase file:text-white disabled:opacity-50"
        >
        {{ $slot }}
    </div>
    <input
        x-ref="uploadInput"
        type="file"
        wire:model="{{ $model }}"
        accept="image/jpeg,image/png,image/webp"
        class="hidden"
        x-on:livewire-upload-start="uploading = true; progress = 0"
        x-on:livewire-upload-progress="progress = $event.detail.progress"
        x-on:livewire-upload-finish="finishUpload()"
        x-on:livewire-upload-error="failUpload()"
    >

    <div class="flex items-start justify-between gap-3 text-[10px] leading-4 text-slate-500">
        <p>{{ $help ?: "Your image will be cropped and scaled to {$width} × {$height}px ({$ratioLabel})." }}</p>
        <span x-show="uploading" x-cloak class="shrink-0 font-bold text-indigo-300" x-text="`${progress}%`"></span>
    </div>

    <div x-show="uploading" x-cloak class="h-1.5 overflow-hidden rounded-full bg-slate-800">
        <div class="h-full rounded-full bg-indigo-500 transition-all" :style="`width: ${progress}%`"></div>
    </div>

    <p x-show="clientError" x-cloak class="text-xs text-red-400" x-text="clientError"></p>
    <p x-show="fileName && !clientError" x-cloak class="truncate text-[10px] font-semibold text-emerald-400" x-text="`Ready: ${fileName}`"></p>
    @error($model) <p class="text-xs text-red-400">{{ $message }}</p> @enderror

    <template x-teleport="body">
        <div
            x-show="cropOpen"
            x-cloak
            x-on:keydown.escape.window="cancelCrop()"
            class="fixed inset-0 z-[200] flex items-center justify-center bg-black/85 p-4 backdrop-blur-sm"
        >
            <section x-on:click.outside="cancelCrop()" class="w-full overflow-hidden rounded-2xl border border-indigo-500/30 bg-[#0b1020] shadow-2xl {{ $compact ? 'flex max-h-[90dvh] max-w-lg flex-col' : 'max-w-2xl' }}">
                <header class="flex items-start justify-between gap-4 border-b border-slate-800 {{ $compact ? 'shrink-0 px-4 py-3' : 'px-5 py-4' }}">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[0.25em] text-indigo-400">Image editor</p>
                        <h2 class="mt-1 text-base font-black text-white">Crop {{ $label }}</h2>
                        <p class="mt-1 text-xs text-slate-500">Original: <span x-text="`${sourceWidth} × ${sourceHeight}px`"></span> · Output: {{ $width }} × {{ $height }}px</p>
                    </div>
                    <button type="button" x-on:click="cancelCrop()" class="rounded-lg border border-slate-700 px-3 py-2 text-xs text-slate-400 hover:text-white">✕</button>
                </header>

                <div class="{{ $compact ? 'min-h-0 overflow-y-auto space-y-4 p-4' : 'space-y-5 p-5' }}">
                    <div class="mx-auto overflow-hidden rounded-xl border border-slate-700 bg-black shadow-inner" style="{{ $compact ? 'width: min(100%, 320px, calc(35dvh * '.((int) $width / (int) $height).'));' : 'max-width: 560px;' }} aspect-ratio: {{ $width }} / {{ $height }};">
                        <canvas x-ref="canvas" class="h-full w-full"></canvas>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <label class="space-y-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Zoom
                            <input x-model.number="zoom" x-on:input="drawCrop()" type="range" min="1" max="3" step="0.01" class="w-full accent-indigo-500">
                        </label>
                        <label class="space-y-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Horizontal
                            <input x-model.number="positionX" x-on:input="drawCrop()" type="range" min="-100" max="100" step="1" class="w-full accent-indigo-500">
                        </label>
                        <label class="space-y-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Vertical
                            <input x-model.number="positionY" x-on:input="drawCrop()" type="range" min="-100" max="100" step="1" class="w-full accent-indigo-500">
                        </label>
                    </div>
                </div>

                <footer class="flex justify-end gap-3 border-t border-slate-800 {{ $compact ? 'shrink-0 px-4 py-3' : 'px-5 py-4' }}">
                    <button type="button" x-on:click="cancelCrop()" class="rounded-lg border border-slate-700 px-4 py-2.5 text-xs font-bold text-slate-300 hover:bg-slate-800">Cancel</button>
                    <button type="button" x-on:click="applyCrop()" x-bind:disabled="processing" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-black uppercase tracking-wider text-white hover:bg-indigo-500 disabled:opacity-50">
                        <span x-show="!processing">Apply Crop</span><span x-show="processing">Processing…</span>
                    </button>
                </footer>
            </section>
        </div>
    </template>
</div>
