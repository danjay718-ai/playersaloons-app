@php
    $navigation = \App\Modules\CMS\Models\PublicNavigationItem::query()->where('is_active', true)
        ->whereIn('visibility', ['public', 'guest', 'guest_or_player'])->orderBy('sort_order')->get();
@endphp
<header class="guest-header" x-data="{ menu: false }" @keydown.escape.window="menu = false">
    <a class="guest-skip" href="#guest-main">{{ __('Skip to content') }}</a>
    <div class="guest-nav guest-wrap">
        <a href="/" aria-label="{{ __('GamersRival home') }}"><x-public-brand :mobile-icon-only="true" /></a>
        <nav class="guest-desktop-links" aria-label="{{ __('Main navigation') }}">
            @foreach($navigation as $item)
                <a href="{{ $item->url }}" @if($item->opens_new_tab) target="_blank" rel="noopener" @endif>{{ __($item->label) }}</a>
            @endforeach
            @if(config('features.tournament_v2.enabled'))<a href="{{ route('platform-h2h', ['view' => 'guest']) }}">{{ __('Head-to-Head') }}</a>@endif
        </nav>
        <div class="guest-nav-actions">
            <button type="button" class="pwa-install-btn guest-install" data-pwa-install-desktop><i data-lucide="download" aria-hidden="true"></i><span data-pwa-install-label>{{ __('Install App') }}</span></button>
            <button type="button" class="pwa-install-btn guest-install guest-install-icon hidden" data-pwa-install-mobile><i data-lucide="download" aria-hidden="true"></i><span data-pwa-install-label>{{ __('Install App') }}</span></button>
            <a href="/login">{{ __('Sign In') }}</a>
            <a href="/register" class="guest-button">{{ __('Join Now') }} <span aria-hidden="true">↗</span></a>
            <button class="guest-menu-toggle" type="button" @click="menu = !menu" :aria-expanded="menu.toString()" aria-controls="guest-mobile-nav">{{ __('Menu') }}</button>
        </div>
    </div>
    <nav id="guest-mobile-nav" class="guest-mobile-links guest-wrap" x-show="menu" x-cloak aria-label="{{ __('Mobile navigation') }}">
        <a href="/login">{{ __('Sign In') }}</a>
        @foreach($navigation as $item)
            <a href="{{ $item->url }}" @click="menu = false" @if($item->opens_new_tab) target="_blank" rel="noopener" @endif>{{ __($item->label) }}</a>
        @endforeach
        @if(config('features.tournament_v2.enabled'))<a href="{{ route('platform-h2h', ['view' => 'guest']) }}">{{ __('Head-to-Head') }}</a>@endif
        <button type="button" class="pwa-install-btn guest-install" data-pwa-install-mobile><i data-lucide="download" aria-hidden="true"></i><span data-pwa-install-label>{{ __('Install App') }}</span></button>
        <x-localization.language-switcher variant="public" align="left" />
    </nav>
    <div class="guest-livebar guest-wrap"><livewire:landing.guest-pulse /><a class="guest-livebar-link" href="/tournaments">{{ __('Explore the arena') }} ↗</a></div>
</header>
