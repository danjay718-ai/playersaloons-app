<div class="space-y-6">
    <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-100">Escrow & Cashflow</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-400">Immutable wallet ledger, player liabilities, platform-funded prize commitments, and their source flows. A negative platform position is an explicit amount owed by the platform to circulation—not a cash deposit.</p>
            </div>
            <span class="rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-indigo-300">Ledger-backed</span>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">User wallet liability</p>
            <p class="mt-2 text-2xl font-black text-slate-100">${{ number_format($userWalletLiability, 2) }}</p>
            <p class="mt-2 text-xs text-slate-500">Balances held for all non-platform wallet owners.</p>
        </div>
        <div class="rounded-xl border {{ $platformPosition < 0 ? 'border-red-500/40' : 'border-emerald-500/30' }} bg-[#0f172a] p-5">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Platform account balance</p>
            <p class="mt-2 text-2xl font-black {{ $platformPosition < 0 ? 'text-red-400' : 'text-emerald-400' }}">${{ number_format($platformPosition, 2) }}</p>
            <p class="mt-2 text-xs {{ $platformPosition < 0 ? 'text-red-300/80' : 'text-slate-500' }}">{{ $platformPosition < 0 ? 'Amount the platform owes to circulation.' : 'Net platform account balance.' }}</p>
        </div>
        <div class="rounded-xl border border-amber-500/30 bg-[#0f172a] p-5">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Reserved sponsored prizes</p>
            <p class="mt-2 text-2xl font-black text-amber-300">${{ number_format($sponsoredCommitments, 2) }}</p>
            <p class="mt-2 text-xs text-slate-500">Open fixed-prize commitments.</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Combined wallet balances</p>
            <p class="mt-2 text-2xl font-black text-indigo-300">${{ number_format($userWalletLiability + $platformPosition, 2) }}</p>
            <p class="mt-2 text-xs text-slate-500">Sum of user and platform account balances.</p>
        </div>
    </div>

    <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5">
        <h3 class="text-sm font-black text-slate-200">Source cashflow</h3>
        <p class="mt-1 text-xs text-slate-500">Totals follow the filters below. Inflow credits a wallet; outflow debits a wallet or locks value.</p>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($flowByType as $type => $flow)
                <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ str($type)->replace('_', ' ')->title() }}</p>
                        <span class="text-[9px] text-slate-600">{{ $flow->entry_count }} entries</span>
                    </div>
                    <div class="mt-3 flex justify-between text-xs"><span class="text-emerald-400">In ${{ number_format((float) $flow->inflow, 2) }}</span><span class="text-red-400">Out ${{ number_format((float) $flow->outflow, 2) }}</span></div>
                    <p class="mt-2 text-right text-xs font-bold {{ (float) $flow->net_amount < 0 ? 'text-red-300' : 'text-slate-300' }}">Net ${{ number_format((float) $flow->net_amount, 2) }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5">
        <div class="flex items-center justify-between gap-4">
            <div><h3 class="text-sm font-black text-slate-200">Sponsored prize commitments</h3><p class="mt-1 text-xs text-slate-500">Latest reserve, payout, and release states.</p></div>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-xs">
                <thead><tr class="border-b border-slate-800 text-[10px] font-black uppercase tracking-wider text-slate-500"><th class="pb-3">Tournament</th><th class="pb-3">Starts UTC</th><th class="pb-3">First / Second</th><th class="pb-3">Reserved now</th><th class="pb-3">State</th><th class="pb-3 text-right">Audit</th></tr></thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($commitments as $tournament)
                        <tr>
                            <td class="py-3 font-semibold text-slate-200">{{ $tournament->name }}</td>
                            <td class="py-3 font-mono text-slate-400">{{ $tournament->start_at?->utc()->format('Y-m-d H:i:s') }}</td>
                            <td class="py-3 text-slate-300">${{ number_format((float) $tournament->prize_1st, 2) }} / ${{ number_format((float) $tournament->prize_2nd, 2) }}</td>
                            <td class="py-3 font-bold text-amber-300">${{ number_format((float) $tournament->reserved_prize_amount, 2) }}</td>
                            <td class="py-3"><span class="rounded-full bg-slate-800 px-2 py-1 text-[9px] font-black uppercase text-slate-300">{{ $tournament->funding_state }}</span></td>
                            <td class="py-3 text-right"><a href="{{ route('admin.tournaments.v2.occurrences.show', $tournament) }}" class="font-bold text-indigo-400 hover:text-indigo-300">View occurrence</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center italic text-slate-500">No sponsored prize commitments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5">
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-5">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Player, description, or reference" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-xs text-slate-200 lg:col-span-2">
            <select wire:model.live="typeFilter" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-xs text-slate-200"><option value="">All ledger sources</option>@foreach($ledgerTypes as $type)<option value="{{ $type->value }}">{{ str($type->value)->replace('_', ' ')->title() }}</option>@endforeach</select>
            <input wire:model.live="dateFrom" type="date" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-xs text-slate-200">
            <div class="flex gap-2"><input wire:model.live="dateTo" type="date" class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-xs text-slate-200"><button wire:click="clearFilters" type="button" class="rounded-lg border border-slate-700 px-3 text-xs font-bold text-slate-400 hover:text-white">Clear</button></div>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[1050px] text-left text-xs">
                <thead><tr class="border-b border-slate-800 text-[10px] font-black uppercase tracking-wider text-slate-500"><th class="pb-3">UTC timestamp</th><th class="pb-3">Account</th><th class="pb-3">Source</th><th class="pb-3">Inflow</th><th class="pb-3">Outflow</th><th class="pb-3">Running balance</th><th class="pb-3">Reference / Description</th></tr></thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($entries as $entry)
                        <tr class="align-top">
                            <td class="py-3 font-mono text-slate-400">{{ $entry->created_at?->utc()->format('Y-m-d H:i:s') }}</td>
                            <td class="py-3"><p class="font-semibold text-slate-200">{{ $entry->wallet?->user?->username ?? 'Unknown wallet' }}</p><p class="mt-0.5 text-[10px] text-slate-600">{{ $entry->wallet?->user?->email }}</p></td>
                            <td class="py-3 font-bold text-indigo-300">{{ str($entry->type->value)->replace('_', ' ')->title() }}</td>
                            <td class="py-3 font-bold text-emerald-400">{{ (float) $entry->amount > 0 ? '$'.number_format((float) $entry->amount, 2) : '—' }}</td>
                            <td class="py-3 font-bold text-red-400">{{ (float) $entry->amount < 0 ? '$'.number_format(abs((float) $entry->amount), 2) : '—' }}</td>
                            <td class="py-3 font-mono {{ (float) $entry->running_balance < 0 ? 'text-red-400' : 'text-slate-300' }}">${{ number_format((float) $entry->running_balance, 2) }}</td>
                            <td class="py-3"><p class="text-[10px] text-slate-500">{{ class_basename($entry->reference_type ?? 'Manual') }} #{{ $entry->reference_id ?? '—' }}</p><p class="mt-1 max-w-sm text-slate-300">{{ $entry->description ?: 'No description' }}</p></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center italic text-slate-500">No ledger entries match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $entries->links() }}</div>
    </div>
</div>
