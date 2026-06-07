<div class="space-y-8" x-data="{ walletAction: 'deposit' }">
    <!-- Messages / Error Flash -->
    @if(session()->has('message'))
        <div class="bg-emerald-950/30 border border-emerald-900/50 text-emerald-400 rounded-xl p-4 flex items-center space-x-3 shadow-md shadow-emerald-950/10">
            <i data-lucide="circle-check" class="w-5 h-5 flex-shrink-0"></i>
            <span class="text-sm font-medium">{{ session('message') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-950/30 border border-red-900/50 text-red-400 rounded-xl p-4 flex items-center space-x-3 shadow-md shadow-red-950/10">
            <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
            <span class="text-sm font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Wallet Balance Header Card -->
    <div class="bg-zinc-900 border border-zinc-850 rounded-2xl p-6 md:p-8 shadow-xl relative overflow-hidden">
        <div class="absolute -top-20 -right-20 w-80 h-80 bg-emerald-600/5 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
            <div class="space-y-2">
                <span class="block text-xs font-semibold text-zinc-500 uppercase tracking-wider">AVAILABLE BALANCE</span>
                <h1 class="text-4xl md:text-6xl font-black font-orbitron tracking-wider text-emerald-400">
                    ${{ number_format((float)($wallet?->cached_balance ?? 0.00), 2) }}
                </h1>
                <div class="flex items-center space-x-2 text-xs text-zinc-400 font-semibold">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="uppercase tracking-widest">{{ $wallet?->status ?? 'ACTIVE' }}</span>
                </div>
            </div>

            <!-- Deposit / Withdraw Quick Tabs -->
            <div class="flex items-center bg-zinc-950 border border-zinc-850 p-1 rounded-xl">
                <button @click="walletAction = 'deposit'" :class="walletAction === 'deposit' ? 'bg-zinc-800 text-white' : 'text-zinc-400 hover:text-zinc-200'" class="px-5 py-2 rounded-lg text-xs font-bold transition-all">
                    Deposit
                </button>
                <button @click="walletAction = 'withdraw'" :class="walletAction === 'withdraw' ? 'bg-zinc-800 text-white' : 'text-zinc-400 hover:text-zinc-200'" class="px-5 py-2 rounded-lg text-xs font-bold transition-all">
                    Withdraw
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Action Forms (Deposit or Withdraw) -->
        <div class="lg:col-span-1 space-y-6">
            
            <!-- Deposit Panel -->
            <div x-show="walletAction === 'deposit'" class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 space-y-6">
                <div>
                    <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase">
                        MOCK DEPOSIT
                    </h2>
                    <p class="text-xs text-zinc-500 mt-1">
                        Instantly add funds to your wallet balance for testing entry fees.
                    </p>
                </div>

                <form wire:submit.prevent="deposit" class="space-y-4">
                    @csrf
                    <div>
                        <label for="depositAmount" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-2">Deposit Amount ($)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-zinc-500 font-orbitron text-sm">
                                $
                            </span>
                            <input wire:model="depositAmount" id="depositAmount" type="number" step="0.01" min="1" required
                                class="block w-full pl-9 pr-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-200 placeholder-zinc-650 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200"
                                placeholder="0.00">
                        </div>
                        @error('depositAmount') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Suggestion Buttons -->
                    <div class="grid grid-cols-4 gap-2">
                        @foreach([10, 25, 50, 100] as $sug)
                            <button type="button" @click="$wire.set('depositAmount', '{{ $sug }}')"
                                class="py-1.5 bg-zinc-950 border border-zinc-800 hover:border-zinc-700 text-zinc-300 text-xs font-bold rounded-lg transition-colors">
                                +${{ $sug }}
                            </button>
                        @endforeach
                    </div>

                    <button type="submit" 
                        class="w-full flex justify-center py-2.5 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 transition-all duration-200 shadow-md shadow-emerald-900/20">
                        Deposit Funds
                    </button>
                </form>
            </div>

            <!-- Withdrawal Panel -->
            <div x-show="walletAction === 'withdraw'" class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 space-y-6">
                <div>
                    <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase">
                        WITHDRAW FUNDS
                    </h2>
                    <p class="text-xs text-zinc-500 mt-1">
                        Submit a withdrawal request. Enforces approved KYC verify checks.
                    </p>
                </div>

                <form wire:submit.prevent="withdraw" class="space-y-4">
                    @csrf
                    <div>
                        <label for="amount" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-2">Withdrawal Amount ($)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-zinc-500 font-orbitron text-sm">
                                $
                            </span>
                            <input wire:model="amount" id="amount" type="number" step="0.01" min="1" required
                                class="block w-full pl-9 pr-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-200 placeholder-zinc-650 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200"
                                placeholder="0.00">
                        </div>
                        @error('amount') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="bg-zinc-950 border border-zinc-850 rounded-lg p-3 text-[10px] text-zinc-500 flex items-start space-x-2 leading-relaxed">
                        <i data-lucide="info" class="w-4 h-4 text-violet-400 flex-shrink-0 mt-0.5"></i>
                        <span>Withdrawals require an approved KYC submission. Processing takes 24-48 hours upon admin approval.</span>
                    </div>

                    <button type="submit" 
                        class="w-full flex justify-center py-2.5 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 transition-all duration-200 shadow-md shadow-violet-900/20">
                        Request Withdrawal
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Transaction History Ledger -->
        <div class="lg:col-span-2 bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 space-y-4">
            <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase border-b border-zinc-850 pb-3">
                TRANSACTION HISTORY
            </h2>

            @if($ledgerEntries->count() > 0)
                <div class="space-y-3">
                    @foreach($ledgerEntries as $entry)
                        @php
                            $isCredit = (float)$entry->amount >= 0;
                        @endphp
                        <div class="bg-zinc-950 border border-zinc-850 rounded-xl p-4 flex items-center justify-between gap-4">
                            <div class="truncate">
                                <div class="flex items-center space-x-2">
                                    <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded {{ $isCredit ? 'bg-emerald-950/20 text-emerald-400 border border-emerald-900/30' : 'bg-red-950/20 text-red-400 border border-red-900/30' }}">
                                        {{ str_replace('_', ' ', $entry->type->value ?? $entry->type) }}
                                    </span>
                                    <span class="text-[9px] text-zinc-600 font-semibold">
                                        {{ $entry->created_at?->format('M d, Y h:i A') }}
                                    </span>
                                </div>
                                <span class="block text-xs font-semibold text-zinc-400 mt-2 truncate w-72 md:w-96">
                                    {{ $entry->description }}
                                </span>
                            </div>

                            <div class="text-right">
                                <span class="text-sm font-black font-orbitron block {{ $isCredit ? 'text-emerald-400' : 'text-red-400' }}">
                                    {{ $isCredit ? '+' : '' }}${{ number_format(abs((float)$entry->amount), 2) }}
                                </span>
                                <span class="text-[10px] text-zinc-600 font-medium block mt-0.5">
                                    Bal: ${{ number_format((float)$entry->running_balance, 2) }}
                                </span>
                            </div>
                        </div>
                    @endforeach

                    <!-- Pagination -->
                    <div class="pt-4">
                        {{ $ledgerEntries->links() }}
                    </div>
                </div>
            @else
                <div class="text-center py-16 text-zinc-550">
                    <i data-lucide="receipt" class="w-8 h-8 mx-auto text-zinc-650 mb-3"></i>
                    <p class="text-xs font-semibold">No transactions logged yet.</p>
                </div>
            @endif
        </div>
    </div>
</div>
