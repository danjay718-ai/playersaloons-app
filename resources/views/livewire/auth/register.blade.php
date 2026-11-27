<div class="auth-shell auth-shell-register"
    x-data="{
            username: @entangle('username'),
            fullName: @entangle('full_name'),
            countryCode: @entangle('countryCode'),
            email: @entangle('email'),
            password: @entangle('password'),
            passwordConfirmation: @entangle('password_confirmation'),
            acceptedPolicies: @entangle('accepted_policies'),
            ageConfirmed: @entangle('age_confirmed'),
            showPassword: false,
            showPasswordConfirmation: false,
            get passwordChecks() {
                return {
                    length: this.password.length >= 8,
                    lower: /[a-z]/.test(this.password),
                    upper: /[A-Z]/.test(this.password),
                    number: /[0-9]/.test(this.password),
                };
            },
            get passwordScore() { return Object.values(this.passwordChecks).filter(Boolean).length; },
            get passwordStrong() { return this.passwordScore === 4; },
            get strengthLabel() {
                if (!this.password) return @js(__('Enter a password'));
                if (this.passwordScore <= 1) return @js(__('Weak'));
                if (this.passwordScore <= 3) return @js(__('Almost there'));
                return @js(__('Strong password'));
            },
            get canSubmit() {
                return /^[A-Za-z0-9_-]{3,30}$/.test(this.username)
                    && this.fullName.trim().length >= 2
                    && !/[0-9<>]/.test(this.fullName)
                    && this.countryCode !== ''
                    && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email)
                    && this.passwordStrong
                    && this.password === this.passwordConfirmation
                    && this.acceptedPolicies
                    && this.ageConfirmed;
            }
        }">
    <x-auth.player-story :register="true" />
    <section class="auth-panel">
        <div class="auth-panel-heading"><h1>{{ __('Create Account') }}</h1><p>{{ __('Choose your player identity. Make it yours.') }}</p></div>
        @if(session('error'))
            <p class="auth-flash-error" role="alert">{{ session('error') }}</p>
        @endif
        @if(config('features.google_auth.enabled'))
            <a href="{{ route('auth.google.redirect', $referrerId ? ['ref' => $referrerId] : []) }}" class="auth-oauth-btn">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <span>{{ __('Continue with Google') }}</span>
            </a>
            <div class="auth-divider" aria-hidden="true"><span>{{ __('or register with email') }}</span></div>
        @endif
        <form wire:submit.prevent="register" class="auth-form">
            @csrf
            <div class="auth-fields-grid">
                <div class="auth-field"><label for="username">{{ __('Username') }}</label><input x-model="username" id="username" name="username" type="text" autocomplete="username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_-]+" placeholder="player_name">@error('username')<p class="auth-error" role="alert">{{ $message }}</p>@enderror</div>
                <div class="auth-field"><label for="full_name">{{ __('Full Legal Name') }}</label><input x-model="fullName" id="full_name" name="full_name" type="text" autocomplete="name" required minlength="2" maxlength="150" placeholder="{{ __('As shown on your ID') }}"><p class="auth-field-hint">{{ __('Used privately for identity verification.') }}</p>@error('full_name')<p class="auth-error" role="alert">{{ $message }}</p>@enderror</div>
                <div class="auth-field"><label for="countryCode">{{ __('Country') }}</label><select x-model="countryCode" id="countryCode" name="countryCode" required><option value="">{{ __('Select your country') }}</option>@foreach($countries as $code => $name)<option value="{{ $code }}">{{ $name }} ({{ $code }})</option>@endforeach</select>@error('countryCode')<p class="auth-error" role="alert">{{ $message }}</p>@enderror</div>
                <div class="auth-field"><label for="email">{{ __('Email Address') }}</label><input x-model="email" id="email" name="email" type="email" autocomplete="email" required maxlength="255" placeholder="you@example.com">@error('email')<p class="auth-error" role="alert">{{ $message }}</p>@enderror</div>
                <div class="auth-field"><label for="password">{{ __('Password') }}</label><div class="auth-password"><input x-model="password" id="password" name="password" type="password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" required minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}" placeholder="{{ __('8+ characters') }}"><button type="button" @click="showPassword = !showPassword" :aria-pressed="showPassword.toString()" x-text="showPassword ? @js(__('Hide')) : @js(__('Show'))">{{ __('Show') }}</button></div>@error('password')<p class="auth-error" role="alert">{{ $message }}</p>@enderror</div>
                <div class="auth-field"><label for="password_confirmation">{{ __('Confirm Password') }}</label><div class="auth-password"><input x-model="passwordConfirmation" id="password_confirmation" name="password_confirmation" type="password" :type="showPasswordConfirmation ? 'text' : 'password'" autocomplete="new-password" required minlength="8" placeholder="{{ __('Repeat password') }}"><button type="button" @click="showPasswordConfirmation = !showPasswordConfirmation" :aria-pressed="showPasswordConfirmation.toString()" x-text="showPasswordConfirmation ? @js(__('Hide')) : @js(__('Show'))">{{ __('Show') }}</button></div><p class="auth-field-hint" x-show="passwordConfirmation" :class="{ 'auth-error': password !== passwordConfirmation }" x-text="password === passwordConfirmation ? @js(__('Passwords match')) : @js(__('Passwords do not match'))" aria-live="polite"></p>@error('password_confirmation')<p class="auth-error" role="alert">{{ $message }}</p>@enderror</div>
            </div>
            <div class="auth-password-rules" aria-label="{{ __('Password requirements') }}">
                <span :class="{ 'is-met': passwordChecks.length }">{{ __('8+ characters') }}</span><span :class="{ 'is-met': passwordChecks.lower }">{{ __('Lowercase') }}</span><span :class="{ 'is-met': passwordChecks.upper }">{{ __('Uppercase') }}</span><span :class="{ 'is-met': passwordChecks.number }">{{ __('Number') }}</span>
            </div>
            <p class="auth-strength" x-text="strengthLabel" aria-live="polite"></p>
            <div class="auth-consent">
                <label class="auth-check"><input x-model="acceptedPolicies" name="accepted_policies" type="checkbox" required><span>{{ __('I accept the') }} <a href="/policies/cookie-policy" target="_blank" rel="noopener">{{ __('Cookie Policy') }}</a>, <a href="/policies/terms-and-conditions" target="_blank" rel="noopener">{{ __('Terms & Conditions') }}</a> {{ __('and') }} <a href="/policies/privacy-policy" target="_blank" rel="noopener">{{ __('Privacy Policy') }}</a>.</span></label>
                @error('accepted_policies')<p class="auth-error" role="alert">{{ $message }}</p>@enderror
                <label class="auth-check"><input x-model="ageConfirmed" name="age_confirmed" type="checkbox" required><span>{{ __('I am 18 years or older.') }}</span></label>
                @error('age_confirmed')<p class="auth-error" role="alert">{{ $message }}</p>@enderror
                <label class="auth-check"><input wire:model="newsletter_subscribed" name="newsletter_subscribed" type="checkbox"><span>{{ __('Subscribe to newsletters, tournament announcements, and platform updates.') }}</span></label>
            </div>
            <button type="submit" class="guest-button auth-submit" :disabled="!canSubmit" wire:loading.attr="disabled" wire:target="register"><span wire:loading.remove wire:target="register">{{ __('Create Account') }}</span><span wire:loading wire:target="register">{{ __('Creating Account...') }}</span><span aria-hidden="true">↗</span></button>
        </form>
        <p class="auth-switch">{{ __('Already have an account?') }} <a href="/login" wire:navigate>{{ __('Sign In') }} →</a></p>
    </section>
</div>
