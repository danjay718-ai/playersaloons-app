@php
    $settingsGroups = [
        'Workspace' => ['general' => ['General', 'sliders-horizontal'], 'localization' => ['Languages', 'languages'], 'security' => ['Sign-in security', 'shield-check']],
        'Finance' => ['payments' => ['Payment gateways', 'credit-card'], 'fees' => ['Fees & commission', 'percent'], 'referrals' => ['Referral rewards', 'gift']],
        'Operations' => ['competition' => ['Tournament timing', 'timer'], 'notifications' => ['Dispute alerts', 'bell']],
    ];
    if (app()->environment(['local', 'testing']) && auth()->user()?->hasRole('SUPER_ADMIN')) {
        $settingsGroups['Operations']['maintenance'] = ['Test data', 'database'];
    }
    $settingsSections = collect($settingsGroups)->flatMap(fn ($items) => array_keys($items))->values()->all();
    $canManageSettings = auth()->user()?->can('system_settings.manage');
@endphp
<div class="admin-settings" x-data="settingsNavigation(@js($settingsSections))" @popstate.window="restore()">
    <div class="settings-heading">
        <div>
            <p class="settings-eyebrow">{{ __('Administration') }} <span aria-hidden="true">/</span> {{ __('Configuration') }}</p>
            <h1>{{ __('System settings') }}</h1>
            <p>{{ __('Manage payments, platform rules and account security.') }}</p>
        </div>
        <span class="settings-access"><i data-lucide="{{ $canManageSettings ? 'shield-check' : 'eye' }}" aria-hidden="true"></i>{{ $canManageSettings ? __('Management access') : __('View-only access') }}</span>
    </div>

    @if(session('success'))
        <div class="settings-notice" role="status"><i data-lucide="circle-check" aria-hidden="true"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="settings-error-summary" role="alert">{{ __('Settings were not saved. Check the highlighted fields.') }}</div>
    @endif

    <div class="settings-workspace">
        <aside class="settings-navigation">
            <label for="settings-section" class="settings-mobile-label">{{ __('Settings section') }}</label>
            <select id="settings-section" class="settings-mobile-select" :value="section" @change="select($event.target.value)">
                @foreach($settingsGroups as $group => $items)
                    <optgroup label="{{ __($group) }}">
                        @foreach($items as $id => [$label, $icon])<option value="{{ $id }}">{{ __($label) }}</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
            <nav aria-label="{{ __('Settings navigation') }}" class="settings-desktop-nav">
                @foreach($settingsGroups as $group => $items)
                    <div class="settings-nav-group">
                        <p>{{ __($group) }}</p>
                        <ul>
                            @foreach($items as $id => [$label, $icon])
                                <li><a href="{{ route('admin.system-settings', ['section' => $id]) }}" @click.prevent="select(@js($id))" :aria-current="section === @js($id) ? 'page' : null">
                                    <i data-lucide="{{ $icon }}" aria-hidden="true"></i><span>{{ __($label) }}</span>
                                </a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </aside>
        <div class="settings-content">
            <div class="settings-panel-intro" aria-live="polite">
                @foreach($settingsGroups as $items)
                    @foreach($items as $id => [$label, $icon])
                        <div x-show="section === @js($id)" x-cloak><h2>{{ __($label) }}</h2></div>
                    @endforeach
                @endforeach
                <p>{{ __('Changes apply after you save each form.') }}</p>
            </div>
            <section x-show="section === 'payments'" x-cloak data-settings-panel="payments" aria-labelledby="payment-settings-heading" class="settings-payment-panel">
                <div class="settings-card-header">
                    <div><h3 id="payment-settings-heading">{{ __('Payment gateway') }}</h3><p>{{ __('Select the gateway for new deposits. Pending payments continue to use their original gateway.') }}</p></div>
                    <span class="settings-tag">{{ __('Deposits & withdrawals') }}</span>
                </div>
                <form wire:submit="savePaymentSettings" x-data="{ deposit: $wire.entangle('paymentProvider'), payout: $wire.entangle('payoutProvider') }">
                    <fieldset @disabled(! $canManageSettings) class="settings-payment-body">
                        <legend class="sr-only">{{ __('Payment configuration') }}</legend>
                        <div class="settings-subheading"><span class="settings-step">01</span><div><h4>{{ __('Deposit gateway') }}</h4><p>{{ __('Choose where players complete their wallet deposits.') }}</p></div></div>
                        <div class="settings-provider-options" role="group" aria-label="{{ __('Active gateway') }}">
                            @foreach(['payop' => ['PayOp', 'Hosted checkout'], 'stripe' => ['Stripe', 'Card payments']] as $provider => [$name, $description])
                                <label class="settings-provider" :class="{ 'is-selected': deposit === @js($provider) }">
                                    <input type="radio" name="deposit-gateway" value="{{ $provider }}" x-model="deposit">
                                    <span><strong>{{ $name }}</strong><small>{{ __($description) }}</small></span>
                                    <span class="settings-choice" aria-hidden="true"></span>
                                </label>
                            @endforeach
                        </div>
                        @error('paymentProvider')<p role="alert" class="settings-error">{{ $message }}</p>@enderror
                        <label class="settings-toggle-row"><span><strong>{{ __('Accept new deposits') }}</strong><small>{{ __('Turn off to pause new deposits.') }}</small></span><input type="checkbox" wire:model="paymentsEnabled" class="settings-toggle"></label>
                        <div class="settings-credential-grid">
                            @foreach($paymentCredentialFields as $field => $details)
                                @continue($field === 'payop_payout_token')
                                <div wire:key="payment-{{ $field }}" x-show="deposit === @js(str_starts_with($field, 'payop_') ? 'payop' : 'stripe')" x-cloak x-data="{ reveal: false }">
                                    <div class="settings-field-label"><label for="payment-{{ $field }}">{{ __($details['label']) }}</label><span class="settings-credential-status {{ $configuredPaymentCredentials[$details['key']] ? 'is-configured' : '' }}">{{ $configuredPaymentCredentials[$details['key']] ? __('Configured') : __('Not configured') }}</span></div>
                                    <div class="settings-secret"><input id="payment-{{ $field }}" :type="reveal ? 'text' : 'password'" type="password" wire:model="paymentCredentials.{{ $field }}" autocomplete="new-password" spellcheck="false" aria-describedby="credential-help"><button type="button" @click="reveal = !reveal" :aria-pressed="reveal" aria-label="{{ __('Show or hide :credential', ['credential' => __($details['label'])]) }}"><span x-text="reveal ? @js(__('Hide')) : @js(__('Show'))"></span></button></div>
                                    <label class="settings-help"><input type="checkbox" wire:model="resetPaymentCredentials" value="{{ $details['key'] }}">{{ __('Use environment value instead of saved override') }} · {{ $paymentCredentialSources[$details['key']] === 'database' ? __('Saved override') : __('Environment') }}</label>
                                    @error('credentials.'.$details['key'])<p role="alert" class="settings-error">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>
                        <p id="credential-help" class="settings-help"><i data-lucide="lock-keyhole" aria-hidden="true"></i>{{ __('Credentials are encrypted. Leave a field blank to keep its saved value.') }}</p>

                        <div class="settings-divider"></div>
                        <div id="settings-payouts" class="settings-subheading"><span class="settings-step">02</span><div><h4>{{ __('Withdrawal processing') }}</h4><p>{{ __('Choose how approved withdrawals are paid.') }}</p></div></div>
                        <div class="settings-provider-options" role="group" aria-label="{{ __('Withdrawal processing') }}">
                            @foreach(['manual' => ['Manual', 'Finance team pays externally'], 'payop' => ['PayOp', 'Bank transfer (PBB)']] as $provider => [$name, $description])
                                <label class="settings-provider" :class="{ 'is-selected': payout === @js($provider) }">
                                    <input type="radio" name="payout-gateway" value="{{ $provider }}" x-model="payout">
                                    <span><strong>{{ __($name) }}</strong><small>{{ __($description) }}</small></span><span class="settings-choice" aria-hidden="true"></span>
                                </label>
                            @endforeach
                        </div>
                        @error('payoutProvider')<p role="alert" class="settings-error">{{ $message }}</p>@enderror
                        <div x-show="payout === 'payop'" x-cloak class="settings-payout-fields">
                            <div x-data="{ reveal: false }">
                                <div class="settings-field-label"><label for="payment-payop_payout_token">{{ __('PayOp payout JWT token') }}</label><span class="settings-credential-status {{ $configuredPaymentCredentials['payop.payout_token'] ? 'is-configured' : '' }}">{{ $configuredPaymentCredentials['payop.payout_token'] ? __('Configured') : __('Not configured') }}</span></div>
                                <div class="settings-secret"><input id="payment-payop_payout_token" :type="reveal ? 'text' : 'password'" type="password" wire:model="paymentCredentials.payop_payout_token" autocomplete="new-password" spellcheck="false"><button type="button" @click="reveal = !reveal" :aria-pressed="reveal" aria-label="{{ __('Show or hide :credential', ['credential' => __('PayOp payout JWT token')]) }}"><span x-text="reveal ? @js(__('Hide')) : @js(__('Show'))"></span></button></div>
                                <label class="settings-help"><input type="checkbox" wire:model="resetPaymentCredentials" value="payop.payout_token">{{ __('Use environment value instead of saved override') }} · {{ $paymentCredentialSources['payop.payout_token'] === 'database' ? __('Saved override') : __('Environment') }}</label>
                                @error('credentials.payop.payout_token')<p role="alert" class="settings-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <div class="settings-field-label"><label for="payop-payout-certificate">{{ __('PayOp payout certificate') }}</label><span class="settings-credential-status {{ $configuredPaymentCredentials['payop.payout_certificate'] ? 'is-configured' : '' }}">{{ $configuredPaymentCredentials['payop.payout_certificate'] ? __('Configured') : __('Not configured') }}</span></div>
                                <input id="payop-payout-certificate" type="file" wire:model="payopPayoutCertificate" class="settings-file">
                                <p class="settings-help">{{ __('Upload the binary x25519.pub certificate from PayOp Settings → Certificates. Leave empty to keep the saved certificate.') }}</p>
                                <p wire:loading wire:target="payopPayoutCertificate" role="status" class="settings-progress">{{ __('Uploading certificate...') }}</p>
                                <label class="settings-help"><input type="checkbox" wire:model="resetPaymentCredentials" value="payop.payout_certificate">{{ __('Use environment value instead of saved override') }} · {{ $paymentCredentialSources['payop.payout_certificate'] === 'database' ? __('Saved override') : __('Environment') }}</label>
                                @error('payopPayoutCertificate')<p role="alert" class="settings-error">{{ $message }}</p>@enderror
                            </div>
                            <p class="settings-info">{{ __('PayOp bank payouts must be enabled for this merchant and currency. Gateway payout fees are paid from the merchant PayOp balance.') }}</p>
                        </div>
                        <p class="settings-help">{{ __('Applies to new withdrawal requests independently of the deposit gateway. PayOp payouts require finance approval and submission.') }}</p>
                        <div class="settings-divider"></div>
                        <div id="settings-webhooks" class="settings-subheading"><span class="settings-step">03</span><div><h4>{{ __('Webhook endpoints') }}</h4><p>{{ __('Copy these URLs into your payment provider dashboard.') }}</p></div></div>
                        @foreach(['payop.webhook' => ['PayOp · Checkout IPN', "deposit === 'payop'"], 'payop.payout-webhook' => ['PayOp · Withdraw IPN', "payout === 'payop'"], 'stripe.webhook' => ['Stripe · Webhook', "deposit === 'stripe'"]] as $route => [$label, $condition])
                            <div class="settings-endpoint" x-show="{{ $condition }}" x-cloak x-data="{ copied: false, failed: false, async copy() { try { await navigator.clipboard.writeText($refs.endpoint.value); this.copied = true; this.failed = false; } catch { this.failed = true; $refs.endpoint.select(); } } }">
                                <label for="endpoint-{{ $loop->index }}">{{ $label }}</label>
                                <div><input id="endpoint-{{ $loop->index }}" x-ref="endpoint" type="text" readonly value="{{ route($route) }}"><button type="button" @click="copy()" class="settings-copy" :aria-label="copied ? @js(__('Copied')) : @js(__('Copy :endpoint', ['endpoint' => $label]))"><span x-text="copied ? @js(__('Copied')) : @js(__('Copy URL'))"></span></button></div>
                                <p x-show="failed" x-cloak role="status" class="settings-help">{{ __('Copy the selected URL manually.') }}</p>
                            </div>
                        @endforeach
                    </fieldset>
                    <div class="settings-card-footer">
                        <p>{{ __('Configured means saved; verify with a real transaction before launch.') }}</p>
                        @can('system_settings.manage')
                            <button type="submit" wire:loading.attr="disabled" wire:target="savePaymentSettings,payopPayoutCertificate" class="settings-save"><span wire:loading.remove wire:target="savePaymentSettings">{{ __('Save payment settings') }}</span><span wire:loading wire:target="savePaymentSettings">{{ __('Saving...') }}</span></button>
                        @endcan
                    </div>
                </form>
            </section>
            <section x-show="section === 'general'" x-cloak data-settings-panel="general" class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
                <h3 class="text-xl font-bold text-white">{{ __('App timezone') }}</h3>
                <p class="mt-2 text-sm text-slate-500">{{ __('Used across admin and player pages. Changing this does not reschedule existing competitions. New schedules use the selected timezone. Refresh other open pages after saving.') }}</p>
                <form wire:submit="saveAppTimezoneSettings" class="mt-6 space-y-4">
                    <fieldset @disabled(! $canManageSettings) class="settings-form-fields">
                        <label for="app-timezone" class="block text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('App timezone') }}</label>
                        <select id="app-timezone" wire:model="tournamentTimezone" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">
                            @foreach($timezones as $timezone)<option value="{{ $timezone }}">{{ $timezone }}</option>@endforeach
                        </select>
                        @error('tournamentTimezone')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                        <button wire:target="saveAppTimezoneSettings" type="submit" wire:loading.attr="disabled" class="settings-save">{{ __('Save app timezone') }}</button><span wire:loading wire:target="saveAppTimezoneSettings" role="status" class="settings-progress">{{ __('Saving...') }}</span>
                    </fieldset>
                </form>
            </section>
            <section x-show="section === 'general'" x-cloak data-settings-panel="general" class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
                <h3 class="text-xl font-bold text-white">{{ __('App currency') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-400">{{ __('Used for wallets, prizes, entry fees and new payments across admin and player pages. Currency is locked after financial activity starts.') }}</p>
                <form wire:submit="saveCurrencySettings" class="mt-5 space-y-4">
                    <fieldset @disabled(! $canManageSettings) class="settings-form-fields">
                        <label class="block text-xs font-bold text-slate-400" for="currencyCode">{{ __('Currency') }}</label>
                        <select id="currencyCode" wire:model="currencyCode" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">
                            <option value="EUR">{{ __('Euro (EUR €)') }}</option>
                            <option value="GBP">{{ __('British Pound (GBP £)') }}</option>
                            <option value="USD">{{ __('US Dollar (USD $)') }}</option>
                        </select>
                        @error('currencyCode')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                        <button wire:target="saveCurrencySettings" type="submit" wire:loading.attr="disabled" class="settings-save">{{ __('Save currency') }}</button><span wire:loading wire:target="saveCurrencySettings" role="status" class="settings-progress">{{ __('Saving...') }}</span>
                    </fieldset>
                </form>
            </section>
            <section x-show="section === 'security'" x-cloak data-settings-panel="security" class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Authentication') }}</p>
                <h3 class="mt-1 text-xl font-bold text-white">{{ __('Sign-in protection') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('Set the failed sign-in limit and how long accounts wait before trying again.') }}</p>
                <form wire:submit="saveAuthenticationSettings" class="mt-6 space-y-5">
                    <fieldset @disabled(! $canManageSettings) class="settings-form-fields">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="loginMaxAttempts" class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Failed attempts') }}</label>
                                <input id="loginMaxAttempts" wire:model="loginMaxAttempts" type="number" min="3" max="20" required class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">
                                @error('loginMaxAttempts')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="loginLockoutMinutes" class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Lockout minutes') }}</label>
                                <input id="loginLockoutMinutes" wire:model="loginLockoutMinutes" type="number" min="1" max="1440" required class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">
                                @error('loginLockoutMinutes')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <button wire:target="saveAuthenticationSettings" type="submit" wire:loading.attr="disabled" class="settings-save">{{ __('Save authentication settings') }}</button><span wire:loading wire:target="saveAuthenticationSettings" role="status" class="settings-progress">{{ __('Saving...') }}</span>
                    </fieldset>
                </form>
            </section>
            <section x-show="section === 'notifications'" x-cloak data-settings-panel="notifications" class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Notifications') }}</p>
                <h3 class="mt-1 text-xl font-bold text-white">{{ __('Dispute alerts') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('Send tournament and head-to-head dispute alerts to this mailbox.') }}</p>
                <form wire:submit="saveDisputeNotificationSettings" class="mt-6 space-y-5">
                    <fieldset @disabled(! $canManageSettings) class="settings-form-fields">
                        <div>
                            <label for="disputeNotificationEmail" class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Recipient email') }}</label>
                            <input id="disputeNotificationEmail" wire:model="disputeNotificationEmail" type="email" required class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white" placeholder="info@playersaloons.com">
                            @error('disputeNotificationEmail')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="disputeNotificationName" class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Recipient name') }}</label>
                            <input id="disputeNotificationName" wire:model="disputeNotificationName" type="text" maxlength="100" required class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white" placeholder="PlayerSaloons Disputes">
                            @error('disputeNotificationName')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <button wire:target="saveDisputeNotificationSettings" type="submit" wire:loading.attr="disabled" class="settings-save">{{ __('Save dispute notifications') }}</button><span wire:loading wire:target="saveDisputeNotificationSettings" role="status" class="settings-progress">{{ __('Saving...') }}</span>
                    </fieldset>
                </form>
            </section>
            <section x-show="section === 'competition'" x-cloak data-settings-panel="competition" class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Tournaments') }}</p>
                <h3 class="mt-1 text-xl font-bold text-white">{{ __('Result confirmation timeout') }}</h3>
                <p class="mt-2 text-sm text-slate-500">{{ __('New tournaments inherit this value. Organizers can override it on each tournament.') }}</p>
                <form wire:submit="saveTournamentSettings" class="mt-6 space-y-4">
                    <fieldset @disabled(! $canManageSettings) class="settings-form-fields">
                        <div><label for="defaultWaitingResultTime" class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Default result response minutes') }}</label><input id="defaultWaitingResultTime" wire:model="defaultWaitingResultTime" type="number" min="1" max="1440" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">@error('defaultWaitingResultTime')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror</div>

                        <button wire:target="saveTournamentSettings" type="submit" wire:loading.attr="disabled" class="settings-save">{{ __('Save tournament settings') }}</button><span wire:loading wire:target="saveTournamentSettings" role="status" class="settings-progress">{{ __('Saving...') }}</span>
                    </fieldset>
                </form>
            </section>
            <section x-show="section === 'fees'" x-cloak data-settings-panel="fees" class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Payouts') }}</p>
                <h3 class="mt-1 text-xl font-bold text-white">{{ __('Platform commission') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('Deducted from winning head-to-head payouts and tournament prizes. Each deduction appears in the wallet ledger.') }}</p>
                <form wire:submit="savePlatformCommissionSettings" class="mt-6 space-y-5">
                    <fieldset @disabled(! $canManageSettings) class="settings-form-fields">
                        <div>
                            <label for="platformCommissionPercentage" class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Commission Percentage') }}</label>
                            <div class="relative mt-2 w-full sm:w-64">
                                <input id="platformCommissionPercentage" wire:model="platformCommissionPercentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-lg border border-slate-800 bg-slate-900 pl-3 pr-8 py-2 text-white">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400 font-bold">%</div>
                            </div>
                            @error('platformCommissionPercentage')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <button wire:target="savePlatformCommissionSettings" type="submit" wire:loading.attr="disabled" class="settings-save">{{ __('Save platform commission') }}</button><span wire:loading wire:target="savePlatformCommissionSettings" role="status" class="settings-progress">{{ __('Saving...') }}</span>
                    </fieldset>
                </form>
            </section>
            <section x-show="section === 'referrals'" x-cloak data-settings-panel="referrals" class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Growth') }}</p>
                <h3 class="mt-1 text-xl font-bold text-white">{{ __('Referral rewards') }}</h3>
                <p class="mt-2 text-sm text-slate-500">{{ __('Amounts are read when a referred player completes their first successful deposit.') }}</p>
                <form wire:submit="saveReferralSettings" class="mt-6 space-y-5">
                    <fieldset @disabled(! $canManageSettings) class="settings-form-fields">
                        <label class="flex items-center justify-between rounded-lg border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-200">
                            Enable referral rewards
                            <input wire:model="referralEnabled" type="checkbox" class="rounded border-slate-700 bg-slate-900 text-indigo-500">
                        </label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><label for="referrerReward" class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Referrer reward') }}</label><input id="referrerReward" wire:model="referrerReward" type="number" min="0" max="10000" step="0.01" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">@error('referrerReward')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror</div>
                            <div><label for="referredReward" class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('New-player reward') }}</label><input id="referredReward" wire:model="referredReward" type="number" min="0" max="10000" step="0.01" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">@error('referredReward')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror</div>
                        </div>
                        <button wire:target="saveReferralSettings" type="submit" wire:loading.attr="disabled" class="settings-save">{{ __('Save referral settings') }}</button><span wire:loading wire:target="saveReferralSettings" role="status" class="settings-progress">{{ __('Saving...') }}</span>
                    </fieldset>
                </form>
            </section>
            <section x-show="section === 'fees'" x-cloak data-settings-panel="fees" class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Payments') }}</p>
                <h3 class="mt-1 text-xl font-bold text-white">{{ __('Deposit processing fee') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('Added to the payment total. Players receive the full wallet credit they requested.') }}</p>
                <form wire:submit="saveDepositFeeSettings" class="mt-6 space-y-5">
                    <fieldset @disabled(! $canManageSettings) class="settings-form-fields">
                        <label class="flex items-center justify-between rounded-lg border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-200">{{ __('Charge deposit processing fees') }}<input wire:model="depositFeeEnabled" type="checkbox" class="rounded border-slate-700 bg-slate-900 text-emerald-500"></label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><label for="depositFeeFixed" class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Fixed fee') }}</label><p class="mt-1 text-xs text-slate-600">{{ __('A flat amount added to every deposit.') }}</p><input id="depositFeeFixed" wire:model="depositFeeFixed" type="number" min="0" max="1000" step="0.01" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">@error('depositFeeFixed')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror</div>
                            <div><label for="depositFeePercentage" class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Percentage fee') }}</label><p class="mt-1 text-xs text-slate-600">{{ __('A percentage of the requested wallet credit.') }}</p><input id="depositFeePercentage" wire:model="depositFeePercentage" type="number" min="0" max="100" step="0.01" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-white">@error('depositFeePercentage')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror</div>
                        </div>
                        <button wire:target="saveDepositFeeSettings" type="submit" wire:loading.attr="disabled" class="settings-save">{{ __('Save deposit fee settings') }}</button><span wire:loading wire:target="saveDepositFeeSettings" role="status" class="settings-progress">{{ __('Saving...') }}</span>
                    </fieldset>
                </form>
            </section>
            <section x-show="section === 'localization'" x-cloak data-settings-panel="localization" class="rounded-xl border border-slate-800 bg-slate-950/60 p-6">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Localization') }}</p>
                <h3 class="mt-1 text-xl font-bold text-white">{{ __('Language switcher visibility') }}</h3>
                <p class="mt-2 text-sm text-slate-500">{{ __('Choose where the language switcher appears. It is always available in the player dashboard.') }}</p>
                <form wire:submit="saveLanguageSwitcherSettings" class="mt-6 space-y-4">
                    <fieldset @disabled(! $canManageSettings) class="settings-form-fields">
                        <label class="flex items-center justify-between rounded-lg border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-200">
                            Show on Guest Pages
                            <input wire:model="showLanguageSwitcherGuest" type="checkbox" class="rounded border-slate-700 bg-slate-900 text-pink-500">
                        </label>
                        <label class="flex items-center justify-between rounded-lg border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-200">
                            Show on Admin Pages
                            <input wire:model="showLanguageSwitcherAdmin" type="checkbox" class="rounded border-slate-700 bg-slate-900 text-pink-500">
                        </label>
                        <button wire:target="saveLanguageSwitcherSettings" type="submit" wire:loading.attr="disabled" class="settings-save">{{ __('Save localization settings') }}</button><span wire:loading wire:target="saveLanguageSwitcherSettings" role="status" class="settings-progress">{{ __('Saving...') }}</span>
                    </fieldset>
                </form>
            </section>
            @if(app()->environment(['local', 'testing']) && auth()->user()?->hasRole('SUPER_ADMIN'))
                <section x-show="section === 'maintenance'" x-cloak data-settings-panel="maintenance" class="rounded-xl border border-red-500/30 bg-red-950/15 p-6">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Local testing only') }}</p>
                    <h3 class="mt-1 text-xl font-bold text-white">{{ __('Reset tournament test data') }}</h3>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">{{ __('Permanently removes every tournament and H2H occurrence, templates, slots, brackets, registrations, matches, disputes, prize/refund records, and their linked wallet transactions. Remaining wallet balances and XP are rebuilt from non-tournament records. This tool is unavailable in production.') }}</p>
                    <button wire:click="openTournamentTestingReset" type="button" class="mt-5 rounded-lg border border-red-400/50 bg-red-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-red-500">{{ __('Delete all tournament test data') }}</button>
                </section>

                @if($showTournamentResetModal)
                    <div class="fixed inset-0 z-[200] flex items-end bg-black/80 p-0 backdrop-blur-sm sm:items-center sm:justify-center sm:p-5" role="dialog" aria-modal="true" aria-labelledby="tournament-reset-title" x-data x-init="$nextTick(() => $el.querySelector('input').focus())" @keydown.escape.window="$wire.set('showTournamentResetModal', false)">
                        <div class="w-full rounded-t-2xl border border-red-500/30 bg-slate-950 p-6 shadow-2xl sm:max-w-lg sm:rounded-2xl">
                            <p class="text-[11px] font-black uppercase tracking-wider text-slate-400">{{ __('Irreversible local test action') }}</p><h3 id="tournament-reset-title" class="mt-2 text-lg font-black text-white">{{ __('Delete all tournament test data?') }}</h3>
                            <p class="mt-3 text-sm leading-6 text-slate-400">{{ __('This cannot be undone. Type') }} <code class="rounded bg-slate-800 px-1.5 py-0.5 text-red-200">DELETE TOURNAMENT TEST DATA</code> {{ __('to continue.') }}</p>
                            <input wire:model="tournamentResetConfirmation" type="text" autocomplete="off" class="mt-5 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white" aria-label="{{ __('Tournament reset confirmation') }}" placeholder="DELETE TOURNAMENT TEST DATA">
                            @error('tournamentResetConfirmation')<p class="mt-2 text-xs text-red-400">{{ $message }}</p>@enderror
                            <div class="mt-6 flex justify-end gap-3"><button wire:click="$set('showTournamentResetModal', false)" type="button" class="rounded-lg border border-slate-700 px-4 py-2 text-xs font-bold uppercase text-slate-300 hover:bg-slate-800">{{ __('Cancel') }}</button><button wire:click="resetTournamentTestingData" wire:loading.attr="disabled" type="button" class="rounded-lg bg-red-600 px-4 py-2 text-xs font-bold uppercase text-white hover:bg-red-500 disabled:opacity-60">{{ __('Permanently delete') }}</button></div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
