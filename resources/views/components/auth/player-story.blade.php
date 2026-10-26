@props(['register' => false])
<aside class="auth-story" aria-label="{{ __('GamersRival player arena') }}">
    <img class="auth-story-background" src="/images/guest-highlights.webp" alt="" width="1280" height="720">
    <div class="auth-story-content">
        <img class="auth-story-crest" src="/gamersrival-logo.webp" alt="GamersRival" width="500" height="384">
        <p class="auth-story-title">{{ $register ? __('Your next win starts here.') : __('Your game. Your legacy.') }}</p>
        <p>{{ __('Tournaments, head-to-head duels, and a place to make your name.') }}</p>
    </div>
    <a class="auth-story-link" href="/tournaments">{{ __('Explore the arena') }} <span aria-hidden="true">↗</span></a>
</aside>
