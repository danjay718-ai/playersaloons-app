@teleport('body')
<div x-show="languageModalOpen" x-trap.inert.noscroll="languageModalOpen" x-cloak @keydown.escape.window="languageModalOpen = false" class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-slate-950/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="add-translation-language-title">
    <form wire:submit="addLanguage" @click.outside="languageModalOpen = false" class="my-auto w-full max-h-[90dvh] max-w-lg overflow-y-auto rounded-2xl border border-slate-700 bg-[#0f172a] shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-800 p-6">
            <div><h3 id="add-translation-language-title" class="text-lg font-bold text-white">{{ __('Add a new language') }}</h3><p class="mt-1 text-sm leading-6 text-slate-400">{{ __('Make another language available to your players.') }}</p></div>
            <button type="button" @click="languageModalOpen = false" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 hover:text-white" aria-label="{{ __('Close') }}"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <div class="space-y-4 p-6">
            @php($presets = ['fil' => ['Filipino', 'Filipino', 'ph'], 'hi' => ['Hindi', 'हिन्दी', 'in'], 'ar' => ['Arabic', 'العربية', 'sa'], 'ko' => ['Korean', '한국어', 'kr']])
            @if(array_diff_key($presets, $languages))
                <div><p class="mb-2 text-xs font-semibold text-slate-400">{{ __('Quick start') }}</p><div class="flex flex-wrap gap-2">
                    @foreach(array_diff_key($presets, $languages) as $presetCode => $preset)
                        <button type="button" @click="$wire.set('code', @js($presetCode), false); $wire.set('english', @js($preset[0]), false); $wire.set('native', @js($preset[1]), false); $wire.set('flag', @js($preset[2]), false)" class="rounded-full border border-slate-700 bg-slate-800/40 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:border-cyan-400/50 hover:text-cyan-200">{{ $preset[0] }}</button>
                    @endforeach
                </div></div>
            @endif
            <label class="block"><span class="text-sm font-semibold text-slate-200">{{ __('Language name') }}</span><input wire:model="english" placeholder="{{ __('For example, Filipino') }}" maxlength="80" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-3 text-sm text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20"></label>
            @error('english')<p role="alert" class="text-xs text-red-300">{{ $message }}</p>@enderror
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="block"><span class="text-sm font-semibold text-slate-200">{{ __('Language code') }}</span><input wire:model="code" placeholder="fil" maxlength="10" spellcheck="false" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-3 font-mono text-sm text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20"></label><p class="mt-1.5 text-xs leading-5 text-slate-500">{{ __('A unique short code, such as fil or hi.') }}</p>@error('code')<p role="alert" class="mt-2 text-xs text-red-300">{{ $message }}</p>@enderror</div>
                <div><label class="block"><span class="text-sm font-semibold text-slate-200">{{ __('Native name') }} <span class="text-xs font-normal text-slate-500">{{ __('Optional') }}</span></span><input wire:model="native" placeholder="{{ __('Name in its own language') }}" maxlength="80" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-3 text-sm text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20"></label><p class="mt-1.5 text-xs leading-5 text-slate-500">{{ __('Leave blank to use the language name.') }}</p>@error('native')<p role="alert" class="mt-2 text-xs text-red-300">{{ $message }}</p>@enderror</div>
            </div>
            <div>
                <label for="new-language-flag" class="block text-sm font-semibold text-slate-200">{{ __('Flag icon') }}</label>
                <div class="mt-2 flex items-center gap-3">
                    <span class="flex h-12 w-14 shrink-0 items-center justify-center rounded-xl border border-slate-700 bg-slate-950/60"><span :class="'fi fi-' + $wire.flag" class="rounded-sm text-2xl" aria-hidden="true"></span></span>
                    <select id="new-language-flag" wire:model="flag" class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-3 text-sm text-white outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20">
                        <option value="un">{{ __('Neutral flag') }}</option>
                        @foreach(config('countries', []) as $countryCode => $countryName)<option value="{{ strtolower($countryCode) }}">{{ $countryName }}</option>@endforeach
                    </select>
                </div>
                <p class="mt-1.5 text-xs leading-5 text-slate-500">{{ __('Choose the flag shown beside this language in the language selector.') }}</p>
                @error('flag')<p role="alert" class="mt-2 text-xs text-red-300">{{ $message }}</p>@enderror
            </div>
            <p class="rounded-xl bg-cyan-500/5 p-3 text-xs leading-5 text-cyan-200/70">{{ __('English is used until you add translations. You can start with just one phrase.') }}</p>
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-800 bg-slate-950/30 px-6 py-4"><button type="button" @click="languageModalOpen = false" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-400 hover:text-white">{{ __('Cancel') }}</button><button type="submit" wire:loading.attr="disabled" wire:target="addLanguage" class="inline-flex items-center gap-2 rounded-xl bg-cyan-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-cyan-500 disabled:opacity-50"><i data-lucide="plus" class="h-4 w-4"></i>{{ __('Add language') }}</button></div>
    </form>
</div>
@endteleport
