<div class="auth-shell auth-shell-login">
    <x-auth.player-story />
    <section class="auth-panel">
        <div class="auth-panel-heading"><h1>{{ __('Sign In') }}</h1><p>{{ __('Welcome back. Your next match is waiting.') }}</p></div>
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
