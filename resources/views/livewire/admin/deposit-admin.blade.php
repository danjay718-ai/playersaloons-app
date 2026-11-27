<div class="space-y-6" @if(! $showDetailModal) wire:poll.10s.visible @endif>
    {{-- Live Sync Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-800 bg-[#0f172a] px-4 py-3 text-xs shadow-sm">
        <div class="flex items-center gap-2.5">
            <span class="relative flex h-2.5 w-2.5">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
            </span>
            <span class="font-semibold text-slate-200">{{ __('Live Deposit Monitor') }}</span>
            <span class="text-slate-400">· {{ __('Auto-refreshes every 10s') }}</span>
        </div>
        <div class="flex items-center gap-3">
            <span class="font-mono text-[11px] text-slate-400">{{ __('Updated') }} {{ $lastSyncedAt }}</span>
            <button
                type="button"
                wire:click="$refresh"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-[11px] font-semibold text-slate-200 transition hover:border-indigo-500/60"
            >
                <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i>
                <span>{{ __('Refresh') }}</span>
            </button>
        </div>
    </div>

    {{-- KPI Summary Strip --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ __('Completed Deposits') }}</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-400">
                    <i data-lucide="arrow-down-circle" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-3 text-2xl font-black text-slate-100 tabular-nums">{{ number_format($summary['completed_count']) }}</p>
            <p class="mt-1 text-[11px] text-slate-400">
                {{ __('Gross Charged') }}: <span class="font-semibold text-slate-200">@currencySymbol{{ $summary['gross_charged'] }}</span>
            </p>
        </div>

        <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ __('Net Credited to Wallets') }}</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-cyan-500/10 text-cyan-400">
                    <i data-lucide="wallet" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-3 text-2xl font-black text-emerald-400 tabular-nums">@currencySymbol{{ $summary['net_credited'] }}</p>
            <p class="mt-1 text-[11px] text-slate-400">{{ __('Credited across completed deposits') }}</p>
        </div>

        <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ __('Processing Fees') }}</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-400">
                    <i data-lucide="percent" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-3 text-2xl font-black text-indigo-300 tabular-nums">@currencySymbol{{ $summary['fees_collected'] }}</p>
            <p class="mt-1 text-[11px] text-slate-400">{{ __('Top-up fees collected') }}</p>
        </div>

        <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ __('Pending / Failed') }}</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-400">
                    <i data-lucide="clock-3" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-3 text-2xl font-black text-slate-100 tabular-nums">
                <span class="text-amber-400">{{ number_format($summary['pending_count']) }}</span>
                <span class="text-slate-500">/</span>
                <span class="text-red-400">{{ number_format($summary['failed_count']) }}</span>
            </p>
            <p class="mt-1 text-[11px] text-slate-400">{{ __('Live pending checkouts vs. failed or expired') }}</p>
        </div>
    </div>

    {{-- Live Pending Checkout Alert Banner --}}
    @if($summary['live_pending_checkouts_count'] > 0 && $viewMode === 'deposits')
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-xs">
            <div class="flex items-center gap-2.5 text-amber-300">
                <span class="h-2 w-2 animate-pulse rounded-full bg-amber-400"></span>
                <span class="font-semibold">
                    {{ __(':count live checkout(s) currently awaiting payment confirmation.', ['count' => number_format($summary['live_pending_checkouts_count'])]) }}
                </span>
            </div>
            <button
                type="button"
                wire:click="setViewMode('checkouts')"
                class="rounded-lg border border-amber-500/40 bg-amber-500/15 px-3 py-1.5 text-[11px] font-bold text-amber-200 transition hover:bg-amber-500/25"
            >
                {{ __('View Pending Checkouts') }} →
            </button>
        </div>
    @endif

    {{-- View Mode Tabs --}}
    <div class="flex flex-wrap items-center gap-2">
        <button
            type="button"
            wire:click="setViewMode('deposits')"
            class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-xs font-bold transition {{ $viewMode === 'deposits' ? 'border-indigo-500 bg-indigo-600 text-white shadow-sm' : 'border-slate-800 bg-[#0f172a] text-slate-300 hover:border-slate-700' }}"
        >
            <i data-lucide="check-circle-2" class="h-4 w-4"></i>
            <span>{{ __('Completed & Settled Deposits') }}</span>
            <span class="rounded-full px-2 py-0.5 font-mono text-[10px] {{ $viewMode === 'deposits' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-300' }}">
                {{ number_format($depositsTotal) }}
            </span>
        </button>

        <button
            type="button"
            wire:click="setViewMode('checkouts')"
            class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-xs font-bold transition {{ $viewMode === 'checkouts' ? 'border-amber-500 bg-amber-600 text-white shadow-sm' : 'border-slate-800 bg-[#0f172a] text-slate-300 hover:border-slate-700' }}"
        >
            <i data-lucide="clock-3" class="h-4 w-4"></i>
            <span>{{ __('Live Pending & Failed Checkouts') }}</span>
            <span class="rounded-full px-2 py-0.5 font-mono text-[10px] {{ $viewMode === 'checkouts' ? 'bg-white/20 text-white' : ($summary['live_pending_checkouts_count'] > 0 ? 'bg-amber-500/20 text-amber-300' : 'bg-slate-800 text-slate-300') }}">
                {{ number_format($checkoutsTotal) }}
            </span>
        </button>
    </div>

    {{-- Filters Bar --}}
    <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Search') }}</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Username, email, deposit UUID, or reference...') }}"
                    class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3.5 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                >
            </div>

            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Status') }}</label>
                <select
                    wire:model.live="statusFilter"
                    class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none"
                >
                    <option value="">{{ __('All Statuses') }}</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}">{{ strtoupper(str_replace('_', ' ', (string) $status)) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Provider') }}</label>
                <select
                    wire:model.live="providerFilter"
                    class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none"
                >
                    <option value="">{{ __('All Providers') }}</option>
                    @foreach($providers as $provider)
                        <option value="{{ $provider }}">{{ strtoupper((string) $provider) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('From Date') }}</label>
                <input
                    type="date"
                    wire:model.live="dateFrom"
                    class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none"
                >
            </div>

            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('To Date') }}</label>
                <input
                    type="date"
                    wire:model.live="dateTo"
                    class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none"
                >
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-800/70 pt-3 text-xs">
            <p class="text-slate-400">
                @if($viewMode === 'deposits' && $deposits)
                    {{ __('Showing :count matching deposits', ['count' => number_format($deposits->total())]) }}
                @elseif($checkouts)
                    {{ __('Showing :count matching checkouts', ['count' => number_format($checkouts->total())]) }}
                @endif
            </p>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="applyLast30Days"
                    class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-[11px] font-semibold text-slate-200 transition hover:border-indigo-500/60"
                >
                    {{ __('Last 30 Days') }}
                </button>
                <button
                    type="button"
                    wire:click="clearFilters"
                    class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-[11px] font-semibold text-slate-300 transition hover:border-slate-500"
                >
                    {{ __('All Time / Reset') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Main Table --}}
    @if($viewMode === 'deposits' && $deposits)
        <div class="overflow-hidden rounded-xl border border-slate-800 bg-[#0f172a] shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-[10px] font-bold uppercase text-slate-400">
                            <th class="p-4">{{ __('Player') }}</th>
                            <th class="p-4">{{ __('Net Credited') }}</th>
                            <th class="p-4">{{ __('Fee / Gross') }}</th>
                            <th class="p-4">{{ __('Provider & Reference') }}</th>
                            <th class="p-4">{{ __('Date') }}</th>
                            <th class="p-4">{{ __('Status') }}</th>
                            <th class="p-4 text-right">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        @forelse($deposits as $deposit)
                            @php
                                $player = $deposit->wallet?->user;
                                $netMinor = \App\Shared\Support\DecimalMoney::toMinor((string) $deposit->amount);
                                $feeMinor = \App\Shared\Support\DecimalMoney::toMinor((string) $deposit->fee_amount);
                                $grossFormatted = \App\Shared\Support\DecimalMoney::format($netMinor + $feeMinor);
                                $statusBadge = match ($deposit->status) {
                                    'completed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/25',
                                    'pending' => 'bg-amber-500/10 text-amber-400 border-amber-500/25',
                                    'failed', 'cancelled', 'expired' => 'bg-red-500/10 text-red-400 border-red-500/25',
                                    default => 'bg-slate-800 text-slate-300 border-slate-700',
                                };
                            @endphp
                            <tr class="hover:bg-slate-900/40" wire:key="deposit-{{ $deposit->id }}">
                                <td class="p-4">
                                    @if($player)
                                        <span class="font-semibold text-slate-200">{{ $player->username }}</span>
                                        @if($player->trashed())
                                            <span class="ml-1 inline-flex rounded border border-red-500/25 bg-red-500/10 px-1.5 py-0.5 text-[9px] font-bold uppercase text-red-400">{{ __('Deleted') }}</span>
                                        @endif
                                        <span class="mt-0.5 block text-[10px] text-slate-400">{{ $player->email }}</span>
                                    @else
                                        <span class="italic text-slate-400">{{ __('Unknown user') }}</span>
                                    @endif
                                </td>
                                <td class="p-4 font-bold text-emerald-400 tabular-nums">
                                    @currencySymbol{{ number_format((float) $deposit->amount, 2) }}
                                </td>
                                <td class="p-4 text-slate-300 tabular-nums">
                                    <span class="block text-[11px] font-semibold text-slate-200">{{ __('Gross') }}: @currencySymbol{{ $grossFormatted }}</span>
                                    <span class="mt-0.5 block text-[10px] text-slate-400">{{ __('Fee') }}: @currencySymbol{{ number_format((float) $deposit->fee_amount, 2) }}</span>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex rounded border border-indigo-500/25 bg-indigo-500/10 px-2 py-0.5 text-[9px] font-bold uppercase text-indigo-300">
                                        {{ strtoupper((string) $deposit->provider) }}
                                    </span>
                                    <span class="mt-1 block max-w-[220px] truncate font-mono text-[10px] text-slate-400" title="{{ $deposit->provider_reference ?? $deposit->uuid }}">
                                        {{ $deposit->provider_reference ?: $deposit->uuid }}
                                    </span>
                                </td>
                                <td class="p-4 text-slate-300 tabular-nums">
                                    {{ $deposit->created_at?->format('Y-m-d H:i') }}
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex rounded border px-2 py-0.5 text-[9px] font-bold uppercase {{ $statusBadge }}">
                                        {{ str_replace('_', ' ', (string) $deposit->status) }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <button
                                        type="button"
                                        wire:click="selectDeposit({{ $deposit->id }})"
                                        class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-200 transition hover:border-indigo-500/60"
                                    >
                                        {{ __('Details') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400 italic">
                                    {{ __('No deposits found for the selected filters.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            {{ $deposits->links('vendor.livewire.custom-pagination') }}
        </div>
    @elseif($checkouts)
        <div class="overflow-hidden rounded-xl border border-slate-800 bg-[#0f172a] shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-[10px] font-bold uppercase text-slate-400">
                            <th class="p-4">{{ __('Player') }}</th>
                            <th class="p-4">{{ __('Expected Credit') }}</th>
                            <th class="p-4">{{ __('Fee / Total') }}</th>
                            <th class="p-4">{{ __('Provider & Reference') }}</th>
                            <th class="p-4">{{ __('Started At') }}</th>
                            <th class="p-4">{{ __('Status') }}</th>
                            <th class="p-4 text-right">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        @forelse($checkouts as $checkout)
                            @php
                                $player = $checkout->wallet?->user;
                                $checkoutStatus = (string) ($checkout->status ?? 'pending');
                                $checkoutBadge = match ($checkoutStatus) {
                                    'pending' => 'bg-amber-500/10 text-amber-400 border-amber-500/25 animate-pulse',
                                    'expired' => 'bg-slate-700/40 text-slate-300 border-slate-600',
                                    default => 'bg-red-500/10 text-red-400 border-red-500/25',
                                };
                            @endphp
                            <tr class="hover:bg-slate-900/40" wire:key="checkout-{{ $checkout->id }}">
                                <td class="p-4">
                                    @if($player)
                                        <span class="font-semibold text-slate-200">{{ $player->username }}</span>
                                        @if($player->trashed())
                                            <span class="ml-1 inline-flex rounded border border-red-500/25 bg-red-500/10 px-1.5 py-0.5 text-[9px] font-bold uppercase text-red-400">{{ __('Deleted') }}</span>
                                        @endif
                                        <span class="mt-0.5 block text-[10px] text-slate-400">{{ $player->email }}</span>
                                    @else
                                        <span class="italic text-slate-400">{{ __('Unknown user') }}</span>
                                    @endif
                                </td>
                                <td class="p-4 font-bold text-slate-200 tabular-nums">
                                    @currencySymbol{{ number_format((float) $checkout->amount, 2) }}
                                </td>
                                <td class="p-4 text-slate-300 tabular-nums">
                                    <span class="block text-[11px] font-semibold text-slate-200">{{ __('Total') }}: @currencySymbol{{ number_format((float) $checkout->total, 2) }}</span>
                                    <span class="mt-0.5 block text-[10px] text-slate-400">{{ __('Fee') }}: @currencySymbol{{ number_format((float) $checkout->fee_amount, 2) }}</span>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex rounded border border-indigo-500/25 bg-indigo-500/10 px-2 py-0.5 text-[9px] font-bold uppercase text-indigo-300">
                                        {{ strtoupper((string) $checkout->provider) }}
                                    </span>
                                    <span class="mt-1 block max-w-[220px] truncate font-mono text-[10px] text-slate-400" title="{{ $checkout->provider_reference ?? $checkout->id }}">
                                        {{ $checkout->provider_reference ?: $checkout->id }}
                                    </span>
                                </td>
                                <td class="p-4 text-slate-300 tabular-nums">
                                    {{ $checkout->created_at?->format('Y-m-d H:i') }}
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex rounded border px-2 py-0.5 text-[9px] font-bold uppercase {{ $checkoutBadge }}">
                                        {{ str_replace('_', ' ', $checkoutStatus) }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <button
                                        type="button"
                                        wire:click="selectCheckout('{{ $checkout->id }}')"
                                        class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-200 transition hover:border-indigo-500/60"
                                    >
                                        {{ __('Details') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400 italic">
                                    {{ __('No pending or failed checkouts found for the selected filters.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            {{ $checkouts->links('vendor.livewire.custom-pagination') }}
        </div>
    @endif

    {{-- Deposit / Checkout Detail Modal --}}
    @if($showDetailModal && ($selectedDeposit || $selectedCheckout))
        @php
            $isCheckoutModal = $selectedDeposit === null && $selectedCheckout !== null;
            $detailPlayer = $isCheckoutModal ? $selectedCheckout->wallet?->user : $selectedDeposit->wallet?->user;
            if ($isCheckoutModal) {
                $detailGrossFormatted = number_format((float) $selectedCheckout->total, 2, '.', '');
                $detailFeeFormatted = number_format((float) $selectedCheckout->fee_amount, 2, '.', '');
                $detailNetFormatted = number_format((float) $selectedCheckout->amount, 2, '.', '');
            } else {
                $detailNetMinor = \App\Shared\Support\DecimalMoney::toMinor((string) $selectedDeposit->amount);
                $detailFeeMinor = \App\Shared\Support\DecimalMoney::toMinor((string) $selectedDeposit->fee_amount);
                $detailGrossFormatted = \App\Shared\Support\DecimalMoney::format($detailNetMinor + $detailFeeMinor);
                $detailFeeFormatted = number_format((float) $selectedDeposit->fee_amount, 2, '.', '');
                $detailNetFormatted = number_format((float) $selectedDeposit->amount, 2, '.', '');
            }
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="deposit-detail-title">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="closeDetailModal"></div>
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-800 bg-[#0f172a] shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 bg-[#0b0f19] px-6 py-4">
                    <div class="min-w-0">
                        <h3 id="deposit-detail-title" class="text-sm font-bold uppercase tracking-wider text-slate-200">
                            {{ $isCheckoutModal ? __('Checkout Details') : __('Deposit Details') }}
                        </h3>
                        <p class="mt-0.5 truncate font-mono text-[10px] text-slate-400">
                            {{ $isCheckoutModal ? $selectedCheckout->id : $selectedDeposit->uuid }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeDetailModal" class="rounded-lg p-1.5 text-slate-400 transition hover:text-white" aria-label="{{ __('Close') }}">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <div class="space-y-5 overflow-y-auto p-6 text-xs">
                    {{-- Amount Breakdown --}}
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-slate-800 bg-slate-900/60 p-3.5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Gross Charged') }}</p>
                            <p class="mt-1 text-base font-black text-slate-100 tabular-nums">@currencySymbol{{ $detailGrossFormatted }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-800 bg-slate-900/60 p-3.5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Processing Fee') }}</p>
                            <p class="mt-1 text-base font-black text-indigo-300 tabular-nums">@currencySymbol{{ $detailFeeFormatted }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-800 bg-slate-900/60 p-3.5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $isCheckoutModal ? __('Expected Credit') : __('Net Credited') }}</p>
                            <p class="mt-1 text-base font-black text-emerald-400 tabular-nums">@currencySymbol{{ $detailNetFormatted }}</p>
                        </div>
                    </div>

                    {{-- Player & Gateway Metadata --}}
                    <dl class="grid grid-cols-1 gap-3 rounded-lg border border-slate-800 bg-slate-900/40 p-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Player') }}</dt>
                            <dd class="mt-1 font-semibold text-slate-200">
                                @if($detailPlayer)
                                    {{ $detailPlayer->username }}
                                    @if($detailPlayer->trashed())
                                        <span class="ml-1 text-[10px] font-bold uppercase text-red-400">({{ __('Deleted') }})</span>
                                    @endif
                                    <span class="block text-[11px] font-normal text-slate-400">{{ $detailPlayer->email }}</span>
                                @else
                                    <span class="italic text-slate-400">{{ __('Unknown user') }}</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Wallet UUID') }}</dt>
                            <dd class="mt-1 break-all font-mono text-[11px] text-slate-300">
                                {{ $isCheckoutModal ? ($selectedCheckout->wallet?->uuid ?? '—') : ($selectedDeposit->wallet?->uuid ?? '—') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Provider') }}</dt>
                            <dd class="mt-1 font-semibold uppercase text-indigo-300">
                                {{ $isCheckoutModal ? $selectedCheckout->provider : $selectedDeposit->provider }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Status') }}</dt>
                            <dd class="mt-1 font-semibold uppercase text-slate-200">
                                {{ str_replace('_', ' ', (string) ($isCheckoutModal ? ($selectedCheckout->status ?? 'pending') : $selectedDeposit->status)) }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Provider Reference') }}</dt>
                            <dd class="mt-1 break-all font-mono text-[11px] text-slate-300">
                                {{ ($isCheckoutModal ? $selectedCheckout->provider_reference : $selectedDeposit->provider_reference) ?: '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Created At') }}</dt>
                            <dd class="mt-1 text-slate-300 tabular-nums">
                                {{ ($isCheckoutModal ? $selectedCheckout->created_at : $selectedDeposit->created_at)?->format('Y-m-d H:i:s') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $isCheckoutModal ? __('Failed / Expired At') : __('Updated At') }}</dt>
                            <dd class="mt-1 text-slate-300 tabular-nums">
                                {{ $isCheckoutModal ? ($selectedCheckout->failed_at?->format('Y-m-d H:i:s') ?? '—') : ($selectedDeposit->updated_at?->format('Y-m-d H:i:s') ?? '—') }}
                            </dd>
                        </div>
                    </dl>

                    @if(! $isCheckoutModal)
                        {{-- Linked Ledger Entry --}}
                        <div class="rounded-lg border border-slate-800 bg-slate-900/40 p-4">
                            <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Ledger Audit Trail') }}</h4>
                            @if($selectedLedgerEntry)
                                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <div>
                                        <span class="text-slate-400">{{ __('Ledger UUID') }}:</span>
                                        <span class="block break-all font-mono text-[11px] text-slate-200">{{ $selectedLedgerEntry->uuid }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400">{{ __('Running Balance After') }}:</span>
                                        <span class="block font-bold text-emerald-400 tabular-nums">@currencySymbol{{ number_format((float) $selectedLedgerEntry->running_balance, 2) }}</span>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <span class="text-slate-400">{{ __('Description') }}:</span>
                                        <span class="block text-slate-300">{{ $selectedLedgerEntry->description }}</span>
                                    </div>
                                </div>
                            @else
                                <p class="mt-2 italic text-slate-400">{{ __('No ledger credit entry linked to this deposit.') }}</p>
                            @endif
                        </div>

                        {{-- Linked Checkout Telemetry (when present) --}}
                        @if($selectedCheckout)
                            <div class="rounded-lg border border-slate-800 bg-slate-900/40 p-4">
                                <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Payment Checkout Record') }}</h4>
                                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <div>
                                        <span class="text-slate-400">{{ __('Checkout ID') }}:</span>
                                        <span class="block break-all font-mono text-[11px] text-slate-200">{{ $selectedCheckout->id }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400">{{ __('Completed At') }}:</span>
                                        <span class="block text-slate-200 tabular-nums">{{ $selectedCheckout->completed_at?->format('Y-m-d H:i:s') ?? '—' }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="rounded-lg border border-amber-500/25 bg-amber-500/10 p-4 text-amber-200">
                            {{ __('Wallet has not been credited for this checkout yet. Once payment is confirmed by the gateway webhook, it will automatically settle into Completed Deposits.') }}
                        </div>
                    @endif
                </div>

                <div class="flex justify-end border-t border-slate-800 bg-[#0b0f19] px-6 py-3">
                    <button
                        type="button"
                        wire:click="closeDetailModal"
                        class="rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-bold text-slate-200 transition hover:border-indigo-500/60"
                    >
                        {{ __('Close') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
