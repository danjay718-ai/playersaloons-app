<div class="space-y-6">
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
                <p class="mt-1 text-xs text-slate-500">Edit app UI words from the database and export them back to Laravel JSON translation files.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="syncFromJson" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-300 hover:border-indigo-500/50 hover:text-white">
                    <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                    Sync JSON
                </button>
                <button type="button" wire:click="exportJson" class="inline-flex items-center gap-2 rounded-lg border border-emerald-700/50 bg-emerald-950/30 px-3 py-2 text-xs font-semibold text-emerald-300 hover:border-emerald-500/70 hover:text-white">
                    <i data-lucide="download" class="h-4 w-4"></i>
                    Export JSON
                </button>
                <button type="button" wire:click="fillMissingWithEnglish" wire:confirm="Fill every missing translation with its English text? This clears Missing badges, but real translations can still be edited later." class="inline-flex items-center gap-2 rounded-lg border border-amber-700/50 bg-amber-950/30 px-3 py-2 text-xs font-semibold text-amber-300 hover:border-amber-500/70 hover:text-white">
                    <i data-lucide="copy-check" class="h-4 w-4"></i>
                    Fill Missing
                </button>
            </div>
        </div>

        @if(session()->has('success'))
            <div class="mt-4 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                {{ session('success') }}
            </div>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
        <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-4">
            <div class="grid gap-3 md:grid-cols-[1fr_180px_auto] md:items-end">
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Search</label>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search translation key or English text..." class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Locale</label>
                    <select wire:model.live="localeFilter" class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                        <option value="all">All locales</option>
                        @foreach($languages as $locale => $language)
                            <option value="{{ $locale }}">{{ strtoupper($locale) }} - {{ $language['native'] }}</option>
                        @endforeach
                    </select>
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

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        @foreach($missingCounts as $locale => $count)
            <button type="button" wire:click="$set('localeFilter', '{{ $locale }}')" class="rounded-lg border border-slate-800 bg-slate-900/70 px-3 py-2 text-left">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ strtoupper($locale) }} missing</span>
                <span class="mt-1 block text-lg font-extrabold text-slate-100">{{ $count }}</span>
            </button>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-800 bg-[#0f172a]">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="p-4">Key / English</th>
                        @foreach($languages as $locale => $language)
                            @if($locale !== 'en')
                                <th class="p-4">{{ strtoupper($locale) }}</th>
                            @endif
                        @endforeach
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($keys as $keyRow)
                        @php($row = $rows->get($keyRow->key, collect())->keyBy('locale'))
                        <tr class="hover:bg-slate-900/40">
                            <td class="max-w-xs p-4 align-top">
                                <p class="font-semibold text-slate-200">{{ $row->get('en')?->text ?? $keyRow->key }}</p>
                                <p class="mt-1 truncate font-mono text-[10px] text-slate-600">{{ $keyRow->key }}</p>
                            </td>
                            @foreach($languages as $locale => $language)
                                @if($locale !== 'en')
                                    @php($value = $row->get($locale)?->text)
                                    <td class="max-w-[180px] p-4 align-top">
                                        @if($value)
                                            <span class="line-clamp-2 text-slate-350">{{ $value }}</span>
                                        @else
                                            <span class="rounded border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[9px] font-bold uppercase text-amber-300">Missing</span>
                                        @endif
                                    </td>
                                @endif
                            @endforeach
                            <td class="p-4 text-right align-top">
                                <button type="button" wire:click="editKey(@js($keyRow->key))" class="rounded-lg border border-indigo-900/50 bg-indigo-950/40 p-1.5 text-indigo-400 hover:text-white" title="Edit translations">
                                    <i data-lucide="edit" class="h-4 w-4"></i>
                                </button>
                                <button type="button" wire:click="deleteKey(@js($keyRow->key))" wire:confirm="Delete this translation key from every language?" class="ml-1 rounded-lg border border-red-900/50 bg-red-950/40 p-1.5 text-red-400 hover:text-white" title="Delete key">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($languages) + 1 }}" class="p-8 text-center text-slate-500">No translation keys found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $keys->links() }}

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
                    @foreach($languages as $locale => $language)
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                {{ strtoupper($locale) }} - {{ $language['native'] }}
                            </label>
                            <textarea wire:model="values.{{ $locale }}" rows="3" class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none"></textarea>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-800 p-5">
                    <button type="button" wire:click="$set('showEditModal', false)" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:text-white">
                        Cancel
                    </button>
                    <button type="button" wire:click="saveKey" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-500">
                        Save & Export
                    </button>
                </div>
            </div>
        </div>
    @endif
    @endunless
</div>
