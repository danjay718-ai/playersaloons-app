<x-layouts.admin title="Head-to-Head Management" admin_title="Head-to-Head Management">
    @php
        $filterUrl = static fn (array $overrides = []) => route('admin.h2h.index', array_merge(request()->except('page'), $overrides));
        $statusCards = [
            'active' => ['label' => 'Active', 'description' => 'Scheduled → Ongoing', 'count' => $countActive, 'icon' => 'zap', 'accent' => 'fuchsia'],
            'completed' => ['label' => 'Completed', 'description' => 'Finished H2H matches', 'count' => $countCompleted, 'icon' => 'trophy', 'accent' => 'emerald'],
            'cancelled' => ['label' => 'Cancelled', 'description' => 'Cancelled & refunded', 'count' => $countCancelled, 'icon' => 'x-circle', 'accent' => 'red'],
            'all' => ['label' => 'All', 'description' => 'Every H2H schedule', 'count' => $countAll, 'icon' => 'layers', 'accent' => 'slate'],
        ];
    @endphp
    <div class="w-full space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-[10px] font-black uppercase tracking-[.24em] text-fuchsia-400">Platform competitions</p><h1 class="mt-1 text-2xl font-black text-white">Head-to-Head schedules</h1><p class="mt-1 text-sm text-slate-400">One card per reusable 1v1 parent. Open its slots to manage immutable match occurrences.</p></div>
            <a href="{{ route('admin.h2h.v2.create') }}" class="inline-flex items-center justify-center rounded-lg bg-fuchsia-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-fuchsia-500"><i data-lucide="swords" class="mr-2 h-4 w-4"></i>Create H2H schedule</a>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach($statusCards as $key => $card)
                @php($selected = $statusTab === $key)
                <a href="{{ $filterUrl(['status_tab' => $key, 'status' => '']) }}" class="group relative overflow-hidden rounded-xl border p-4 text-left transition-all duration-200 {{ $selected ? 'border-fuchsia-500/60 bg-fuchsia-500/10 shadow-lg shadow-fuchsia-500/10' : 'border-slate-800 bg-slate-900/60 hover:border-slate-700 hover:bg-slate-800/60' }}">
                    <div class="mb-2 flex items-center justify-between"><i data-lucide="{{ $card['icon'] }}" class="h-4 w-4 {{ $selected ? 'text-fuchsia-400' : 'text-slate-600 group-hover:text-slate-400' }}"></i><span class="text-xs font-black tabular-nums {{ $selected ? 'text-fuchsia-300' : 'text-slate-600 group-hover:text-slate-400' }}">{{ $card['count'] }}</span></div>
                    <p class="text-sm font-bold {{ $selected ? 'text-white' : 'text-slate-400 group-hover:text-slate-200' }}">{{ $card['label'] }}</p><p class="mt-0.5 text-[10px] {{ $selected ? 'text-fuchsia-400/70' : 'text-slate-600' }}">{{ $card['description'] }}</p>
                    @if($selected)<div class="absolute inset-x-0 bottom-0 h-0.5 bg-fuchsia-500"></div>@endif
                </a>
            @endforeach
        </div>

        <form x-data x-ref="h2hFilters" method="GET" class="rounded-xl border border-slate-800 bg-[#0f172a] p-4">
            <input type="hidden" name="status_tab" value="{{ $statusTab }}">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <h2 class="mb-4 text-xs font-bold uppercase tracking-widest text-slate-400">Filter Head-to-Head</h2>
            <div class="mb-4 flex gap-2 overflow-x-auto border-b border-slate-800 pb-2">
                @foreach(['all' => 'All', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'one-time' => 'One-time'] as $key => $label)
                    <a href="{{ $filterUrl(['tab' => $key]) }}" class="whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === $key ? 'bg-fuchsia-900 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <input @input.debounce.350ms="$refs.h2hFilters.requestSubmit()" name="search" value="{{ $search }}" type="search" placeholder="Search H2H schedules" class="min-w-52 rounded-lg border border-slate-800 bg-slate-950 px-4 py-2 text-sm text-slate-100 placeholder-slate-500 outline-none focus:border-fuchsia-500">
                <select @change="$refs.h2hFilters.status_tab.value = 'all'; $refs.h2hFilters.requestSubmit()" name="status" class="rounded-lg border border-slate-800 bg-slate-950 px-4 py-2 text-sm text-slate-300"><option value="">All statuses</option>@foreach(\App\Shared\Enums\TournamentStatus::cases() as $item)<option value="{{ $item->value }}" @selected($status === $item->value)>{{ str_replace('_', ' ', $item->value) }}</option>@endforeach</select>
                <select @change="$refs.h2hFilters.requestSubmit()" name="game_id" class="rounded-lg border border-slate-800 bg-slate-950 px-4 py-2 text-sm text-slate-300"><option value="">All games</option>@foreach($games as $game)<option value="{{ $game->id }}" @selected($gameId === (string) $game->id)>{{ $game->localizedName() }}</option>@endforeach</select>
                <select @change="$refs.h2hFilters.requestSubmit()" name="platform_id" class="rounded-lg border border-slate-800 bg-slate-950 px-4 py-2 text-sm text-slate-300"><option value="">All platforms</option>@foreach($platforms as $platform)<option value="{{ $platform->id }}" @selected($platformId === (string) $platform->id)>{{ $platform->name }}</option>@endforeach</select>
                @if($activeTab === 'daily')<div><input @change="$refs.h2hFilters.requestSubmit()" name="start_time" value="{{ $startTime }}" type="time" aria-label="Exact UTC start time" class="rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-300 [color-scheme:dark]"><p class="mt-1 text-[10px] text-slate-500">Exact UTC start time</p></div>@else<div class="flex items-center gap-2"><input @change="$refs.h2hFilters.requestSubmit()" name="start_date" value="{{ $startDate }}" type="date" aria-label="Start date" class="rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-300 [color-scheme:dark]"><span class="text-sm text-slate-500">to</span><input @change="$refs.h2hFilters.requestSubmit()" name="end_date" value="{{ $endDate }}" type="date" aria-label="End date" class="rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-300 [color-scheme:dark]"></div>@endif
                <select @change="$refs.h2hFilters.requestSubmit()" name="per_page" class="rounded-lg border border-slate-800 bg-slate-950 px-4 py-2 text-sm text-slate-300">@foreach([5, 10, 25, 50] as $option)<option value="{{ $option }}" @selected($perPage === $option)>{{ $option }} per page</option>@endforeach</select>
            </div>
        </form>

        <x-admin.v2-schedule-list :templates="$templates" :head-to-head="true" :status-filter="$status" :status-tab="$statusTab" :active-tab="$activeTab" :start-date="$startDate" :end-date="$endDate" :start-time="$startTime" />
    </div>
    <x-admin.deletion-actions resource="head_to_head_schedules" />
</x-layouts.admin>
