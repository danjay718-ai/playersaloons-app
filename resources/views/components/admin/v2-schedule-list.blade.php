@props(['templates', 'headToHead' => false, 'livewire' => false, 'parentList' => false, 'statusFilter' => '', 'statusTab' => 'active', 'activeTab' => 'all', 'startDate' => '', 'endDate' => '', 'startTime' => ''])

@php
    $activeOccurrenceStatuses = ['DRAFT', 'PUBLISHED', 'REGISTRATION_OPEN', 'REGISTRATION_CLOSED', 'CHECKIN_OPEN', 'CHECKIN_CLOSED', 'BRACKET_GENERATED', 'ONGOING'];
    $matchesOccurrenceFilters = static function ($occurrence) use ($statusFilter, $statusTab, $activeOccurrenceStatuses, $activeTab, $startDate, $endDate, $startTime): bool {
        $status = $occurrence->status?->value ?? $occurrence->status;
        if ($statusFilter !== '' && $status !== $statusFilter) return false;
        if ($statusTab === 'active' && ! in_array($status, $activeOccurrenceStatuses, true)) return false;
        if ($statusTab === 'completed' && $status !== 'COMPLETED') return false;
        if ($statusTab === 'cancelled' && ! in_array($status, ['CANCELLED', 'REFUNDED'], true)) return false;
        if ($activeTab === 'daily' && $startTime !== '' && $occurrence->start_at?->utc()->format('H:i') !== $startTime) return false;
        if ($activeTab !== 'daily' && $startDate !== '' && (! $occurrence->start_at || $occurrence->start_at->utc()->toDateString() < $startDate)) return false;
        if ($activeTab !== 'daily' && $endDate !== '' && (! $occurrence->start_at || $occurrence->start_at->utc()->toDateString() > $endDate)) return false;

        return true;
    };
@endphp

