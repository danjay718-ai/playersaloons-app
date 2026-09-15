<div x-data="{ showDetail: @entangle('showDetailModal') }">
    <livewire:admin.recoverable-delete resource="errors" />

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-indigo-400">System observability</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-100">Error Logs</h1>
            <p class="mt-1 text-sm text-slate-500">Sanitized incidents from HTTP, Livewire, queue workers, and scheduled tasks.</p>
        </div>
        <div class="flex gap-3">
            <div class="rounded-xl border border-slate-800 bg-[#0f172a] px-4 py-3 text-right">
                <p class="text-[9px] font-bold uppercase tracking-wider text-slate-500">Open</p>
                <p class="text-xl font-bold text-red-300">{{ number_format($openCount) }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-[#0f172a] px-4 py-3 text-right">
                <p class="text-[9px] font-bold uppercase tracking-wider text-slate-500">Occurrences today</p>
                <p class="text-xl font-bold text-amber-300">{{ number_format($todayCount) }}</p>
            </div>
        </div>
    </div>

    <div class="mb-6 rounded-xl border border-slate-800 bg-[#0f172a] p-4">
        <h2 class="mb-4 text-xs font-bold uppercase tracking-widest text-slate-400">Filter incidents</h2>
        <div class="flex flex-wrap items-center gap-3">
            <input wire:model.live.debounce.350ms="search" type="search" placeholder="Reference, exception, message, route..." class="w-full rounded-lg border border-slate-800 bg-slate-900 px-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none sm:w-72">
            <select wire:model.live="stateFilter" class="rounded-lg border border-slate-800 bg-slate-900 px-4 py-2 text-sm text-slate-300 focus:border-indigo-500 focus:outline-none">
                <option value="open">Open</option>
                <option value="resolved">Resolved</option>
                <option value="all">All states</option>
            </select>
            <select wire:model.live="levelFilter" class="rounded-lg border border-slate-800 bg-slate-900 px-4 py-2 text-sm text-slate-300 focus:border-indigo-500 focus:outline-none">
                <option value="">All levels</option>
                <option value="error">Error</option>
                <option value="warning">Warning</option>
            </select>
            <select wire:model.live="sourceFilter" class="rounded-lg border border-slate-800 bg-slate-900 px-4 py-2 text-sm text-slate-300 focus:border-indigo-500 focus:outline-none">
                <option value="">All sources</option>
                <option value="http">HTTP / Livewire</option>
                <option value="queue">Queue</option>
                <option value="scheduler">Scheduler</option>
                <option value="console">Console</option>
            </select>
            <div class="flex items-center gap-2">
                <input wire:model.live="startDateFilter" type="date" aria-label="Start date" class="rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-300 focus:border-indigo-500 focus:outline-none">
                <span class="text-sm text-slate-600">to</span>
                <input wire:model.live="endDateFilter" type="date" aria-label="End date" class="rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-300 focus:border-indigo-500 focus:outline-none">
            </div>
            <select wire:model.live="perPage" class="rounded-lg border border-slate-800 bg-slate-900 px-4 py-2 text-sm text-slate-300 focus:border-indigo-500 focus:outline-none">
                <option value="10">10 per page</option>
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
            </select>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 flex items-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            <i data-lucide="check-circle" class="mr-2 h-4 w-4"></i>{{ session('success') }}
        </div>
    @endif

    <div class="mb-6 rounded-xl border border-slate-800 bg-[#0f172a] shadow-sm">
        <div class="min-h-[350px] overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-[10px] font-bold uppercase text-slate-400">
                        <th class="p-4">Reference / exception</th>
                        <th class="p-4">Source</th>
                        <th class="p-4">Route</th>
                        <th class="p-4">User</th>
                        <th class="p-4">Count</th>
                        <th class="p-4">Last seen</th>
                        <th class="p-4">State</th>
                        <th class="p-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @forelse($incidents as $incident)
                        <tr wire:key="incident-{{ $incident->id }}" class="hover:bg-slate-900/40">
                            <td class="max-w-sm p-4">
                                <button wire:click="selectIncident({{ $incident->id }})" class="block text-left font-mono text-[10px] font-bold text-indigo-300 hover:text-indigo-200">{{ $incident->reference_id }}</button>
                                <span class="mt-1 block truncate text-slate-300">{{ class_basename($incident->exception_class) }}</span>
                                <span class="mt-1 block truncate text-[10px] text-slate-600">{{ $incident->message }}</span>
                            </td>
                            <td class="p-4"><span class="rounded border border-slate-700 bg-slate-900 px-2 py-1 uppercase text-slate-400">{{ $incident->source }}</span></td>
                            <td class="p-4 text-slate-400">{{ $incident->route ?: $incident->path ?: '—' }}</td>
                            <td class="p-4 text-slate-400">{{ $incident->user?->username ?: 'Guest/System' }}</td>
                            <td class="p-4 font-bold text-amber-300">{{ number_format($incident->occurrences) }}</td>
                            <td class="whitespace-nowrap p-4 text-slate-400" title="{{ $incident->last_seen_at?->toDateTimeString() }}">{{ $incident->last_seen_at?->diffForHumans() }}</td>
                            <td class="p-4">
                                @if($incident->resolved_at)
                                    <span class="rounded border border-emerald-800/60 bg-emerald-950/40 px-2 py-1 text-emerald-300">Resolved</span>
                                @else
                                    <span class="rounded border border-red-800/60 bg-red-950/40 px-2 py-1 text-red-300">Open</span>
                                @endif
                            </td>
                            <td class="p-4 text-right"><button wire:click="selectIncident({{ $incident->id }})" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-slate-300 hover:border-indigo-600 hover:text-indigo-300">View</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-10 text-center italic text-slate-500">No error incidents found matching the filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-8 flex flex-col gap-4 border-t border-slate-800 pt-6 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-xs text-slate-500">Showing {{ $incidents->firstItem() ?? 0 }} to {{ $incidents->lastItem() ?? 0 }} of {{ $incidents->total() }} results</div>
        {{ $incidents->links('vendor.livewire.custom-pagination') }}
    </div>

    <div x-show="showDetail" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" @click="$wire.closeDetailModal()"></div>
        @if($selectedIncident)
            <section class="relative z-10 flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl border border-slate-800 bg-[#0f172a] shadow-2xl">
                <header class="flex items-start justify-between border-b border-slate-800 bg-[#0b0f19] px-6 py-4">
                    <div>
                        <p class="font-mono text-[10px] font-bold text-indigo-300">{{ $selectedIncident->reference_id }}</p>
                        <h2 class="mt-1 break-all text-sm font-bold text-slate-100">{{ $selectedIncident->exception_class }}</h2>
                    </div>
                    <button wire:click="closeDetailModal" class="rounded-lg p-2 text-slate-500 hover:bg-slate-800 hover:text-white"><i data-lucide="x" class="h-4 w-4"></i></button>
                </header>
                <div class="space-y-5 overflow-y-auto p-6">
                    <dl class="grid gap-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt class="text-slate-600">Source</dt><dd class="mt-1 text-slate-300">{{ $selectedIncident->source }} / {{ $selectedIncident->status_code }}</dd></div>
                        <div><dt class="text-slate-600">Occurrences</dt><dd class="mt-1 text-slate-300">{{ number_format($selectedIncident->occurrences) }}</dd></div>
                        <div><dt class="text-slate-600">First seen</dt><dd class="mt-1 text-slate-300">{{ $selectedIncident->first_seen_at?->toDateTimeString() }}</dd></div>
                        <div><dt class="text-slate-600">Last seen</dt><dd class="mt-1 text-slate-300">{{ $selectedIncident->last_seen_at?->toDateTimeString() }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-600">Route / path</dt><dd class="mt-1 break-all text-slate-300">{{ $selectedIncident->method }} {{ $selectedIncident->route ?: $selectedIncident->path ?: '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-600">Actor</dt><dd class="mt-1 text-slate-300">{{ $selectedIncident->user ? $selectedIncident->user->username.' (#'.$selectedIncident->user->id.')' : 'Guest/System' }}</dd></div>
                    </dl>
                    <div><h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Sanitized message</h3><p class="mt-2 rounded-lg border border-slate-800 bg-slate-950 p-4 text-sm leading-6 text-slate-300">{{ $selectedIncident->message }}</p></div>
                    @if($selectedIncident->context)
                        <div><h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Context</h3><pre class="mt-2 overflow-x-auto rounded-lg border border-slate-800 bg-slate-950 p-4 text-[11px] leading-5 text-cyan-200">{{ json_encode($selectedIncident->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
                    @endif
                    <div><h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Stack trace</h3><pre class="mt-2 max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-lg border border-slate-800 bg-slate-950 p-4 text-[10px] leading-5 text-slate-400">{{ $selectedIncident->stack_trace }}</pre></div>
                    @can('error_incidents.manage')
                        @if($selectedIncident->resolved_at)
                            <div class="rounded-lg border border-emerald-900/60 bg-emerald-950/20 p-4 text-sm text-emerald-200">Resolved by {{ $selectedIncident->resolver?->username ?? 'Unknown' }} on {{ $selectedIncident->resolved_at->toDateTimeString() }}@if($selectedIncident->resolution_notes)<p class="mt-2 text-emerald-300/70">{{ $selectedIncident->resolution_notes }}</p>@endif</div>
                            <button wire:click="reopenIncident" class="rounded-lg border border-amber-700 bg-amber-950/30 px-5 py-3 text-xs font-bold uppercase tracking-wider text-amber-300 hover:bg-amber-950/60">Reopen incident</button>
                        @else
                            <div><label for="resolutionNotes" class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Resolution notes</label><textarea id="resolutionNotes" wire:model="resolutionNotes" maxlength="2000" rows="3" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none" placeholder="Optional remediation or ticket reference"></textarea>@error('resolutionNotes')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror</div>
                            <button wire:click="resolveIncident" class="rounded-lg bg-emerald-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-emerald-500">Mark resolved</button>
                        @endif
                    @endcan
                </div>
            </section>
        @endif
    </div>
</div>
