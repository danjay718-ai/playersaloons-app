@php
    $footer = \App\Modules\CMS\Models\LandingSection::query()->where('key', 'footer')->where('is_active', true)->with('activeItems')->first();
@endphp
<footer class="guest-footer">
    <div class="guest-wrap">
    <div class="guest-footer-top">
        <div><h2>{{ __('Your next match awaits.') }}</h2><p>{{ __('Find your game. Take your place in the bracket.') }}</p></div>
        <a href="/tournaments" class="guest-button">{{ __('Browse Tournaments') }} <span aria-hidden="true">↗</span></a>
    </div>
    <a href="/" class="guest-footer-brand" aria-label="{{ __('GamersRival home') }}"><span>Gamers</span><span>Rival</span></a>
    <div class="guest-footer-bottom">
        <p>&copy; {{ date('Y') }} {{ __($footer?->body ?? 'All rights reserved. Operated by GamersRival Systems.') }}</p>
        <nav aria-label="{{ __('Footer navigation') }}">
            @forelse($footer?->activeItems ?? [] as $item)
                <a href="{{ $item->url ?: route('policies.index') }}">{{ __($item->label ?: $item->title) }}</a>
            @empty
                <a href="{{ route('policies.index') }}">{{ __('Policies') }}</a>
            @endforelse
            <a href="{{ route('contact') }}">{{ __('Contact') }}</a>
        </nav>
        <div class="guest-footer-tools">
            <button type="button" class="pwa-install-btn" data-pwa-install-desktop><span data-pwa-install-label>{{ __('Install App') }}</span></button>
            <x-localization.language-switcher variant="public" align="left" />
        </div>
    </div>
    </div>
</footer>
