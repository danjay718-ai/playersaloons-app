<div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
    <form wire:submit="importTranslations" x-data="translationImportUpload()" x-on:livewire-upload-start="uploading = true; progress = 0" x-on:livewire-upload-progress="progress = $event.detail.progress" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-cancel="uploading = false" class="overflow-hidden rounded-2xl border border-slate-800 bg-gradient-to-br from-[#101a30] to-[#0b1222] shadow-lg">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-800/70 p-5 sm:p-6">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-300"><i data-lucide="file-up" class="h-5 w-5"></i></span>
                <div>
                    <h3 class="text-base font-bold text-white">{{ __('Import translations') }}</h3>
                    <p class="mt-1 max-w-md text-sm leading-6 text-slate-400">{{ __('Download the English template, translate it, then upload it for your chosen language.') }}</p>
                </div>
            </div>
            <button type="button" wire:click="exportEnglish" wire:loading.attr="disabled" wire:target="exportEnglish" class="inline-flex items-center gap-2 rounded-xl border border-indigo-400/30 bg-indigo-500/10 px-3 py-2.5 text-xs font-bold text-indigo-200 transition hover:bg-indigo-500/20 disabled:opacity-50"><i data-lucide="download" class="h-4 w-4"></i>{{ __('Download English template') }}</button>
        </div>
        <div class="space-y-5 p-5 sm:p-6">
            <div class="flex gap-3">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-500/15 text-xs font-bold text-indigo-300">1</span>
                <div class="min-w-0 flex-1">
                    <label for="translation-import-language" class="block text-sm font-semibold text-slate-100">{{ __('Which language are you uploading?') }}</label>
                    <select id="translation-import-language" wire:model="importLocale" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-3 text-sm text-white outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20">
                        <option value="">{{ __('Choose a language…') }}</option>
                        @foreach($languages as $locale => $language)
                            @if($locale !== 'en')<option value="{{ $locale }}">{{ $language['english'] }} · {{ strtoupper($locale) }}</option>@endif
                        @endforeach
                    </select>
                    @error('importLocale')<p role="alert" class="mt-2 text-xs text-red-300">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex gap-3">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-500/15 text-xs font-bold text-indigo-300">2</span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-100">{{ __('Upload your translated file') }}</p>
                    <label for="translation-import-file" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="dropFile($event, $refs.translationUpload)" :class="dragging ? 'border-indigo-400 bg-indigo-500/10' : 'border-slate-700 bg-slate-950/40'" class="mt-2 flex cursor-pointer flex-col items-center rounded-xl border-2 border-dashed px-4 py-6 text-center transition hover:border-indigo-400/60 focus-within:ring-2 focus-within:ring-indigo-400">
                        <input id="translation-import-file" x-ref="translationUpload" type="file" wire:model="importFile" accept=".json,application/json" class="sr-only">
                        @if($importFile)
                            <i data-lucide="file-check-2" class="mb-2 h-7 w-7 text-emerald-300"></i>
                            <span class="max-w-full break-all text-sm font-semibold text-emerald-200">{{ $importFile->getClientOriginalName() }}</span>
                            <span class="mt-1 text-xs text-slate-400">{{ __('Click or drop another file to replace it') }}</span>
                        @else
                            <i data-lucide="cloud-upload" class="mb-2 h-7 w-7 text-indigo-300"></i>
                            <span class="text-sm font-semibold text-slate-200">{{ __('Drop your file here, or click to browse') }}</span>
                            <span class="mt-1 text-xs text-slate-400">{{ __('JSON files only · Up to 10 MB') }}</span>
                        @endif
                    </label>
                    <div x-show="uploading" x-cloak class="mt-3" role="status">
                        <div class="mb-1 flex justify-between text-xs text-indigo-200"><span>{{ __('Uploading…') }}</span><span x-text="`${progress}%`"></span></div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-800"><div class="h-full rounded-full bg-indigo-400 transition-all" :style="`width: ${progress}%`"></div></div>
                    </div>
                    @error('importFile')<p role="alert" class="mt-2 text-sm leading-6 text-red-300">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex items-start gap-2 rounded-xl border border-slate-700/50 bg-slate-800/30 p-3 text-xs leading-5 text-slate-400"><i data-lucide="shield-check" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400"></i><p>{{ __('Edit only the translation values. We check that every English phrase matches before saving. If anything is wrong, your existing translations stay unchanged.') }}</p></div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800/70 bg-slate-950/30 px-5 py-4 sm:px-6">
            <span wire:loading wire:target="importTranslations" role="status" class="text-xs text-indigo-200">{{ __('Checking and importing your translations…') }}</span>
            <button type="submit" :disabled="uploading" wire:loading.attr="disabled" wire:target="importFile,importTranslations" class="ml-auto inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-indigo-950/40 transition hover:bg-indigo-500 disabled:cursor-wait disabled:opacity-50"><i data-lucide="check-check" class="h-4 w-4"></i>{{ __('Check & import translations') }}</button>
        </div>
    </form>
    <aside class="flex flex-col rounded-2xl border border-slate-800 bg-[#0f172a] p-5 sm:p-6">
        <span class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-300"><i data-lucide="languages" class="h-5 w-5"></i></span>
        <h3 class="text-base font-bold text-white">{{ __('Need another language?') }}</h3>
        <p class="mt-2 text-sm leading-6 text-slate-400">{{ __('Add it once, then translate phrases manually or upload a completed English template.') }}</p>
        <p class="mt-4 text-xs font-semibold text-slate-500">{{ __(':count languages available', ['count' => count($languages)]) }}</p>
        <button type="button" @click="languageModalOpen = true" @disabled(! $this->languageTableReady) class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-cyan-400/25 bg-cyan-500/10 px-4 py-3 text-sm font-bold text-cyan-200 transition hover:bg-cyan-500/20 disabled:cursor-not-allowed disabled:opacity-40"><i data-lucide="plus" class="h-4 w-4"></i>{{ __('Add language') }}</button>

        @if(! $this->languageTableReady)<p role="status" class="mt-3 text-xs leading-5 text-amber-300">{{ __('Adding languages is unavailable until the application update is complete. No language was added.') }}</p>@endif
    </aside>
</div>
