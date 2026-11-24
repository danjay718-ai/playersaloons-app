<div class="auth-shell auth-shell-login">
    <x-auth.player-story />
    <section class="auth-panel">
        <div class="auth-panel-heading"><h1>{{ __('Sign In') }}</h1><p>{{ __('Welcome back. Your next match is waiting.') }}</p></div>
        @if(session('error'))
            <p class="auth-flash-error" role="alert">{{ session('error') }}</p>
        @endif
        @if(config('features.google_auth.enabled'))
            <a href="{{ route('auth.google.redirect') }}" class="auth-oauth-btn">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <span>{{ __('Continue with Google') }}</span>
            </a>
            <div class="auth-divider" aria-hidden="true"><span>{{ __('or sign in with email') }}</span></div>
        @endif
        <form wire:submit.prevent="login" class="auth-form">
            @csrf
            <div class="auth-field">
                <label for="identity">{{ __('Username or Email') }}</label>
                <input wire:model="identity" id="identity" name="identity" type="text" autocomplete="username" maxlength="255" required placeholder="{{ __('you@example.com or username') }}" @error('identity') aria-invalid="true" aria-describedby="identity-error" @enderror>
                @error('identity')<p class="auth-error" id="identity-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="auth-field" x-data="{ show: false }">
                <div class="auth-label-row"><label for="password">{{ __('Password') }}</label><a href="/reset-password" wire:navigate>{{ __('Forgot Password?') }}</a></div>
                <div class="auth-password"><input wire:model="password" id="password" name="password" type="password" :type="show ? 'text' : 'password'" autocomplete="current-password" minlength="1" required placeholder="{{ __('Enter your password') }}">
                    <button type="button" @click="show = !show" :aria-pressed="show.toString()" x-text="show ? @js(__('Hide')) : @js(__('Show'))">{{ __('Show') }}</button>
                </div>
                @error('password')<p class="auth-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <label class="auth-check"><input wire:model="remember" id="remember" name="remember" type="checkbox"><span>{{ __('Remember me on this device') }}</span></label>
            <button type="submit" class="guest-button auth-submit" wire:loading.attr="disabled" wire:target="login">
                <span wire:loading.remove wire:target="login">{{ __('Sign In to Play') }}</span><span wire:loading wire:target="login">{{ __('Signing In...') }}</span><span aria-hidden="true">↗</span>
            </button>
            <p class="auth-security-note">{{ __('Repeated failed attempts trigger a temporary security lock. Password reset remains available above.') }}</p>
        </form>
        <p class="auth-switch">{{ __('New to GamersRival?') }} <a href="/register" wire:navigate>{{ __('Create Account') }} →</a></p>
    </section>
</div>
