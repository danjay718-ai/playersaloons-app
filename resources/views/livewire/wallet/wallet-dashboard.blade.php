<div class="space-y-6 min-w-0" x-data="{ walletAction: 'deposit' }">
    <x-ui.toasts />

    @if(request('deposit') === 'submitted')
        <p role="status" class="rounded-xl border border-zinc-800 p-4 text-sm text-zinc-300">{{ __('Payment submitted. Your balance updates after payment confirmation.') }}</p>
    @elseif(request('deposit') === 'cancelled')
        <p role="status" class="rounded-xl border border-zinc-800 p-4 text-sm text-zinc-300">{{ __('Payment was cancelled or could not be completed.') }}</p>
    @endif

    @unless($emailVerified)
        <section class="flex flex-col gap-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3"><i data-lucide="mail-warning" class="mt-0.5 h-5 w-5 shrink-0 text-amber-300"></i><p class="text-xs leading-relaxed text-amber-100">Verify your email to unlock withdrawals and email notifications. Deposits and the rest of your player account remain available.</p></div>
            <a href="/profile" wire:navigate class="shrink-0 rounded-lg border border-amber-400/30 bg-zinc-950/50 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-amber-200 hover:bg-amber-500/15">Verify in profile</a>
        </section>
    @endunless

    <section class="rounded-2xl border border-zinc-800 bg-zinc-950/70 p-5 shadow-xl md:p-6">
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full border border-emerald-500/25 bg-emerald-500/10 px-3 py-1 font-orbitron text-[9px] font-black uppercase tracking-widest text-emerald-300">
                        {{ $wallet?->status ?? 'ACTIVE' }}
                    </span>
                    <span class="rounded-full border border-sky-500/25 bg-sky-500/10 px-3 py-1 font-orbitron text-[9px] font-black uppercase tracking-widest text-sky-300">
                        {{ $paymentProvider }}
                    </span>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <p class="font-orbitron text-[10px] font-black uppercase tracking-widest text-zinc-500">Available Balance</p>
                        <div class="mt-2 font-orbitron text-4xl font-black tracking-wide text-white md:text-5xl">
                            @currencySymbol{{ number_format((float)($wallet?->cached_balance ?? 0.00), 2) }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-4">
                        <p class="font-orbitron text-[10px] font-black uppercase tracking-widest text-zinc-500">{{ __('Payment gateway') }}</p>
                        <div class="mt-3 flex items-center gap-2 text-sm font-bold text-zinc-200">
                            <i data-lucide="flask-conical" class="h-4 w-4 text-sky-300"></i>
                            {{ $paymentProvider }}
                        </div>
                        <p class="mt-2 text-xs leading-relaxed text-zinc-500">
                            {{ __('Payments are completed on the gateway checkout page.') }}
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
                            {{ __('Enter an amount to continue to secure checkout.') }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-emerald-500/25 bg-emerald-500/10 p-2 text-emerald-300">
                        <i data-lucide="credit-card" class="h-5 w-5"></i>
                    </div>
                </div>

                @unless($depositsEnabled)
                    <p role="status" class="mt-4 text-sm text-amber-200">{{ __('Deposits are temporarily unavailable. Please try again later.') }}</p>
                @endunless
                <form wire:submit.prevent="deposit" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="depositAmount" class="mb-2 block font-orbitron text-[10px] font-black uppercase tracking-widest text-zinc-500">Amount</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 font-orbitron text-sm font-black text-emerald-300">@currencySymbol</span>
                            <input wire:model.live.debounce.250ms="depositAmount" id="depositAmount" type="number" step="0.01" min="1" required
                                class="h-12 w-full rounded-xl border border-zinc-800 bg-zinc-900/70 pl-9 pr-4 font-orbitron text-sm font-bold text-white outline-none transition-colors placeholder:text-zinc-700 focus:border-emerald-400/60"
                                placeholder="0.00">
                        </div>
                        @error('depositAmount') <span class="mt-2 block text-xs font-bold text-red-400">{{ $message }}</span> @enderror
                    </div>

                    @if((float) $depositBreakdown['credit'] > 0)
                        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-4 text-xs">
                            <div class="flex justify-between text-zinc-400"><span>Wallet credit</span><span class="font-bold text-white">@currencySymbol{{ $depositBreakdown['credit'] }}</span></div>
                            <div class="mt-2 flex justify-between text-zinc-400"><span>Processing fee</span><span class="font-bold text-amber-300">@currencySymbol{{ $depositBreakdown['fee'] }}</span></div>
                            <div class="mt-3 flex justify-between border-t border-zinc-800 pt-3 text-zinc-300"><span class="font-bold">{{ __('Total charge') }}</span><span class="font-black text-emerald-300">@currencySymbol{{ $depositBreakdown['total'] }}</span></div>
                        </div>
                    @endif

                    <div class="grid grid-cols-4 gap-2">
                        @foreach([10, 25, 50, 100] as $suggestedAmount)
                            <button type="button" @click="$wire.set('depositAmount', '{{ $suggestedAmount }}')"
                                class="h-10 rounded-lg border border-zinc-800 bg-zinc-900/70 font-orbitron text-[10px] font-black text-zinc-300 transition-colors hover:border-emerald-400/40 hover:text-emerald-200">
                                @currencySymbol{{ $suggestedAmount }}
                            </button>
                        @endforeach
                    </div>

                    <button type="submit" @disabled(!$depositsEnabled) wire:loading.attr="disabled" wire:target="deposit"
                        class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl border border-emerald-400/30 bg-emerald-500 px-4 font-orbitron text-xs font-black uppercase tracking-widest text-zinc-950 shadow-[0_0_20px_rgba(16,185,129,0.22)] transition-colors hover:bg-emerald-400">
                        <i data-lucide="plus-circle" class="h-4 w-4"></i>
                        {{ __('Continue to checkout') }}
                    </button>
                </form>
            </div>

            <div x-show="walletAction === 'withdraw'" x-cloak class="rounded-2xl border border-zinc-800 bg-zinc-950/70 p-5 shadow-xl md:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-orbitron text-sm font-black uppercase tracking-widest text-white">Withdraw</h2>
                        <p class="mt-1 text-xs leading-relaxed text-zinc-500">
                            {{ __('Submit a withdrawal request for finance review and processing.') }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-fuchsia-500/25 bg-fuchsia-500/10 p-2 text-fuchsia-300">
                        <i data-lucide="landmark" class="h-5 w-5"></i>
                    </div>
                </div>

                @if($emailVerified)
                @unless($withdrawalKycApproved)
                    <div role="alert" class="mt-5 rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-xs leading-relaxed text-amber-100">
                        <p>{{ __('Your identity verification must be approved before you can withdraw. Check your verification status in Profile.') }}</p>
                        <a href="{{ url('/profile') }}" wire:navigate class="mt-2 inline-block font-bold underline">{{ __('Check identity verification') }}</a>
                    </div>
                @endunless
                <form wire:submit.prevent="withdraw" class="mt-6 space-y-4">
                    @csrf
                    @if($payoutProvider === 'payop')
                        <p class="text-sm text-zinc-300">{{ __('Withdrawals are sent by PayOp bank transfer after finance approval. Enter your bank account details.') }}</p>
                        <div>
                            <label for="payout-name" class="mb-2 block text-sm text-zinc-300">{{ __('Bank account holder name') }}</label>
                            <input id="payout-name" wire:model="payoutName" type="text" required maxlength="150" autocomplete="name" class="min-h-11 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                            @error('name')<p role="alert" class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="payout-iban" class="mb-2 block text-sm text-zinc-300">{{ __('Bank account IBAN') }}</label>
                            <input id="payout-iban" wire:model="payoutIban" type="text" required maxlength="50" spellcheck="false" autocomplete="off" class="min-h-11 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                            @error('iban')<p role="alert" class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    <div>
                        <label for="amount" class="mb-2 block text-sm font-semibold text-zinc-300">{{ __('Withdrawal amount') }}</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 font-orbitron text-sm font-black text-fuchsia-300">@currencySymbol</span>
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

                    <button type="submit" @disabled(!$withdrawalKycApproved) wire:loading.attr="disabled" wire:target="withdraw"
                        class="disabled:cursor-not-allowed disabled:opacity-50 inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-cyan-700 px-4 text-sm font-bold text-white transition-colors hover:bg-cyan-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-400">
                        <i data-lucide="send" class="h-4 w-4"></i>
                        Request Withdrawal
                    </button>
                </form>
                @else
                    <div class="mt-6 rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-xs leading-relaxed text-amber-100">Email verification is required before you can request a withdrawal. Send and complete the verification link from your Profile.</div>
                @endif
            </div>

        </section>

        <section class="rounded-2xl border border-zinc-800 bg-zinc-950/70 p-5 shadow-xl md:p-6">
            <div wire:poll.30s>
                <h2 class="text-base font-bold text-white">{{ __('Recent withdrawal requests') }}</h2>
                @forelse($withdrawalRequests as $withdrawalRequest)
                    <div class="mt-3 flex flex-wrap justify-between gap-2 border-b border-zinc-800 pb-3 text-sm">
                        <span class="text-zinc-200">{{ $withdrawalRequest->payout_currency ?? \App\Shared\Support\Currency::code() }} {{ $withdrawalRequest->amount }}</span>
                        <span class="text-zinc-300">{{ __(ucwords(str_replace('_', ' ', $withdrawalRequest->status->value))) }}{{ $withdrawalRequest->payout_status ? ' · '.__('PayOp').': '.__($withdrawalRequest->payout_status) : '' }}</span>
                        @if($withdrawalRequest->payout_status === 'unknown')<p class="w-full text-zinc-400">{{ __('Your payout is awaiting confirmation. Please contact support before making another request.') }}</p>@endif
                    </div>
                @empty
                    <p class="mt-3 text-sm text-zinc-400">{{ __('No withdrawal requests yet.') }}</p>
                @endforelse
            </div>
            <div class="mt-6 flex flex-col gap-3 border-b border-zinc-800 pb-4 sm:flex-row sm:items-center sm:justify-between">
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
                                    {{ $isCredit ? '+' : '-' }}@currencySymbol{{ number_format(abs((float)$entry->amount), 2) }}
                                </div>
                                <div class="mt-1 font-orbitron text-[10px] font-black uppercase tracking-widest text-zinc-600">{{ __('Balance: :amount', ['amount' => \App\Shared\Support\Currency::format($entry->running_balance)]) }}</div>
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
