<div class="space-y-6 min-w-0" x-data="{ walletAction: 'deposit' }">
    <x-ui.toasts />

    <section class="rounded-2xl border border-zinc-800 bg-zinc-950/70 p-5 shadow-xl md:p-6">
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full border border-emerald-500/25 bg-emerald-500/10 px-3 py-1 font-orbitron text-[9px] font-black uppercase tracking-widest text-emerald-300">
                        {{ $wallet?->status ?? 'ACTIVE' }}
                    </span>
                    <span class="rounded-full border border-sky-500/25 bg-sky-500/10 px-3 py-1 font-orbitron text-[9px] font-black uppercase tracking-widest text-sky-300">
                        Stripe Sandbox
                    </span>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <p class="font-orbitron text-[10px] font-black uppercase tracking-widest text-zinc-500">Available Balance</p>
                        <div class="mt-2 font-orbitron text-4xl font-black tracking-wide text-white md:text-5xl">
                            ${{ number_format((float)($wallet?->cached_balance ?? 0.00), 2) }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-4">
                        <p class="font-orbitron text-[10px] font-black uppercase tracking-widest text-zinc-500">Payment Mode</p>
                        <div class="mt-3 flex items-center gap-2 text-sm font-bold text-zinc-200">
                            <i data-lucide="flask-conical" class="h-4 w-4 text-sky-300"></i>
                            Test only
                        </div>
                        <p class="mt-2 text-xs leading-relaxed text-zinc-500">
                            No real cards or live funds are used on this wallet screen.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 rounded-xl border border-zinc-800 bg-zinc-950 p-1.5">
                <button type="button"
                    @click="walletAction = 'deposit'"
                    :class="walletAction === 'deposit' ? 'border-emerald-400/40 bg-emerald-500/15 text-emerald-200' : 'border-transparent text-zinc-500 hover:text-zinc-200'"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border px-4 font-orbitron text-[10px] font-black uppercase tracking-widest transition-colors">
                    <i data-lucide="arrow-down-to-line" class="h-4 w-4"></i>
                    Deposit
                </button>
                <button type="button"
                    @click="walletAction = 'withdraw'"
                    :class="walletAction === 'withdraw' ? 'border-fuchsia-400/40 bg-fuchsia-500/15 text-fuchsia-200' : 'border-transparent text-zinc-500 hover:text-zinc-200'"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border px-4 font-orbitron text-[10px] font-black uppercase tracking-widest transition-colors">
                    <i data-lucide="arrow-up-from-line" class="h-4 w-4"></i>
                    Withdraw
                </button>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
        <section class="space-y-6">
            <div x-show="walletAction === 'deposit'" x-cloak class="rounded-2xl border border-zinc-800 bg-zinc-950/70 p-5 shadow-xl md:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-orbitron text-sm font-black uppercase tracking-widest text-white">Deposit</h2>
                        <p class="mt-1 text-xs leading-relaxed text-zinc-500">
                            Enter an amount, then use the test card details below while Stripe Checkout is being wired.
                        </p>
                    </div>
                    <div class="rounded-xl border border-emerald-500/25 bg-emerald-500/10 p-2 text-emerald-300">
                        <i data-lucide="credit-card" class="h-5 w-5"></i>
                    </div>
                </div>

                <form wire:submit.prevent="deposit" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="depositAmount" class="mb-2 block font-orbitron text-[10px] font-black uppercase tracking-widest text-zinc-500">Amount</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 font-orbitron text-sm font-black text-emerald-300">$</span>
                            <input wire:model="depositAmount" id="depositAmount" type="number" step="0.01" min="1" required
                                class="h-12 w-full rounded-xl border border-zinc-800 bg-zinc-900/70 pl-9 pr-4 font-orbitron text-sm font-bold text-white outline-none transition-colors placeholder:text-zinc-700 focus:border-emerald-400/60"
                                placeholder="0.00">
                        </div>
                        @error('depositAmount') <span class="mt-2 block text-xs font-bold text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-4 gap-2">
                        @foreach([10, 25, 50, 100] as $suggestedAmount)
                            <button type="button" @click="$wire.set('depositAmount', '{{ $suggestedAmount }}')"
                                class="h-10 rounded-lg border border-zinc-800 bg-zinc-900/70 font-orbitron text-[10px] font-black text-zinc-300 transition-colors hover:border-emerald-400/40 hover:text-emerald-200">
                                ${{ $suggestedAmount }}
                            </button>
                        @endforeach
                    </div>

                    <button type="submit"
                        class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl border border-emerald-400/30 bg-emerald-500 px-4 font-orbitron text-xs font-black uppercase tracking-widest text-zinc-950 shadow-[0_0_20px_rgba(16,185,129,0.22)] transition-colors hover:bg-emerald-400">
                        <i data-lucide="plus-circle" class="h-4 w-4"></i>
                        Deposit Test Funds
                    </button>
                </form>
            </div>

            <div x-show="walletAction === 'withdraw'" x-cloak class="rounded-2xl border border-zinc-800 bg-zinc-950/70 p-5 shadow-xl md:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-orbitron text-sm font-black uppercase tracking-widest text-white">Withdraw</h2>
                        <p class="mt-1 text-xs leading-relaxed text-zinc-500">
                            Submit a withdrawal request for review. Stripe Connect payout wiring can be added next.
                        </p>
                    </div>
                    <div class="rounded-xl border border-fuchsia-500/25 bg-fuchsia-500/10 p-2 text-fuchsia-300">
                        <i data-lucide="landmark" class="h-5 w-5"></i>
                    </div>
                </div>

                <form wire:submit.prevent="withdraw" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="amount" class="mb-2 block font-orbitron text-[10px] font-black uppercase tracking-widest text-zinc-500">Withdrawal Amount</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 font-orbitron text-sm font-black text-fuchsia-300">$</span>
                            <input wire:model="amount" id="amount" type="number" step="0.01" min="1" required
                                class="h-12 w-full rounded-xl border border-zinc-800 bg-zinc-900/70 pl-9 pr-4 font-orbitron text-sm font-bold text-white outline-none transition-colors placeholder:text-zinc-700 focus:border-fuchsia-400/60"
                                placeholder="0.00">
                        </div>
                        @error('amount') <span class="mt-2 block text-xs font-bold text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-xs leading-relaxed text-amber-200">
                        <div class="flex gap-3">
                            <i data-lucide="shield-check" class="mt-0.5 h-4 w-4 shrink-0"></i>
                            <span>Withdrawals require approved identity verification and finance review before processing.</span>
                        </div>
                    </div>

                    <button type="submit"
                        class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl border border-fuchsia-400/30 bg-fuchsia-500 px-4 font-orbitron text-xs font-black uppercase tracking-widest text-white shadow-[0_0_20px_rgba(217,70,239,0.22)] transition-colors hover:bg-fuchsia-400">
                        <i data-lucide="send" class="h-4 w-4"></i>
                        Request Withdrawal
                    </button>
                </form>
            </div>

            <div class="rounded-2xl border border-sky-500/20 bg-sky-500/10 p-5 shadow-xl md:p-6">
                <div class="flex items-start gap-3">
                    <div class="rounded-xl border border-sky-400/25 bg-sky-400/10 p-2 text-sky-200">
                        <i data-lucide="badge-info" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 class="font-orbitron text-xs font-black uppercase tracking-widest text-sky-100">Stripe Test Payment Information</h3>
                        <p class="mt-2 text-xs leading-relaxed text-sky-100/70">
                            Use these values when the deposit button is connected to Stripe Checkout.
                        </p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    <div class="rounded-xl border border-sky-400/20 bg-zinc-950/70 p-4">
                        <label class="font-orbitron text-[9px] font-black uppercase tracking-widest text-sky-200/70">Card Number</label>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <code class="text-sm font-black tracking-wider text-white">4242 4242 4242 4242</code>
                            <i data-lucide="copy" class="h-4 w-4 text-sky-300"></i>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-sky-400/20 bg-zinc-950/70 p-4">
                            <label class="font-orbitron text-[9px] font-black uppercase tracking-widest text-sky-200/70">Expiry</label>
                            <div class="mt-2 text-sm font-black text-white">12 / 34</div>
                        </div>
                        <div class="rounded-xl border border-sky-400/20 bg-zinc-950/70 p-4">
                            <label class="font-orbitron text-[9px] font-black uppercase tracking-widest text-sky-200/70">CVC</label>
                            <div class="mt-2 text-sm font-black text-white">123</div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-sky-400/20 bg-zinc-950/70 p-4">
                        <label class="font-orbitron text-[9px] font-black uppercase tracking-widest text-sky-200/70">ZIP / Postal Code</label>
                        <div class="mt-2 text-sm font-black text-white">Any valid ZIP, e.g. 10001</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-800 bg-zinc-950/70 p-5 shadow-xl md:p-6">
            <div class="flex flex-col gap-3 border-b border-zinc-800 pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-orbitron text-sm font-black uppercase tracking-widest text-white">Transaction History</h2>
                    <p class="mt-1 text-xs text-zinc-500">Deposits, withdrawals, prizes, and wallet adjustments.</p>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full border border-zinc-800 bg-zinc-900/70 px-3 py-1.5 text-xs font-bold text-zinc-400">
                    <i data-lucide="receipt-text" class="h-4 w-4"></i>
                    Ledger Feed
                </div>
            </div>

            @if($ledgerEntries->count() > 0)
                <div class="mt-5 space-y-3">
                    @foreach($ledgerEntries as $entry)
                        @php
                            $isCredit = (float) $entry->amount >= 0;
                            $entryType = $entry->type->value ?? $entry->type;
                        @endphp
                        <div class="grid gap-3 rounded-xl border border-zinc-800 bg-zinc-900/45 p-4 transition-colors hover:border-zinc-700 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-md border px-2 py-1 font-orbitron text-[9px] font-black uppercase tracking-widest {{ $isCredit ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300' : 'border-red-500/25 bg-red-500/10 text-red-300' }}">
                                        {{ str_replace('_', ' ', (string) $entryType) }}
                                    </span>
                                    <span class="font-mono text-[10px] font-bold text-zinc-600">
                                        {{ $entry->created_at?->format('M d, Y h:i A') }}
                                    </span>
                                </div>
                                <p class="mt-2 truncate text-sm font-semibold text-zinc-300">
                                    {{ $entry->description }}
                                </p>
                            </div>

                            <div class="text-left sm:text-right">
                                <div class="font-orbitron text-base font-black {{ $isCredit ? 'text-emerald-300' : 'text-red-300' }}">
                                    {{ $isCredit ? '+' : '-' }}${{ number_format(abs((float)$entry->amount), 2) }}
                                </div>
                                <div class="mt-1 font-orbitron text-[10px] font-black uppercase tracking-widest text-zinc-600">
                                    Bal ${{ number_format((float)$entry->running_balance, 2) }}
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="pt-3">
                        {{ $ledgerEntries->links() }}
                    </div>
                </div>
            @else
                <div class="mt-5 rounded-xl border border-dashed border-zinc-800 bg-zinc-900/25 px-6 py-14 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-zinc-800 bg-zinc-950 text-zinc-600">
                        <i data-lucide="receipt" class="h-7 w-7"></i>
                    </div>
                    <p class="mt-4 font-orbitron text-xs font-black uppercase tracking-widest text-zinc-400">No transactions yet</p>
                    <p class="mt-2 text-sm text-zinc-600">Your wallet activity will appear here after a deposit or withdrawal request.</p>
                </div>
            @endif
        </section>
    </div>
</div>
