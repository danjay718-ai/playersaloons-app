@props([
    'name',
    'label' => 'Image',
    'width' => 960,
    'height' => 540,
    'currentUrl' => null,
    'help' => null,
    'nameBinding' => null,
])

@php
    $inputId = 'controller-crop-upload-'.str_replace(['.', '[', ']'], '-', $name).'-'.uniqid();
@endphp

{{-- Normal form counterpart of image-crop-upload: no Livewire upload round trip. --}}
<div x-data="imageCropUpload(@js(['mode' => 'form', 'name' => $name, 'width' => (int) $width, 'height' => (int) $height, 'previewUrl' => $currentUrl]))" @image-crop-preview.window="if ($event.detail.name === name && !fileName) previewUrl = $event.detail.url || ''" class="space-y-2">
    <label for="{{ $inputId }}" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $label }}</label>
    <input id="{{ $inputId }}" x-ref="input" type="file" accept="image/jpeg,image/png,image/webp" x-on:change="selectFile($event)" class="block w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-xs text-slate-300 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-600 file:px-3 file:py-1.5 file:text-[10px] file:font-black file:uppercase file:text-white">
    <input x-ref="formInput" type="file" @if($nameBinding) x-bind:name="{{ $nameBinding }}" @else name="{{ $name }}" @endif accept="image/jpeg,image/png,image/webp" class="hidden">
    <p class="text-[10px] leading-4 text-slate-500">{{ $help ?: "Upload a JPG, PNG, or WebP. Images are cropped to {$width} × {$height}px before form submission." }}</p>
    <p x-show="clientError" x-cloak class="text-xs text-red-400" x-text="clientError"></p>
    <p x-show="fileName && !clientError" x-cloak class="truncate text-[10px] font-semibold text-emerald-400"><span x-text="`New upload ready: ${fileName}`"></span></p>
    @error($name)<p class="text-xs text-red-400">{{ $message }}</p>@enderror

    <template x-if="previewUrl"><div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-950"><img :src="previewUrl" alt="{{ $label }} preview" class="aspect-[16/9] w-full object-cover"><p class="border-t border-slate-800 px-3 py-2 text-[10px] text-slate-400" x-text="fileName ? 'New cropped upload preview' : 'Current/default banner preview'"></p></div></template>

    <template x-teleport="body">
        <div x-show="cropOpen" x-cloak x-on:keydown.escape.window="cancelCrop()" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/85 p-4 backdrop-blur-sm">
            <section x-on:click.outside="cancelCrop()" class="w-full max-w-2xl overflow-hidden rounded-2xl border border-indigo-500/30 bg-[#0b1020] shadow-2xl">
                <header class="flex items-start justify-between gap-4 border-b border-slate-800 px-5 py-4"><div><p class="text-[9px] font-black uppercase tracking-[0.25em] text-indigo-400">Image editor</p><h2 class="mt-1 text-base font-black text-white">Crop {{ $label }}</h2><p class="mt-1 text-xs text-slate-500">Original: <span x-text="`${sourceWidth} × ${sourceHeight}px`"></span> · Output: {{ $width }} × {{ $height }}px</p></div><button type="button" x-on:click="cancelCrop()" class="rounded-lg border border-slate-700 px-3 py-2 text-xs text-slate-400 hover:text-white">✕</button></header>
                <div class="space-y-5 p-5"><div class="mx-auto overflow-hidden rounded-xl border border-slate-700 bg-black shadow-inner" style="max-width:560px;aspect-ratio:{{ $width }} / {{ $height }}"><canvas x-ref="canvas" class="h-full w-full"></canvas></div><div class="grid gap-4 sm:grid-cols-3"><label class="space-y-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Zoom<input x-model.number="zoom" x-on:input="drawCrop()" type="range" min="1" max="3" step="0.01" class="w-full accent-indigo-500"></label><label class="space-y-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Horizontal<input x-model.number="positionX" x-on:input="drawCrop()" type="range" min="-100" max="100" step="1" class="w-full accent-indigo-500"></label><label class="space-y-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Vertical<input x-model.number="positionY" x-on:input="drawCrop()" type="range" min="-100" max="100" step="1" class="w-full accent-indigo-500"></label></div></div>
                <footer class="flex justify-end gap-3 border-t border-slate-800 px-5 py-4"><button type="button" x-on:click="cancelCrop()" class="rounded-lg border border-slate-700 px-4 py-2.5 text-xs font-bold text-slate-300 hover:bg-slate-800">Cancel</button><button type="button" x-on:click="applyCrop()" x-bind:disabled="processing" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-black uppercase tracking-wider text-white hover:bg-indigo-500 disabled:opacity-50"><span x-show="!processing">Apply Crop</span><span x-show="processing">Processing…</span></button></footer>
            </section>
        </div>
    </template>
</div>