<div class="overflow-hidden rounded-xl border {{ $headToHead ? 'border-fuchsia-900/50' : 'border-indigo-900/50' }} bg-[#0f172a] shadow-sm">
    <div class="border-b border-slate-800 p-4">
        <div class="flex items-start justify-between gap-3"><div><h2 class="text-sm font-bold text-white">{{ $headToHead ? 'Head-to-Head List' : 'Tournament List' }}</h2><p class="mt-1 text-xs text-slate-500">{{ $parentList ? 'One row per tournament schedule, showing its next occurrence. All timestamps are UTC.' : 'Configured slots and their current occurrences are managed directly here. All timestamps are UTC.' }}</p></div>@if($parentList)<a href="{{ route('admin.tournaments.v2.archived') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-700 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-300 hover:bg-slate-800"><i data-lucide="archive" class="h-3.5 w-3.5"></i>Archived</a>@endif</div>
    </div>
    @if($parentList)
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead><tr class="border-b border-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-400"><th class="p-4">Tournament</th><th class="p-4">Game</th><th class="p-4">Frequency</th><th class="p-4">Status</th><th class="p-4">Registrations</th><th class="p-4">Start (UTC)</th><th class="p-4">Time left</th><th class="p-4 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($templates as $template)
                        @php
                            $occurrenceSlots = $template->scheduleSlots->flatMap(fn ($slot) => $slot->occurrences->filter($matchesOccurrenceFilters)->map(fn ($occurrence) => ['occurrence' => $occurrence, 'slot' => $slot]));
                            $nextOccurrence = $occurrenceSlots
                                ->filter(fn ($item) => $item['occurrence']->start_at && $item['occurrence']->start_at->isFuture())
                                ->sortBy(fn ($item) => $item['occurrence']->start_at)
                                ->first() ?? $occurrenceSlots->sortByDesc(fn ($item) => $item['occurrence']->start_at)->first();
                            $occurrence = $nextOccurrence['occurrence'] ?? null;
                            $slot = $nextOccurrence['slot'] ?? null;
                            $status = $occurrence?->status?->value ?? $occurrence?->status;
                            $statusChoices = match ($status) {
                                'DRAFT' => ['PUBLISHED'],
                                'PUBLISHED' => ['DRAFT', 'REGISTRATION_OPEN'],
                                'REGISTRATION_OPEN' => ['PUBLISHED', 'REGISTRATION_CLOSED'],
                                'REGISTRATION_CLOSED' => ['REGISTRATION_OPEN'],
                                default => [],
                            };
                        @endphp
                        <tr wire:key="v2-template-row-{{ $template->id }}" @if($occurrence) x-data="tournamentCountdown(@js(now()->getTimestampMs()), @js(['startsIn' => 'Starts in', 'ongoing' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded', 'pending' => 'Start time pending', 'reached' => 'Start time reached']))" @endif class="hover:bg-slate-900/40">
                            <td class="p-4"><span class="font-semibold text-slate-100">{{ $template->name }}</span>@if($slot?->label)<span class="mt-1 block text-[10px] text-slate-500">{{ $slot->label }}</span>@endif</td>
                            <td class="p-4 text-slate-300">{{ $template->game?->translations->first()?->name ?? $template->game?->slug ?? '—' }}</td>
                            <td class="p-4 capitalize text-slate-300">{{ $template->recurrence_frequency?->value ?? 'One-time' }}</td>
                            <td class="p-4"><span class="rounded border border-slate-700 bg-slate-900 px-2 py-1 font-bold text-slate-300">{{ $status ? str_replace('_', ' ', $status) : 'Not materialized' }}</span></td>
                            <td class="p-4 tabular-nums text-slate-300">{{ $occurrence?->registrations_count ?? 0 }}/{{ $occurrence?->max_participants ?? $template->max_participants }}</td>
                            <td class="whitespace-nowrap p-4 tabular-nums text-slate-300">{{ $occurrence?->start_at?->utc()->format('Y-m-d H:i:s') ?? 'Pending' }}</td>
                            <td class="whitespace-nowrap p-4 text-cyan-300">@if($occurrence)<span x-text="countdown(@js($occurrence->start_at?->getTimestampMs()), @js($status))"></span>@else—@endif</td>
                            <td class="p-4 text-right">
                                @if($occurrence)
                                    <x-admin.action-dropdown>
                                        <div class="py-1">
                                            <a href="{{ route('admin.tournaments.v2.occurrences.edit', $occurrence) }}" class="flex items-center px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white"><i data-lucide="edit" class="mr-2 h-3.5 w-3.5 text-slate-500"></i>Edit Tournament</a>
                                            <form method="POST" action="{{ route('admin.tournaments.v2.occurrences.feature', $occurrence) }}">@csrf<input type="hidden" name="is_featured" value="{{ $occurrence->is_featured ? '0' : '1' }}"><button type="submit" class="flex w-full items-center px-4 py-2 text-left text-xs text-slate-300 hover:bg-slate-800 hover:text-white"><i data-lucide="{{ $occurrence->is_featured ? 'star-off' : 'star' }}" class="mr-2 h-3.5 w-3.5 text-amber-400"></i>{{ $occurrence->is_featured ? 'Unfeature Tournament' : 'Feature Tournament' }}</button></form>
                                            @if($statusChoices !== [])<div class="my-1 border-t border-slate-800"></div><p class="px-4 py-2 text-[9px] font-black uppercase tracking-widest text-slate-500">Change status</p>@foreach($statusChoices as $statusChoice)<form method="POST" action="{{ route('admin.tournaments.v2.occurrences.status', $occurrence) }}">@csrf<input type="hidden" name="status" value="{{ $statusChoice }}"><button type="submit" class="flex w-full items-center px-4 py-2 text-left text-xs text-slate-300 hover:bg-slate-800 hover:text-white">{{ str_replace('_', ' ', $statusChoice) }}</button></form>@endforeach @endif
                                            @if(! in_array($status, ['CANCELLED', 'REFUNDED', 'COMPLETED'], true))<form method="POST" action="{{ route('admin.tournaments.v2.occurrences.cancel', $occurrence) }}" onsubmit="return confirm('Cancel this tournament occurrence? Paid entry fees will be refunded and the prize reserve released.')">@csrf<input type="hidden" name="return_to" value="list"><button type="submit" class="flex w-full items-center px-4 py-2 text-left text-xs text-orange-300 hover:bg-slate-800"><i data-lucide="x-circle" class="mr-2 h-3.5 w-3.5"></i>Cancel Tournament</button></form>@endif
                                            @if(in_array($status, ['CANCELLED', 'REFUNDED', 'COMPLETED'], true))<form method="POST" action="{{ route('admin.tournaments.v2.occurrences.archive', $occurrence) }}" onsubmit="return confirm('Archive this tournament occurrence? It will be hidden from normal lists while its history is retained.')">@csrf<button type="submit" class="flex w-full items-center px-4 py-2 text-left text-xs text-slate-400 hover:bg-slate-800"><i data-lucide="archive" class="mr-2 h-3.5 w-3.5"></i>Archive</button></form>@endif
                                        </div>
                                    </x-admin.action-dropdown>
                                @else<span class="text-[10px] text-slate-600">Awaiting recurrence</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-8 text-center text-slate-500">No V2 tournament schedules match the filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
    <div class="divide-y divide-slate-800/70">
        @forelse($templates as $template)
            <section class="p-4" @if($livewire) wire:key="v2-template-{{ $template->id }}" @endif>
                <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                    <div><h3 class="font-semibold text-slate-100">{{ $template->name }}</h3><p class="mt-1 text-[10px] uppercase tracking-wider text-slate-500">{{ $template->game?->translations->first()?->name ?? $template->game?->slug }} · {{ $template->recurrence_frequency?->value ?? 'one-time' }}</p></div>
                </div>
                <div class="grid gap-2">
                    @foreach($template->scheduleSlots as $slot)
                        @php($matchingOccurrences = $slot->occurrences->filter($matchesOccurrenceFilters))
                        @if($matchingOccurrences->isEmpty() && ($slot->occurrences->isNotEmpty() || $statusFilter !== '' || $statusTab !== 'active' || $startDate !== '' || $endDate !== '' || $startTime !== '')) @continue @endif
                        @php($occurrence = $matchingOccurrences->sortByDesc('start_at')->first())
                        @php($status = $occurrence?->status?->value ?? $occurrence?->status)
                        <article class="grid items-center gap-3 rounded-lg border border-slate-800 bg-slate-950/65 p-3 md:grid-cols-[minmax(0,1.3fr)_auto_auto_auto]"
                            @if($occurrence) x-data="tournamentCountdown(@js(now()->getTimestampMs()), @js(['startsIn' => 'Starts in', 'ongoing' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded', 'pending' => 'Start time pending', 'reached' => 'Start time reached']))" @endif>
                            <div class="min-w-0"><p class="truncate text-xs font-bold text-slate-200">{{ $occurrence?->name ?? $template->name }}</p><p class="mt-1 text-[10px] text-slate-500">{{ $slot->label ?: 'Slot '.$loop->iteration }}</p></div>
                            <div class="text-xs"><span class="rounded border border-slate-700 bg-slate-900 px-2 py-1 font-bold text-slate-300">{{ $status ? str_replace('_', ' ', $status) : 'Not materialized' }}</span><span class="ml-2 text-slate-500">{{ $occurrence?->registrations_count ?? 0 }}/{{ $occurrence?->max_participants ?? ($slot->overrides_json['max_teams'] ?? $template->max_participants) }}</span></div>
                            <div class="text-xs tabular-nums"><p class="font-medium text-slate-300">{{ $occurrence?->start_at?->utc()->format('Y-m-d H:i:s') ?? 'Pending' }} UTC</p>@if($occurrence)<p class="mt-1 text-[10px] text-cyan-300" x-text="countdown(@js($occurrence->start_at?->getTimestampMs()), @js($status))"></p>@endif</div>
                            <div class="text-right">@if($occurrence)<a href="{{ route('admin.tournaments.v2.occurrences.edit', $occurrence) }}" class="inline-flex rounded-lg bg-indigo-600 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white hover:bg-indigo-500">Edit {{ $headToHead ? 'H2H' : 'Tournament' }}</a>@else<span class="text-[10px] text-slate-600">Awaiting recurrence</span>@endif</div>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="p-8 text-center text-sm text-slate-500">No {{ $headToHead ? 'platform H2H' : 'V2 tournament' }} schedules match the filters.</p>
        @endforelse
    </div>
    @endif
    @if($templates->hasPages())<div class="border-t border-slate-800 px-4 py-3">{{ $templates->links('vendor.livewire.custom-pagination', ['livewire' => $livewire]) }}</div>@endif
</div>
