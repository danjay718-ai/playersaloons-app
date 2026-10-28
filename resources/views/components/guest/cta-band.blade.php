<section class="gr-cta gr-wrap gr-reveal">
    <div>
        <h2>{{ __('Your seat is open.') }}</h2>
        <p>{{ __('Join the next bracket in under a minute.') }}</p>
    </div>
    <div class="gr-cta-actions">
        <x-guest.button href="/register">{{ __('Join Now') }}</x-guest.button>
        <a class="gr-link" href="/login">{{ __('Sign In') }}</a>
        <button type="button" class="pwa-install-btn gr-install" data-pwa-install-desktop data-pwa-install-mobile><i data-lucide="download" aria-hidden="true"></i><span data-pwa-install-label>{{ __('Install App') }}</span></button>
    </div>
</section>
