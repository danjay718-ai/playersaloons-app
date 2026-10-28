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
