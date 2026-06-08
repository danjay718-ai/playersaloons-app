<div class="space-y-8 min-w-0" x-data="{ walletAction: 'deposit' }">
    <!-- Messages / Error Flash -->
    @if(session()->has('message'))
        <div class="bg-emerald-950/20 border border-emerald-900/40 text-emerald-400 rounded-xl p-4 flex items-center space-x-3 shadow-[0_0_10px_rgba(16,185,129,0.1)]">
            <i data-lucide="circle-check" class="w-5 h-5 flex-shrink-0 text-emerald-450"></i>
            <span class="text-xs font-semibold uppercase font-orbitron tracking-wider">{{ session('message') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-950/20 border border-red-900/40 text-red-400 rounded-xl p-4 flex items-center space-x-3 shadow-[0_0_10px_rgba(244,63,94,0.1)]">
            <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0 text-red-450"></i>
            <span class="text-xs font-semibold uppercase font-orbitron tracking-wider">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Wallet Balance Header Card -->
    <div class="bg-gradient-to-r from-[#170e30] via-[#0e0a24] to-transparent border border-purple-500/20 rounded-2xl p-6 md:p-8 shadow-[0_10px_30px_rgba(0,0,0,0.5),inset_0_0_20px_rgba(168,85,247,0.05)] relative overflow-hidden">
        <!-- Glowing sci-fi elements -->
        <div class="absolute -top-20 -right-20 w-80 h-80 bg-emerald-500/5 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute top-0 right-0 w-24 h-24 border-t-2 border-r-2 border-purple-500/20 rounded-tr-2xl pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-24 h-24 border-b-2 border-l-2 border-purple-500/20 rounded-bl-2xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
            <div class="space-y-2">
                <span class="block text-[9px] font-bold text-zinc-500 uppercase tracking-widest font-orbitron">AVAILABLE BALANCE</span>
                <h1 class="text-4xl md:text-6xl font-black font-orbitron tracking-wider text-emerald-450 filter drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]">
                    ${{ number_format((float)($wallet?->cached_balance ?? 0.00), 2) }}
                </h1>
                <div class="flex items-center space-x-2 text-[10px] text-zinc-400 font-bold font-orbitron">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span class="uppercase tracking-widest text-emerald-400">{{ $wallet?->status ?? 'ACTIVE' }}</span>
                </div>
            </div>

            <!-- Deposit / Withdraw Quick Tabs -->
            <div class="flex items-center bg-zinc-950/80 border border-purple-500/20 p-1.5 rounded-xl self-start sm:self-center shadow-lg">
                <button @click="walletAction = 'deposit'" :class="walletAction === 'deposit' ? 'bg-purple-650 text-white shadow-[0_0_8px_rgba(168,85,247,0.4)]' : 'text-zinc-500 hover:text-zinc-350'" class="px-5 py-2 rounded-lg text-xs font-bold font-orbitron uppercase tracking-widest transition-all cursor-pointer">
                    Deposit
                </button>
                <button @click="walletAction = 'withdraw'" :class="walletAction === 'withdraw' ? 'bg-purple-650 text-white shadow-[0_0_8px_rgba(168,85,247,0.4)]' : 'text-zinc-500 hover:text-zinc-350'" class="px-5 py-2 rounded-lg text-xs font-bold font-orbitron uppercase tracking-widest transition-all cursor-pointer">
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
            <div x-show="walletAction === 'deposit'" class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 space-y-6 shadow-xl relative overflow-hidden">
                <div class="absolute -top-20 -right-20 w-40 h-40 bg-emerald-600/5 rounded-full blur-2xl pointer-events-none"></div>
                
                <div>
                    <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase">
                        MOCK DEPOSIT
                    </h3>
                    <p class="text-[10px] text-zinc-500 mt-1 font-medium leading-relaxed">
                        Instantly credits test funds into your wallet to facilitate entry fee verification.
                    </p>
                </div>

                <form wire:submit.prevent="deposit" class="space-y-4">
                    @csrf
                    <div>
                        <label for="depositAmount" class="block text-[9px] font-bold text-zinc-500 uppercase tracking-wider font-orbitron mb-2">Deposit Amount ($)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-purple-400 font-orbitron text-xs font-bold">
                                $
                            </span>
                            <input wire:model="depositAmount" id="depositAmount" type="number" step="0.01" min="1" required
                                class="block w-full pl-8 pr-3 py-2.5 bg-zinc-950 border border-purple-500/20 rounded-xl text-xs font-semibold text-purple-300 placeholder-zinc-700 focus:outline-none focus:border-purple-500 transition-all"
                                placeholder="0.00">
                        </div>
                        @error('depositAmount') <span class="text-[10px] text-red-500 mt-1 block font-bold font-mono">{{ $message }}</span> @enderror
                    </div>

                    <!-- Suggestion Buttons -->
                    <div class="grid grid-cols-4 gap-2">
                        @foreach([10, 25, 50, 100] as $sug)
                            <button type="button" @click="$wire.set('depositAmount', '{{ $sug }}')"
                                class="py-2 bg-zinc-950 border border-purple-500/10 hover:border-purple-500/25 text-purple-300 hover:text-white text-[10px] font-bold font-orbitron rounded-lg transition-colors cursor-pointer">
                                +${{ $sug }}
                            </button>
                        @endforeach
                    </div>

                    <button type="submit" 
                        class="w-full flex justify-center py-2.5 px-4 bg-gradient-to-r from-emerald-600 to-teal-500 border border-emerald-450/20 text-xs font-black font-orbitron uppercase tracking-widest text-white rounded-xl shadow-[0_0_15px_rgba(16,185,129,0.35)] transition-all cursor-pointer mt-2">
                        DEPOSIT FUNDS
                    </button>
                </form>
            </div>

            <!-- Withdrawal Panel -->
            <div x-show="walletAction === 'withdraw'" class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 space-y-6 shadow-xl relative overflow-hidden">
                <div class="absolute -top-20 -right-20 w-40 h-40 bg-purple-500/5 rounded-full blur-2xl pointer-events-none"></div>

                <div>
                    <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase">
                        WITHDRAW FUNDS
                    </h3>
                    <p class="text-[10px] text-zinc-500 mt-1 font-medium leading-relaxed">
                        Request withdrawal of ledger funds. Enforces approved identification check status.
                    </p>
                </div>

                <form wire:submit.prevent="withdraw" class="space-y-4">
                    @csrf
                    <div>
                        <label for="amount" class="block text-[9px] font-bold text-zinc-500 uppercase tracking-wider font-orbitron mb-2">Withdrawal Amount ($)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-purple-400 font-orbitron text-xs font-bold">
                                $
                            </span>
                            <input wire:model="amount" id="amount" type="number" step="0.01" min="1" required
                                class="block w-full pl-8 pr-3 py-2.5 bg-zinc-950 border border-purple-500/20 rounded-xl text-xs font-semibold text-purple-300 placeholder-zinc-700 focus:outline-none focus:border-purple-500 transition-all"
                                placeholder="0.00">
                        </div>
                        @error('amount') <span class="text-[10px] text-red-500 mt-1 block font-bold font-mono">{{ $message }}</span> @enderror
                    </div>

                    <div class="bg-zinc-950 border border-purple-500/10 rounded-xl p-3 text-[9px] text-zinc-500 flex items-start space-x-2 leading-relaxed">
                        <i data-lucide="info" class="w-4 h-4 text-purple-400 flex-shrink-0 mt-0.5 animate-pulse"></i>
                        <span>Withdrawals require an approved KYC submission. Requests take 24-48 hours to process.</span>
                    </div>

                    <button type="submit" 
                        class="w-full flex justify-center py-2.5 px-4 bg-gradient-to-r from-purple-600 to-fuchsia-600 border border-fuchsia-450/20 text-xs font-black font-orbitron uppercase tracking-widest text-white rounded-xl shadow-[0_0_15px_rgba(217,70,239,0.35)] transition-all cursor-pointer mt-2">
                        REQUEST WITHDRAWAL
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Transaction History Ledger -->
        <div class="lg:col-span-2 bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 space-y-4 shadow-xl">
            <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase border-b border-purple-500/10 pb-3">
                LEDGER TRANSACTION FEED
            </h3>

            @if($ledgerEntries->count() > 0)
                <div class="space-y-3">
                    @foreach($ledgerEntries as $entry)
                        @php
                            $isCredit = (float)$entry->amount >= 0;
                        @endphp
                        <div class="bg-zinc-950/60 border border-purple-500/10 hover:border-purple-500/20 rounded-xl p-4 flex items-center justify-between gap-4 transition-all duration-200">
                            <div class="truncate">
                                <div class="flex items-center space-x-2">
                                    <span class="text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded font-orbitron border {{ $isCredit ? 'bg-emerald-950/40 text-emerald-450 border-emerald-900/40 shadow-[0_0_6px_rgba(16,185,129,0.1)]' : 'bg-red-950/40 text-red-450 border-red-900/40 shadow-[0_0_6px_rgba(244,63,94,0.1)]' }}">
                                        {{ str_replace('_', ' ', $entry->type->value ?? $entry->type) }}
                                    </span>
                                    <span class="text-[9px] text-zinc-600 font-bold font-mono">
                                        {{ $entry->created_at?->format('M d, Y h:i A') }}
                                    </span>
                                </div>
                                <span class="block text-xs font-bold text-zinc-400 mt-2 truncate w-72 md:w-96">
                                    {{ $entry->description }}
                                </span>
                            </div>

                            <div class="text-right">
                                <span class="text-sm font-black font-orbitron block {{ $isCredit ? 'text-emerald-450' : 'text-red-450' }}">
                                    {{ $isCredit ? '+' : '' }}${{ number_format(abs((float)$entry->amount), 2) }}
                                </span>
                                <span class="text-[9px] text-zinc-650 font-bold font-orbitron block mt-0.5">
                                    BAL: ${{ number_format((float)$entry->running_balance, 2) }}
                                </span>
                            </div>
                        </div>
                    @endforeach

                    <!-- Pagination wrapper with styled pagination links -->
                    <div class="pt-4 font-orbitron">
                        {{ $ledgerEntries->links() }}
                    </div>
                </div>
            @else
                <div class="text-center py-16 text-zinc-550 border border-dashed border-purple-500/10 rounded-xl bg-zinc-950/20">
                    <i data-lucide="receipt" class="w-8 h-8 mx-auto text-zinc-750 mb-3 animate-pulse"></i>
                    <p class="text-xs font-bold font-orbitron uppercase tracking-widest text-zinc-500">No transactions logged yet.</p>
                </div>
            @endif
        </div>
    </div>
</div>
