@php
    $navigation = \App\Modules\CMS\Models\PublicNavigationItem::query()->where('is_active', true)
        ->orderBy('sort_order')->get()->filter(function ($item) {
            $user = auth()->user();
            $staff = $user?->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'FINANCE_OPERATOR', 'KYC_REVIEWER', 'SUPPORT_AGENT', 'TOURNAMENT_ORGANIZER']) ?? false;
            return match ($item->visibility) {
                'guest' => $user === null,
                'auth' => $user !== null,
                'player' => $user !== null && ! $staff,
                'staff' => $staff,
                'guest_or_player' => ! $staff,
                default => true,
            };
        });
    $isHome = request()->is('/');
    $isH2h = request()->is('h2h*');
    $activeIds = $navigation
        ->filter(fn ($item): bool => $item->match_pattern
            ? collect(explode('|', $item->match_pattern))->contains(fn (string $pattern): bool => request()->is(trim($pattern)))
            : url()->current() === url($item->url))
        ->pluck('id')
        ->all();
@endphp
<header class="gr-header" x-data="{ menu: false }" @keydown.escape.window="menu = false">
    <a class="gr-skip" href="#guest-main">{{ __('Skip to content') }}</a>
    <div class="gr-nav gr-wrap">
        <a href="/" class="gr-brand" aria-label="{{ __('GamersRival home') }}"><x-public-brand :mobile-icon-only="true" /></a>
        <nav class="gr-nav-links" aria-label="{{ __('Main navigation') }}">
            <a href="/" class="{{ $isHome ? 'is-active' : '' }}" @if($isHome) aria-current="page" @endif>
                <span class="gr-nav-dot" aria-hidden="true"></span>
                <span>{{ __('Home') }}</span>
            </a>
            @foreach($navigation as $item)
                <a href="{{ $item->url }}" class="{{ in_array($item->id, $activeIds, true) ? 'is-active' : '' }}" @if(in_array($item->id, $activeIds, true)) aria-current="page" @endif @if($item->opens_new_tab) target="_blank" rel="noopener" @endif>
                    <span class="gr-nav-dot" aria-hidden="true"></span>
                    <span>{{ __($item->label) }}</span>
                </a>
            @endforeach
            @if(config('features.tournament_v2.enabled'))
                <a href="{{ route('platform-h2h', ['view' => 'guest']) }}" class="{{ $isH2h ? 'is-active' : '' }}" @if($isH2h) aria-current="page" @endif>
                    <span class="gr-nav-dot" aria-hidden="true"></span>
                    <span>{{ __('Head-to-Head') }}</span>
                </a>
            @endif
        </nav>
        <div class="gr-nav-actions">
            <button type="button" class="pwa-install-btn gr-install" data-pwa-install-desktop><i data-lucide="download" aria-hidden="true"></i><span data-pwa-install-label>{{ __('Install App') }}</span></button>
            <button type="button" class="pwa-install-btn gr-install gr-install-icon hidden" data-pwa-install-mobile><i data-lucide="download" aria-hidden="true"></i><span data-pwa-install-label>{{ __('Install App') }}</span></button>
            @auth
                <x-guest.button :href="route('dashboard')">{{ __('Dashboard') }}</x-guest.button>
            @else
            <a class="gr-link gr-signin {{ request()->is('login') ? 'is-active' : '' }}" href="/login" @if(request()->is('login')) aria-current="page" @endif>{{ __('Sign In') }}</a>
            <x-guest.button href="/register" class="gr-nav-join">{{ __('Join Now') }}</x-guest.button>
            @endauth
            <button class="gr-menu-toggle" type="button" @click="menu = !menu" :aria-expanded="menu.toString()" aria-controls="gr-mobile-nav" aria-label="{{ __('Menu') }}"><span></span><span></span></button>
        </div>
    </div>
    <nav id="gr-mobile-nav" class="gr-mobile-nav gr-wrap" x-show="menu" x-cloak x-transition aria-label="{{ __('Mobile navigation') }}">
        <a href="/" @click="menu = false" class="{{ $isHome ? 'is-active' : '' }}" @if($isHome) aria-current="page" @endif>{{ __('Home') }}</a>
        @foreach($navigation as $item)
            <a href="{{ $item->url }}" @click="menu = false" class="{{ in_array($item->id, $activeIds, true) ? 'is-active' : '' }}" @if(in_array($item->id, $activeIds, true)) aria-current="page" @endif @if($item->opens_new_tab) target="_blank" rel="noopener" @endif>{{ __($item->label) }}</a>
        @endforeach
        @if(config('features.tournament_v2.enabled'))
            <a href="{{ route('platform-h2h', ['view' => 'guest']) }}" @click="menu = false" class="{{ $isH2h ? 'is-active' : '' }}">{{ __('Head-to-Head') }}</a>
        @endif
        @auth<a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>@else<a href="/login">{{ __('Sign In') }}</a>@endauth
        <button type="button" class="pwa-install-btn gr-install" data-pwa-install-mobile><i data-lucide="download" aria-hidden="true"></i><span data-pwa-install-label>{{ __('Install App') }}</span></button>
        <x-localization.language-switcher variant="public" align="left" />
    </nav>
</header>
