<div class="max-w-2xl space-y-6">
    @if(session('success'))
        <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('success') }}</div>
    @endif
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
</div>
