<div class="auth-shell auth-shell-register"
    x-data="{
            username: @entangle('username'),
            fullName: @entangle('full_name'),
            countryCode: @entangle('countryCode'),
            acceptedPolicies: @entangle('accepted_policies'),
            ageConfirmed: @entangle('age_confirmed'),
            get canSubmit() {
                return /^[A-Za-z0-9_-]{3,30}$/.test(this.username)
                    && this.fullName.trim().length >= 2
                    && !/[0-9<>]/.test(this.fullName)
                    && this.countryCode !== ''
                    && this.acceptedPolicies
                    && this.ageConfirmed;
            }
        }">
    <x-auth.player-story :register="true" />
    <section class="auth-panel">
        <div class="auth-panel-heading">
            <h1>{{ __('Finish Google Sign-Up') }}</h1>
            <p>{{ __('Confirm your player identity and eligibility to enter the arena.') }}</p>
        </div>

        <div class="auth-verified-pill" aria-label="{{ __('Verified Google email') }}">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            <span class="auth-verified-email" translate="no">{{ $googleEmail }}</span>
            <span class="auth-verified-badge">{{ __('Verified') }}</span>
        </div>

        <form wire:submit.prevent="complete" class="auth-form">
            @csrf
            <div class="auth-fields-grid">
                <div class="auth-field">
                    <label for="google_username">{{ __('Username') }}</label>
                    <input x-model="username" id="google_username" name="username" type="text" autocomplete="username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_-]+" placeholder="player_name">
                    @error('username')<p class="auth-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="auth-field">
                    <label for="google_full_name">{{ __('Full Legal Name') }}</label>
                    <input x-model="fullName" id="google_full_name" name="full_name" type="text" autocomplete="name" required minlength="2" maxlength="150" placeholder="{{ __('As shown on your ID') }}">
                    <p class="auth-field-hint">{{ __('Used privately for identity verification.') }}</p>
                    @error('full_name')<p class="auth-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="auth-field auth-field-full">
                    <label for="google_countryCode">{{ __('Country') }}</label>
                    <select x-model="countryCode" id="google_countryCode" name="countryCode" required>
                        <option value="">{{ __('Select your country') }}</option>
                        @foreach($countries as $code => $name)
                            <option value="{{ $code }}">{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </select>
                    @error('countryCode')<p class="auth-error" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="auth-consent">
                <label class="auth-check"><input x-model="acceptedPolicies" name="accepted_policies" type="checkbox" required><span>{{ __('I accept the') }} <a href="/policies/cookie-policy" target="_blank" rel="noopener">{{ __('Cookie Policy') }}</a>, <a href="/policies/terms-and-conditions" target="_blank" rel="noopener">{{ __('Terms & Conditions') }}</a> {{ __('and') }} <a href="/policies/privacy-policy" target="_blank" rel="noopener">{{ __('Privacy Policy') }}</a>.</span></label>
                @error('accepted_policies')<p class="auth-error" role="alert">{{ $message }}</p>@enderror
                <label class="auth-check"><input x-model="ageConfirmed" name="age_confirmed" type="checkbox" required><span>{{ __('I am 18 years or older.') }}</span></label>
                @error('age_confirmed')<p class="auth-error" role="alert">{{ $message }}</p>@enderror
                <label class="auth-check"><input wire:model="newsletter_subscribed" name="newsletter_subscribed" type="checkbox"><span>{{ __('Subscribe to newsletters, tournament announcements, and platform updates.') }}</span></label>
            </div>

            <button type="submit" class="guest-button auth-submit" :disabled="!canSubmit" wire:loading.attr="disabled" wire:target="complete">
                <span wire:loading.remove wire:target="complete">{{ __('Complete Account Setup') }}</span>
                <span wire:loading wire:target="complete">{{ __('Creating Account...') }}</span>
                <span aria-hidden="true">↗</span>
            </button>
        </form>
        <p class="auth-switch"><a href="/login" wire:navigate>← {{ __('Back to Sign In') }}</a></p>
    </section>
</div>
