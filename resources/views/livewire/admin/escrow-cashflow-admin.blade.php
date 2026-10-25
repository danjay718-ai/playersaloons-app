<div class="space-y-6">
    <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-100">{{ __('Wallets & Transactions') }}</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-400">{{ __('Track wallet balances, prize money, and transactions. User deposits and money set aside for prizes are not company income. These totals do not show company profit.') }}</p>
            </div>
            <span class="rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-indigo-300">{{ __('Wallet records') }}</span>
        </div>
    </div>

    <section class="rounded-xl border border-emerald-500/30 bg-[#0f172a] p-5">
        <h3 class="text-sm font-black text-emerald-300">{{ __('PlayerSaloons income') }}</h3>
        <p class="mt-1 text-xs text-slate-400">{{ __('Fees collected before expenses. User deposits and prize funds are excluded. These totals use the selected dates only.') }}</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div><p class="text-xs text-slate-400">{{ __('Competition service fees') }}</p><p class="mt-2 text-xl font-bold text-white">@money($serviceFeeIncome)</p><p class="mt-1 text-xs text-slate-500">{{ __('Fees credited to the platform wallet from competitions and cancellations.') }}</p></div>
            <div><p class="text-xs text-slate-400">{{ __('Wallet top-up fees') }}</p><p class="mt-2 text-xl font-bold text-white">@money($topUpFeeIncome)</p><p class="mt-1 text-xs text-slate-500">{{ __('Extra fees collected on completed wallet top-ups, before payment processing costs.') }}</p></div>
            <div><p class="text-xs text-slate-400">{{ __('Total fees collected') }}</p><p class="mt-2 text-xl font-bold text-emerald-300">@money($totalFeeIncome)</p><p class="mt-1 text-xs text-slate-500">{{ __('Competition fees plus wallet top-up fees. This is income before expenses, not net profit.') }}</p></div>
        </div>
        <button type="button" wire:click="$set('typeFilter', 'PLATFORM_COMMISSION')" class="mt-4 text-xs font-bold text-emerald-400 hover:text-emerald-300">{{ __('View service fee transactions') }} →</button>
    </section>

    <p class="text-xs text-slate-400">{{ __('Current totals across all wallets. Filters apply only to transactions.') }}</p>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">{{ __('Money in user wallets') }}</p>
            <p class="mt-2 text-2xl font-black text-slate-100">@currencySymbol{{ number_format($userWalletLiability, 2) }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ __('Money belonging to users, held in their wallets.') }}</p>
        </div>
        <div class="rounded-xl border {{ $platformPosition < 0 ? 'border-red-500/40' : 'border-emerald-500/30' }} bg-[#0f172a] p-5">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">{{ __('Platform wallet balance') }}</p>
            <p class="mt-2 text-2xl font-black {{ $platformPosition < 0 ? 'text-red-400' : 'text-emerald-400' }}">@currencySymbol{{ number_format($platformPosition, 2) }}</p>
            <p class="mt-2 text-xs {{ $platformPosition < 0 ? 'text-red-300/80' : 'text-slate-500' }}">{{ $platformPosition < 0 ? __('A minus balance is the amount the platform needs to cover.') : __('Balance after fees and prize funding. This is not a profit total.') }}</p>
        </div>
        <div class="rounded-xl border border-amber-500/30 bg-[#0f172a] p-5">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">{{ __('Prizes set aside') }}</p>
            <p class="mt-2 text-2xl font-black text-amber-300">@currencySymbol{{ number_format($sponsoredCommitments, 2) }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ __('Prize money reserved for competitions that have not paid out yet.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">{{ __('Total wallet balances') }}</p>
            <p class="mt-2 text-2xl font-black text-indigo-300">@currencySymbol{{ number_format($userWalletLiability + $platformPosition, 2) }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ __('User balances plus the platform balance. This is not company income.') }}</p>
        </div>
    </div>

    <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5">
        <h3 class="text-sm font-black text-slate-200">{{ __('Transactions by type') }}</h3>
        <p class="mt-1 text-xs text-slate-500">{{ __('Added increases a wallet balance; Deducted reduces it. Totals use the transaction filters below.') }}</p>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($flowByType as $type => $flow)
                @php($transactionType = \App\Shared\Enums\LedgerType::from($type))
                <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $transactionType->label() }}</p>
                        <span class="text-[9px] text-slate-600">{{ __(':count transactions', ['count' => $flow->entry_count]) }}</span>
                    </div>
                    <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ $transactionType->description() }}</p>
                    <div class="mt-3 flex justify-between text-xs"><span class="text-emerald-400">{{ __('Added') }} @currencySymbol{{ number_format((float) $flow->inflow, 2) }}</span><span class="text-red-400">{{ __('Deducted') }} @currencySymbol{{ number_format((float) $flow->outflow, 2) }}</span></div>
                    <p class="mt-2 text-right text-xs font-bold {{ (float) $flow->net_amount < 0 ? 'text-red-300' : 'text-slate-300' }}">{{ __('Balance change') }} @currencySymbol{{ number_format((float) $flow->net_amount, 2) }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5">
        <div class="flex items-center justify-between gap-4">
            <div><h3 class="text-sm font-black text-slate-200">{{ __('Competition prize money') }}</h3><p class="mt-1 text-xs text-slate-500">{{ __('See which prizes are set aside, paid to players, or returned to the platform.') }}</p></div>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-xs">
                <thead><tr class="border-b border-slate-800 text-[10px] font-black uppercase tracking-wider text-slate-500"><th class="pb-3">{{ __('Competition') }}</th><th class="pb-3">{{ __('Start time (:timezone)', ['timezone' => app(\App\Modules\Tournament\Services\TournamentTimezone::class)->value()]) }}</th><th class="pb-3">{{ __('1st / 2nd prize') }}</th><th class="pb-3">{{ __('Still set aside') }}</th><th class="pb-3">{{ __('Prize status') }}</th><th class="pb-3 text-right">{{ __('Details') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($commitments as $tournament)
                        <tr>
                            <td class="py-3 font-semibold text-slate-200">{{ $tournament->name }}</td>
                            <td class="py-3 font-mono text-slate-400">{{ $tournament->start_at?->copy()->setTimezone(app(\App\Modules\Tournament\Services\TournamentTimezone::class)->value())->format('Y-m-d H:i:s') }}</td>
                            <td class="py-3 text-slate-300">@currencySymbol{{ number_format((float) $tournament->prize_1st, 2) }} / @currencySymbol{{ number_format((float) $tournament->prize_2nd, 2) }}</td>
                            <td class="py-3 font-bold text-amber-300">@currencySymbol{{ number_format((float) $tournament->reserved_prize_amount, 2) }}</td>
                            <td class="py-3"><span class="rounded-full bg-slate-800 px-2 py-1 text-[9px] font-black uppercase text-slate-300">{{ match ($tournament->funding_state) { 'reserved' => __('Set aside'), 'paid' => __('Paid to players'), 'released' => __('Returned to platform'), default => __('Awaiting prize update') } }}</span></td>
                            <td class="py-3 text-right"><a href="{{ route('admin.tournaments.v2.occurrences.show', $tournament) }}" class="font-bold text-indigo-400 hover:text-indigo-300">{{ __('View competition') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center italic text-slate-500">{{ __('No competition prize money recorded yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5">
        <h3 class="text-sm font-black text-slate-200">{{ __('Transaction history') }}</h3>
        <p class="mb-4 mt-1 text-xs text-slate-500">{{ __('Each wallet addition or deduction, with the balance after that transaction. Transfers can appear as a deduction in one wallet and an addition in another.') }}</p>
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-5">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search user, note, or reference') }}" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-xs text-slate-200 lg:col-span-2">
            <select wire:model.live="typeFilter" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-xs text-slate-200"><option value="">{{ __('All transaction types') }}</option>@foreach($ledgerTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select>
            <input wire:model.live="dateFrom" type="date" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-xs text-slate-200">
            <div class="flex gap-2"><input wire:model.live="dateTo" type="date" class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-xs text-slate-200"><button wire:click="clearFilters" type="button" class="rounded-lg border border-slate-700 px-3 text-xs font-bold text-slate-400 hover:text-white">Clear</button></div>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[1050px] text-left text-xs">
                <thead><tr class="border-b border-slate-800 text-[10px] font-black uppercase tracking-wider text-slate-500"><th class="pb-3">{{ __('Date & time (UTC)') }}</th><th class="pb-3">{{ __('Wallet owner') }}</th><th class="pb-3">{{ __('Transaction type') }}</th><th class="pb-3">{{ __('Added') }}</th><th class="pb-3">{{ __('Deducted') }}</th><th class="pb-3">{{ __('Balance after transaction') }}</th><th class="pb-3">{{ __('Details') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($entries as $entry)
                        <tr class="align-top">
                            <td class="py-3 font-mono text-slate-400">{{ $entry->created_at?->utc()->format('Y-m-d H:i:s') }}</td>
                            <td class="py-3"><p class="font-semibold text-slate-200">{{ $entry->wallet?->user?->username ?? 'Unknown wallet' }}</p><p class="mt-0.5 text-[10px] text-slate-600">{{ $entry->wallet?->user?->email }}</p></td>
                            <td class="py-3 font-bold text-indigo-300">{{ $entry->type->label() }}</td>
                            <td class="py-3 font-bold text-emerald-400">{{ (float) $entry->amount > 0 ? \App\Shared\Support\Currency::symbol().number_format((float) $entry->amount, 2) : '—' }}</td>
                            <td class="py-3 font-bold text-red-400">{{ (float) $entry->amount < 0 ? \App\Shared\Support\Currency::symbol().number_format(abs((float) $entry->amount), 2) : '—' }}</td>
                            <td class="py-3 font-mono {{ (float) $entry->running_balance < 0 ? 'text-red-400' : 'text-slate-300' }}">@currencySymbol{{ number_format((float) $entry->running_balance, 2) }}</td>
                            <td class="py-3"><p class="text-[10px] text-slate-500">{{ class_basename($entry->reference_type ?? 'Manual') }} #{{ $entry->reference_id ?? '—' }}</p><p class="mt-1 max-w-sm text-slate-300">{{ $entry->description ?: 'No description' }}</p></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center italic text-slate-500">{{ __('No transactions match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $entries->links() }}</div>
    </div>
</div>
