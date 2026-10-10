<div class="space-y-6" x-data="{ ...translationLanguageVisibility(@js(auth()->id()), @js(array_keys($languages))), languageModalOpen: false, resetModalOpen: false }" @translation-language-added.window="if (!supported.includes($event.detail.locale)) supported.push($event.detail.locale); languageModalOpen = false" @translation-reset-completed.window="resetModalOpen = false">

    @unless($this->translationTableReady)
        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-5 text-amber-200">
            <h2 class="text-sm font-extrabold uppercase tracking-widest">Translation table is not installed</h2>
            <p class="mt-2 text-sm text-amber-100/80">
                Run <code class="rounded bg-black/30 px-1.5 py-0.5">php artisan migrate</code> to create the <code class="rounded bg-black/30 px-1.5 py-0.5">translation_strings</code> table, then return to this page.
            </p>
        </div>
    @else
    <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-sm font-extrabold uppercase tracking-widest text-slate-200">Translation Manager</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('English is the source catalog. Export it, translate the translation values, then import the file into another language.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('translations.manage')
                <button type="button" wire:click="syncFromJson" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-300 hover:border-indigo-500/50 hover:text-white">
                    <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                    {{ __('Sync English') }}
                </button>
                <button type="button" wire:click="syncFromDatabase" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-300 hover:border-indigo-500/50 hover:text-white">
                    <i data-lucide="database" class="h-4 w-4"></i>
                    Sync Database
                </button>
                <button type="button" wire:click="exportEnglish" class="inline-flex items-center gap-2 rounded-lg border border-emerald-700/50 bg-emerald-950/30 px-3 py-2 text-xs font-semibold text-emerald-300 hover:border-emerald-500/70 hover:text-white">
                    <i data-lucide="download" class="h-4 w-4"></i>
                    {{ __('Export English') }}
                </button>

                @endcan
                @can('translations.reset')
                    <button data-testid="reset-translations-button" type="button" @disabled(! $this->resetTableReady) @click="$wire.set('resetLocale', $wire.localeFilter === 'en' ? 'all' : $wire.localeFilter, false); $wire.set('resetConfirmation', '', false); resetModalOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-rose-500/25 bg-rose-500/5 px-3 py-2 text-xs font-semibold text-rose-300 transition hover:bg-rose-500/10 disabled:cursor-not-allowed disabled:opacity-40"><i data-lucide="rotate-ccw" class="h-4 w-4"></i>{{ __('Reset translations') }}</button>
                @endcan
            </div>
        </div>
        @if(! $this->resetTableReady)
            @can('translations.reset')
                <p role="status" class="mt-3 text-xs text-amber-300">{{ __('Reset is unavailable until the application update is complete. No translations were changed.') }}</p>
            @endcan
        @endif

        @if(session()->has('success'))
            <div class="mt-4 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                {{ session('success') }}
            </div>
        @endif
    </div>

    @can('translations.manage')
        @include('livewire.admin.partials.translation-import-panel')
        @include('livewire.admin.partials.translation-language-modal')
    @endcan
    @can('translations.reset')
        @include('livewire.admin.partials.translation-reset-modal')
    @endcan

    <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
        <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-4">
            <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Search</label>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search English or translated text..." class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                </div>
                <label class="flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-xs font-semibold text-slate-300">
                    <input type="checkbox" wire:model.live="missingOnly" class="rounded border-slate-700 bg-slate-900 text-indigo-600 focus:ring-indigo-500">
                    Missing only
                </label>
            </div>
        </div>

        <form wire:submit="createKey" class="rounded-xl border border-slate-800 bg-[#0f172a] p-4">
            <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Add new phrase key</label>
            <div class="flex gap-2">
                <input type="text" wire:model="newKey" placeholder="New button or message text..." class="min-w-0 flex-1 rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-indigo-500">
                    Add
                </button>
            </div>
            @error('newKey') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </form>
    </div>

    <details class="rounded-xl border border-slate-800 bg-[#0f172a] p-4">
        <summary class="cursor-pointer text-sm font-semibold text-slate-200">{{ __('Show/hide languages') }}</summary>
        <p class="mt-3 text-xs text-slate-500">{{ __('Choose which language tabs to display. This preference only affects your admin view.') }}</p>
        <div class="mt-3 flex flex-wrap gap-3">
            @foreach($languages as $locale => $language)
                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" :checked="!hiddenLanguages.includes('{{ $locale }}')" @click="toggleLanguage('{{ $locale }}')" @disabled($locale === 'en') class="rounded border-slate-700 bg-slate-900 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50">
                    {{ $language['english'] }}
                </label>
            @endforeach
        </div>
        <button type="button" @click="showAllLanguages()" class="mt-4 text-xs font-semibold text-indigo-300 hover:text-white">{{ __('Show all languages') }}</button>
    </details>

    @can('translations.manage')
        <section class="rounded-xl border border-slate-800 bg-[#0f172a] p-4">
            <h2 class="text-sm font-semibold text-slate-200">{{ __('Available to players') }}</h2>
            <p class="mt-1 text-xs text-slate-500">{{ __('Disabled languages are hidden from the site language picker. Existing users fall back to English. Stored translations are kept.') }}</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($languages as $locale => $language)
                    <label wire:key="language-availability-{{ $locale }}" class="flex items-center justify-between gap-3 rounded-lg border border-slate-800 bg-slate-950/50 px-3 py-2 text-sm text-slate-300">
                        <span><span class="fi fi-{{ $language['flag'] ?? 'un' }} mr-2 rounded-sm" aria-hidden="true"></span>{{ $language['english'] }}</span>
                        <input type="checkbox" data-player-language="{{ $locale }}" @checked(isset($availableLanguages[$locale])) @disabled($locale === 'en') wire:change="setLanguageAvailability('{{ $locale }}', $event.target.checked)" wire:loading.attr="disabled" wire:target="setLanguageAvailability" class="rounded border-slate-700 bg-slate-900 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50">
                    </label>
                @endforeach
            </div>
            @error('languageAvailability') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
        </section>
    @endcan

    <nav aria-label="Translation languages" class="flex gap-2 overflow-x-auto rounded-xl border border-slate-800 bg-[#0f172a] p-3">
        @foreach($languages as $locale => $language)
            <button type="button" data-translation-language="{{ $locale }}" x-show="!hiddenLanguages.includes('{{ $locale }}')" wire:click="$set('localeFilter', '{{ $locale }}')" aria-current="{{ $localeFilter === $locale ? 'page' : 'false' }}" @class([
                'shrink-0 rounded-lg border px-4 py-2 text-sm font-semibold transition',
                'border-indigo-500 bg-indigo-600/20 text-indigo-200' => $localeFilter === $locale,
                'border-slate-800 bg-slate-900 text-slate-400 hover:text-white' => $localeFilter !== $locale,
            ])>
                <span class="fi fi-{{ $language['flag'] ?? 'un' }} mr-2 rounded-sm" aria-hidden="true"></span>
                {{ $language['english'] }}
                @if(($missingCounts[$locale] ?? 0) > 0)
                    <span class="ml-2 rounded bg-amber-500/10 px-1.5 py-0.5 text-xs text-amber-300">{{ $missingCounts[$locale] }}</span>
                @endif
            </button>
        @endforeach
    </nav>

    <div class="overflow-hidden rounded-xl border border-slate-800 bg-[#0f172a]">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[540px] table-fixed text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="p-4">English</th>
                        @if($localeFilter !== 'en')<th class="p-4">{{ __(':language translation', ['language' => $languages[$localeFilter]['english']]) }}</th>@endif
                        <th class="w-28 p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($keys as $keyRow)
                        @php($row = $rows->get($keyRow->key, collect())->keyBy('locale'))
                        <tr class="hover:bg-slate-900/40">
                            <td class="break-words p-4 align-top">
                                <p translate="no" class="font-semibold text-slate-200">{{ $row->get('en')?->text ?? $keyRow->key }}</p>
                                @if($keyRow->text !== null && $keyRow->text !== $keyRow->key)<p translate="no" class="mt-1 truncate font-mono text-[10px] text-slate-600">{{ $keyRow->key }}</p>@endif
                            </td>
                            @if($localeFilter !== 'en')
                            @php($value = $row->get($localeFilter)?->text)
                            <td class="break-words p-4 align-top">
                                @if($value !== null && $value !== '')
                                    <span translate="no" class="whitespace-pre-line text-slate-300">{{ $value }}</span>
                                @else
                                    <span class="rounded border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[9px] font-bold uppercase text-amber-300">Missing</span>
                                @endif
                            </td>
                            @endif
                            <td class="p-4 text-right align-top">
                                <button type="button" wire:click="editKey(@js($keyRow->key))" class="rounded-lg border border-indigo-900/50 bg-indigo-950/40 p-1.5 text-indigo-400 hover:text-white" title="Edit translation">
                                    <i data-lucide="edit" class="h-4 w-4"></i>
                                </button>
                                @can('translations.delete')
                                    <button type="button" wire:click="deleteKey(@js($keyRow->key))" wire:confirm="{{ __('Delete this English phrase and its translations? Stored entries remain recoverable.') }}" class="rounded-lg border border-red-900/50 bg-red-950/40 p-1.5 text-red-400 hover:text-white" title="{{ __('Delete phrase') }}"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $localeFilter === 'en' ? 2 : 3 }}" class="p-8 text-center text-slate-500">No translation keys found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($this->translationTableReady){{ $keys->links('vendor.livewire.custom-pagination') }}@endif

    @if($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
            <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl border border-slate-700 bg-[#0f172a] shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-800 p-5">
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-200">Edit Translation</h3>
                        <p class="mt-1 max-w-2xl break-words font-mono text-xs text-slate-500">{{ $editingKey }}</p>
                    </div>
                    <button type="button" wire:click="$set('showEditModal', false)" class="rounded-lg p-2 text-slate-500 hover:bg-slate-800 hover:text-white">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>

                <div class="grid gap-4 p-5 md:grid-cols-2">
                    <div>
                        <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">English phrase</p>
                        <p translate="no" class="whitespace-pre-line break-words rounded-lg border border-slate-800 bg-slate-950 p-3 text-sm text-slate-300">{{ $rows->get($editingKey, collect())->firstWhere('locale', 'en')?->text ?? $editingKey }}</p>
                    </div>
                    <div>
                        <label for="translation-value" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ __(':language translation', ['language' => $languages[$editingLocale]['english']]) }}</label>
                        <textarea id="translation-value" wire:model="values.{{ $editingLocale }}" rows="5" class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none"></textarea>
                        @error('values.'.$editingLocale) <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-800 p-5">
                    <button type="button" wire:click="$set('showEditModal', false)" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:text-white">
                        Cancel
                    </button>
                    <button type="button" wire:click="saveKey" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-500">
                        {{ __('Save translation') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
    @endunless
    <x-admin.deletion-actions resource="translations" />
</div>
