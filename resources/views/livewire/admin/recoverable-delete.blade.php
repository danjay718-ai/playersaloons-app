<div class="{{ $menuItem ? 'w-full' : ($recordId === null ? 'col-span-full mb-4' : 'inline-block') }}" x-data x-on:admin-records-deleted.window="window.location.reload()">
    @if($allowed)
        @if($menuItem)
            <button type="button" wire:click="open" x-on:click="open = false" class="{{ $resource === 'games' ? 'game-action-item text-red-300' : 'group flex w-full items-center px-4 py-2 text-left text-xs text-red-400 hover:bg-slate-800 hover:text-red-300' }}">
                <i data-lucide="trash-2" class="{{ $resource === 'games' ? 'h-4 w-4' : 'mr-2 h-3.5 w-3.5' }}"></i>
                {{ $resource === 'games' ? 'Soft Delete' : 'Delete' }}{{ $recordId === null ? ' '.strtolower($definition[3]) : '' }}
            </button>
        @else
            <button type="button" wire:click="open" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm font-semibold text-red-400 hover:bg-red-500/20">{{ $resource === 'games' ? 'Soft Delete' : 'Delete' }}{{ $recordId === null ? ' '.strtolower($definition[3]) : '' }}</button>
        @endif
        @if($showModal)
            @teleport('body')
            <div class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="delete-title-{{ $resource }}" x-on:keydown.escape.window="$wire.cancel()">
                <button type="button" wire:click="cancel" class="absolute inset-0 bg-black/70" aria-label="Close deletion dialog"></button>
                <div class="relative flex max-h-[85vh] w-full max-w-2xl flex-col rounded-2xl border border-slate-700 bg-slate-900 p-5 text-slate-200 shadow-xl">
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <h2 id="delete-title-{{ $resource }}" class="text-lg font-semibold">{{ $resource === 'games' ? 'Soft Delete' : 'Delete' }} {{ strtolower($definition[3]) }}</h2>
                        <button type="button" wire:click="cancel" aria-label="Close" class="px-2 py-1">×</button>
                    </div>
                    @error('selectedIds')<p role="alert" class="mb-3 text-sm text-red-400">{{ $message }}</p>@enderror
                    @error('selectedIds.*')<p role="alert" class="mb-3 text-sm text-red-400">{{ $message }}</p>@enderror
                    @if(!$confirming)
                        <p class="mb-3 text-sm leading-6 text-slate-400">Select one or more records to delete. Connected history will be preserved. Deleted records disappear from normal lists for every role.</p>
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search {{ strtolower($definition[3]) }}" aria-label="Search records to delete" class="mb-3 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm">
                        <div class="min-h-0 overflow-y-auto">
                            @foreach($records as $record)
                                <label wire:key="delete-{{ $resource }}-{{ $record->id }}" class="flex items-start gap-3 border-b border-slate-700/60 py-3 text-sm">
                                    <input type="checkbox" wire:model.live="selectedIds" value="{{ $record->id }}" class="mt-1 rounded border-slate-600">
                                    <span><span class="block font-medium">{{ $service->label($resource, $record) }}</span><span class="text-slate-400">#{{ $record->id }}</span></span>
                                </label>
                            @endforeach
                            @if($records->isEmpty())<p class="py-6 text-center text-sm text-slate-400">No records available.</p>@endif
                        </div>
                        <div class="mt-3">{{ $records->links() }}</div>
                        <div class="mt-4 flex justify-end gap-3">
                            <button type="button" wire:click="cancel" class="rounded-lg border border-slate-700 px-4 py-2 text-sm">Cancel</button>
                            <button type="button" wire:click="preview" wire:loading.attr="disabled" @disabled(count($selectedIds) === 0) class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Review deletion ({{ count($selectedIds) }})</button>
                        </div>
                    @else
                        @if($blockedReasons)
                            <div role="alert" class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm leading-6 text-red-300">
                                <p class="font-semibold">Deletion blocked</p>
                                <p>Nothing will be deleted. Review the reason under each blocked record below, then cancel and resolve it before trying again.</p>
                            </div>
                        @endif
                        <p class="mb-4 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm leading-6 text-amber-300">These records will disappear from management and public lists. Their data stays stored for recovery, and connected history will remain intact.</p>
                        <div class="min-h-0 space-y-3 overflow-y-auto">
                            @foreach($selected as $record)
                                <div class="rounded-lg border border-slate-700 p-3">
                                    <p class="text-sm font-semibold">{{ $service->label($resource, $record) }}</p>
                                    <p class="mt-1 text-sm leading-6 text-slate-400">{{ $service->impact($record) }}</p>
                                    @if($reason = $blockedReasons[$record->id] ?? null)<p class="mt-2 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm leading-6 text-red-300">Blocked: {{ $reason }}</p>@endif
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 flex justify-end gap-3">
                            <button type="button" wire:click="cancel" class="rounded-lg border border-slate-700 px-4 py-2 text-sm">Cancel</button>
                            <button type="button" wire:click="deleteSelected" wire:loading.attr="disabled" @disabled(count($blockedReasons) > 0) class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">{{ $resource === 'games' ? 'Soft Delete' : 'Delete' }} selected ({{ count($selectedIds) }})</button>
                        </div>
                    @endif
                </div>
            </div>
            @endteleport
        @endif
    @endif
</div>
