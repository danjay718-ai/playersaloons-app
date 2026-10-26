<div class="max-w-2xl space-y-6">
    @if(session('success'))
        <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('success') }}</div>
    @endif
    <section aria-labelledby="payment-settings-heading" class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
        <h2 id="payment-settings-heading" class="text-xl font-bold text-white">{{ __('Payment gateway') }}</h2>
        <p class="mt-2 text-sm text-slate-400">{{ __('Select the gateway for new deposits. Pending payments continue to use their original gateway.') }}</p>
        <form wire:submit="savePaymentSettings" class="mt-5 space-y-4">
            <div>
                <label for="payment-provider" class="mb-2 block text-sm text-slate-300">{{ __('Active gateway') }}</label>
                <select id="payment-provider" wire:model="paymentProvider" class="min-h-11 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">
                    <option value="stripe">Stripe</option>
                    <option value="payop">PayOp</option>
                </select>
                @error('paymentProvider')<p role="alert" class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <label class="flex min-h-11 items-center gap-3 text-sm text-slate-300">
                <input type="checkbox" wire:model="paymentsEnabled" class="rounded border-slate-700">
                {{ __('Accept new deposits') }}
            </label>
            <p class="text-sm text-slate-400">{{ __('Credentials are encrypted. Leave a field blank to keep its saved value.') }}</p>
            @foreach($paymentCredentialFields as $field => $details)
                <div wire:key="payment-{{ $field }}">
                    <label for="payment-{{ $field }}" class="mb-2 block text-sm text-slate-300">{{ __($details['label']) }} <span class="text-slate-400">· {{ $configuredPaymentCredentials[$details['key']] ? __('Configured') : __('Not configured') }}</span></label>
                    <input id="payment-{{ $field }}" type="password" wire:model="paymentCredentials.{{ $field }}" autocomplete="new-password" spellcheck="false" class="min-h-11 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">
                    @error('credentials.'.$details['key'])<p role="alert" class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <div class="space-y-2 border-t border-slate-800 pt-4 text-sm text-slate-400">
                <p>{{ __('Set the Checkout IPN URL in your PayOp project to:') }}</p>
                <code class="block break-all text-slate-200">{{ route('payop.webhook') }}</code>
                <p>{{ __('Stripe webhook URL:') }}</p>
                <code class="block break-all text-slate-200">{{ route('stripe.webhook') }}</code>
                <p>{{ __('Use credentials supplied by your gateway. Live credentials can charge real funds.') }}</p>
            </div>
            @can('system_settings.manage')
                <button type="submit" wire:loading.attr="disabled" wire:target="savePaymentSettings" class="min-h-11 rounded-lg bg-cyan-700 px-5 py-3 text-sm font-bold text-white disabled:opacity-50">{{ __('Save payment settings') }}</button>
            @endcan
        </form>
    </section>
    <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
        <h2 class="text-xl font-bold text-white">{{ __('App timezone') }}</h2>
        <p class="mt-2 text-sm text-slate-500">{{ __('Used across admin and player pages. Changing this does not reschedule existing competitions. New schedules use the selected timezone. Refresh other open pages after saving.') }}</p>
        <form wire:submit="saveAppTimezoneSettings" class="mt-6 space-y-4">
            <label for="app-timezone" class="block text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('App timezone') }}</label>
            <select id="app-timezone" wire:model="tournamentTimezone" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">
                @foreach($timezones as $timezone)<option value="{{ $timezone }}">{{ $timezone }}</option>@endforeach
            </select>
            @error('tournamentTimezone')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
            <button type="submit" class="rounded-lg bg-cyan-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white">{{ __('Save app timezone') }}</button>
        </form>
    </section>
    <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
        <h2 class="text-xl font-bold text-white">{{ __('App currency') }}</h2>
        <p class="mt-2 text-sm leading-6 text-slate-400">{{ __('Used for wallets, prizes, entry fees and new payments across admin and player pages. Existing amounts are not converted.') }}</p>
        <form wire:submit="saveCurrencySettings" class="mt-5 space-y-4">
            <label class="block text-xs font-bold text-slate-400" for="currencyCode">{{ __('Currency') }}</label>
            <select id="currencyCode" wire:model="currencyCode" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">
                <option value="EUR">{{ __('Euro (EUR €)') }}</option>
                <option value="GBP">{{ __('British Pound (GBP £)') }}</option>
                <option value="USD">{{ __('US Dollar (USD $)') }}</option>
            </select>
            @error('currencyCode')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-3 text-xs font-bold text-white hover:bg-indigo-500">{{ __('Save currency') }}</button>
        </form>
    </section>
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
        <p class="text-[11px] font-bold uppercase tracking-wider text-red-300">Notifications</p>
        <h2 class="mt-1 text-xl font-bold text-white">Dispute alerts</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Tournament and head-to-head dispute alerts are sent to this mailbox. The email button opens the exact disputed match for administrator review.</p>
        <form wire:submit="saveDisputeNotificationSettings" class="mt-6 space-y-5">
            <div>
                <label for="disputeNotificationEmail" class="text-xs font-bold uppercase tracking-wider text-slate-400">Recipient email</label>
                <input id="disputeNotificationEmail" wire:model="disputeNotificationEmail" type="email" required class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white" placeholder="info@playersaloons.com">
                @error('disputeNotificationEmail')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="disputeNotificationName" class="text-xs font-bold uppercase tracking-wider text-slate-400">Recipient name</label>
                <input id="disputeNotificationName" wire:model="disputeNotificationName" type="text" maxlength="100" required class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white" placeholder="PlayerSaloons Disputes">
                @error('disputeNotificationName')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-lg bg-red-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-red-500">Save dispute notifications</button>
        </form>
    </section>
    <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
        <p class="text-[11px] font-bold uppercase tracking-wider text-cyan-300">Tournaments</p>
        <h2 class="mt-1 text-xl font-bold text-white">Result confirmation timeout</h2>
        <p class="mt-2 text-sm text-slate-500">New tournaments inherit this value. Organizers can override it on each tournament.</p>
        <form wire:submit="saveTournamentSettings" class="mt-6 space-y-4">
            <div><label class="text-xs font-bold uppercase tracking-wider text-slate-400">Default result response minutes</label><input wire:model="defaultWaitingResultTime" type="number" min="1" max="1440" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">@error('defaultWaitingResultTime')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror</div>

            <button type="submit" class="rounded-lg bg-cyan-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-cyan-500">Save tournament settings</button>
        </form>
    </section>
    <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
        <p class="text-[11px] font-bold uppercase tracking-wider text-orange-300">Payouts</p>
        <h2 class="mt-1 text-xl font-bold text-white">Platform Commission</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Applied to every winning Head-to-Head payout and tournament prize. For example, a @money(100) award with a 10% commission pays @money(90) to the player and records the @money(10) deduction as a platform commission in their wallet history.</p>
        <form wire:submit="savePlatformCommissionSettings" class="mt-6 space-y-5">
            <div>
                <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Commission Percentage</label>
                <div class="relative mt-2 w-full sm:w-64">
                    <input wire:model="platformCommissionPercentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-lg border border-slate-800 bg-slate-900 pl-3 pr-8 py-2 text-white">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400 font-bold">%</div>
                </div>
                @error('platformCommissionPercentage')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-lg bg-orange-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-orange-500">Save platform commission</button>
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
        <p class="mt-2 text-sm leading-6 text-slate-500">This fee is added on top of the player’s requested wallet credit. For example, a @money(50) credit with a @money(1) fixed fee and 2% fee charges @money(52) total, while the wallet receives exactly @money(50).</p>
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
    @if(app()->environment(['local', 'testing']) && auth()->user()?->hasRole('SUPER_ADMIN'))
        <section class="rounded-xl border border-red-500/30 bg-red-950/15 p-6">
            <p class="text-[11px] font-bold uppercase tracking-wider text-red-300">Local testing only</p>
            <h2 class="mt-1 text-xl font-bold text-white">Reset tournament test data</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">Permanently removes every tournament and H2H occurrence, templates, slots, brackets, registrations, matches, disputes, prize/refund records, and their linked wallet transactions. Remaining wallet balances and XP are rebuilt from non-tournament records. This tool is unavailable in production.</p>
            <button wire:click="openTournamentTestingReset" type="button" class="mt-5 rounded-lg border border-red-400/50 bg-red-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-red-500">Delete all tournament test data</button>
        </section>

        @if($showTournamentResetModal)
            <div class="fixed inset-0 z-[200] flex items-end bg-black/80 p-0 backdrop-blur-sm sm:items-center sm:justify-center sm:p-5" role="dialog" aria-modal="true">
                <div class="w-full rounded-t-2xl border border-red-500/30 bg-slate-950 p-6 shadow-2xl sm:max-w-lg sm:rounded-2xl">
                    <p class="text-[11px] font-black uppercase tracking-wider text-red-300">Irreversible local test action</p><h3 class="mt-2 text-lg font-black text-white">Delete all tournament test data?</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-400">This cannot be undone. Type <code class="rounded bg-slate-800 px-1.5 py-0.5 text-red-200">DELETE TOURNAMENT TEST DATA</code> to continue.</p>
                    <input wire:model="tournamentResetConfirmation" type="text" autocomplete="off" class="mt-5 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white" placeholder="DELETE TOURNAMENT TEST DATA">
                    @error('tournamentResetConfirmation')<p class="mt-2 text-xs text-red-400">{{ $message }}</p>@enderror
                    <div class="mt-6 flex justify-end gap-3"><button wire:click="$set('showTournamentResetModal', false)" type="button" class="rounded-lg border border-slate-700 px-4 py-2 text-xs font-bold uppercase text-slate-300 hover:bg-slate-800">Cancel</button><button wire:click="resetTournamentTestingData" wire:loading.attr="disabled" type="button" class="rounded-lg bg-red-600 px-4 py-2 text-xs font-bold uppercase text-white hover:bg-red-500 disabled:opacity-60">Permanently delete</button></div>
                </div>
            </div>
        @endif
    @endif
</div>
