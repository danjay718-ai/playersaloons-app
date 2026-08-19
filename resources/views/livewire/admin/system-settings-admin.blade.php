<div class="max-w-2xl space-y-6">
    @if(session('success'))
        <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('success') }}</div>
    @endif
    <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
        <p class="text-[11px] font-bold uppercase tracking-wider text-violet-300">Authentication</p>
        <h2 class="mt-1 text-xl font-bold text-white">Sign-in protection</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Temporarily locks repeated failed sign-ins. This avoids permanent account denial-of-service while slowing credential stuffing and brute-force attempts.</p>
        <form wire:submit="saveAuthenticationSettings" class="mt-6 space-y-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="loginMaxAttempts" class="text-xs font-bold uppercase tracking-wider text-slate-400">Failed attempts</label>
                    <input id="loginMaxAttempts" wire:model="loginMaxAttempts" type="number" min="3" max="20" required class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">
                    @error('loginMaxAttempts')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="loginLockoutMinutes" class="text-xs font-bold uppercase tracking-wider text-slate-400">Lockout minutes</label>
                    <input id="loginLockoutMinutes" wire:model="loginLockoutMinutes" type="number" min="1" max="1440" required class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">
                    @error('loginLockoutMinutes')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-violet-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-violet-500">Save authentication settings</button>
        </form>
    </section>
    <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
        <p class="text-[11px] font-bold uppercase tracking-wider text-cyan-300">Tournaments</p>
        <h2 class="mt-1 text-xl font-bold text-white">Result confirmation timeout</h2>
        <p class="mt-2 text-sm text-slate-500">New tournaments inherit this value. Organizers can override it on each tournament.</p>
        <form wire:submit="saveTournamentSettings" class="mt-6 space-y-4">
            <div><label class="text-xs font-bold uppercase tracking-wider text-slate-400">Default minutes</label><input wire:model="defaultWaitingResultTime" type="number" min="1" max="1440" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">@error('defaultWaitingResultTime')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror</div>
            <button type="submit" class="rounded-lg bg-cyan-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-cyan-500">Save tournament settings</button>
        </form>
    </section>
    <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
        <p class="text-[11px] font-bold uppercase tracking-wider text-orange-300">Head-to-Head (H2H)</p>
        <h2 class="mt-1 text-xl font-bold text-white">Platform Commission</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">The percentage deducted from the total prize pool (winner's payout) as the platform fee. For example, if two players stake $10 each, a 10% commission deducts $2 from the $20 pool, paying out $18 to the winner.</p>
        <form wire:submit="saveH2hSettings" class="mt-6 space-y-5">
            <div>
                <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Commission Percentage</label>
                <div class="relative mt-2 w-full sm:w-64">
                    <input wire:model="h2hCommissionPercentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-lg border border-slate-800 bg-slate-900 pl-3 pr-8 py-2 text-white">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400 font-bold">%</div>
                </div>
                @error('h2hCommissionPercentage')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-lg bg-orange-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-orange-500">Save H2H settings</button>
        </form>
    </section>
    <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
        <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-300">Growth</p>
        <h2 class="mt-1 text-xl font-bold text-white">Referral rewards</h2>
        <p class="mt-2 text-sm text-slate-500">Amounts are read when a referred player completes their first successful deposit.</p>
        <form wire:submit="saveReferralSettings" class="mt-6 space-y-5">
            <label class="flex items-center justify-between rounded-lg border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-200">
                Enable referral rewards
                <input wire:model="referralEnabled" type="checkbox" class="rounded border-slate-700 bg-slate-900 text-indigo-500">
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="text-xs font-bold uppercase tracking-wider text-slate-400">Referrer reward</label><input wire:model="referrerReward" type="number" min="0" max="10000" step="0.01" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">@error('referrerReward')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror</div>
                <div><label class="text-xs font-bold uppercase tracking-wider text-slate-400">New-player reward</label><input wire:model="referredReward" type="number" min="0" max="10000" step="0.01" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">@error('referredReward')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror</div>
            </div>
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-indigo-500">Save referral settings</button>
        </form>
    </section>
    <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
        <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-300">Payments</p>
        <h2 class="mt-1 text-xl font-bold text-white">Deposit processing fee</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">This fee is added on top of the player’s requested wallet credit. For example, a $50 credit with a $1 fixed fee and 2% fee charges $52 total, while the wallet receives exactly $50.</p>
        <form wire:submit="saveDepositFeeSettings" class="mt-6 space-y-5">
            <label class="flex items-center justify-between rounded-lg border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-200">Charge deposit processing fees<input wire:model="depositFeeEnabled" type="checkbox" class="rounded border-slate-700 bg-slate-900 text-emerald-500"></label>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="text-xs font-bold uppercase tracking-wider text-slate-400">Fixed fee</label><p class="mt-1 text-xs text-slate-600">A flat amount added to every deposit.</p><input wire:model="depositFeeFixed" type="number" min="0" max="1000" step="0.01" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">@error('depositFeeFixed')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror</div>
                <div><label class="text-xs font-bold uppercase tracking-wider text-slate-400">Percentage fee</label><p class="mt-1 text-xs text-slate-600">A percentage of the requested wallet credit.</p><input wire:model="depositFeePercentage" type="number" min="0" max="100" step="0.01" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">@error('depositFeePercentage')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror</div>
            </div>
            <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-emerald-500">Save deposit fee settings</button>
        </form>
    </section>
    <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
        <p class="text-[11px] font-bold uppercase tracking-wider text-pink-300">Localization</p>
        <h2 class="mt-1 text-xl font-bold text-white">Language Switcher Visibility</h2>
        <p class="mt-2 text-sm text-slate-500">Control where the language switcher is displayed. (It is always visible to players in their dashboard).</p>
        <form wire:submit="saveLanguageSwitcherSettings" class="mt-6 space-y-4">
            <label class="flex items-center justify-between rounded-lg border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-200">
                Show on Guest Pages
                <input wire:model="showLanguageSwitcherGuest" type="checkbox" class="rounded border-slate-700 bg-slate-900 text-pink-500">
            </label>
            <label class="flex items-center justify-between rounded-lg border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-200">
                Show on Admin Pages
                <input wire:model="showLanguageSwitcherAdmin" type="checkbox" class="rounded border-slate-700 bg-slate-900 text-pink-500">
            </label>
            <button type="submit" class="rounded-lg bg-pink-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-pink-500">Save localization settings</button>
        </form>
    </section>
</div>
