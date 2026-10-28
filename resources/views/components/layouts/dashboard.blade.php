@php
    $chatInboxEnabled = auth()->user()->hasAnyRole(['PLAYER', 'TEAM_CAPTAIN']);
    $chatInboxInitial = $chatInboxEnabled ? app(\App\Modules\Community\Services\ChatInboxService::class)->summary(auth()->user()) : ['unread_count' => 0, 'conversations' => []];
    $accountTheme = auth()->user()?->theme ?? \App\Shared\Enums\UserTheme::default();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $accountTheme->value }}">
<head>
    <x-brand-icons />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? __('Gamer Terminal | GamersRival') }}</title>

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="{{ $accountTheme->metaColor() }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <x-site-fonts />

    @auth
        <meta name="user-uuid" content="{{ auth()->user()->uuid }}">
    @endauth

    <!-- Tailwind CSS & Fonts -->
    <x-localization.interface-messages />
    <x-currency-config />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css" />
</head>
<body x-data="chatInbox(@js(['enabled' => $chatInboxEnabled, 'url' => route('chat.inbox'), 'userUuid' => auth()->user()->uuid, 'initial' => $chatInboxInitial]))" class="theme-auth theme-player bg-[#05080f] text-zinc-100 min-h-screen font-sans antialiased overflow-x-hidden selection:bg-[#c6ff3d] selection:text-[#05080f] relative cyber-grid">
    @php
        $bottomNavItems = [
            ['label' => __('Overview'), 'icon' => 'layout-dashboard', 'url' => '/dashboard', 'pattern' => 'dashboard'],
            ['label' => __('Tournaments'), 'icon' => 'calendar-days', 'url' => '/tournaments/browse?frequency=daily', 'pattern' => 'tournaments/browse*'],
            ['label' => __('My Games'), 'icon' => 'trophy', 'url' => '/my-tournaments', 'pattern' => 'my-tournaments'],
            ['label' => __('More'), 'icon' => 'grid-3x3', 'url' => null, 'pattern' => null],
        ];
        if (config('features.tournament_v2.enabled')) {
            array_splice($bottomNavItems, 2, 0, [[
                'label' => __('Head-to-Head'), 'icon' => 'swords', 'url' => '/h2h', 'pattern' => 'h2h',
            ]]);
        }
    @endphp
    <div x-show="chatAlert" x-cloak role="status" class="fixed right-4 top-20 z-[100] flex max-w-sm items-center gap-3 rounded-xl border border-cyan-400/40 bg-[#0a1220] p-4 shadow-[0_14px_35px_rgba(0,0,0,0.75)]">
        <a :href="chatAlert?.url" wire:navigate class="min-w-0 text-sm text-zinc-100"><strong>{{ __('New direct message') }}</strong><span class="mt-1 block break-words text-xs text-zinc-400" x-text="chatAlert?.sender"></span></a>
        <button type="button" @click="chatAlert = null" aria-label="{{ __('Dismiss notification') }}" class="px-2 text-zinc-400 hover:text-white">×</button>
    </div>

    <!-- Global Background FX (Neon Arena) -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute top-[-12%] right-[-10%] w-[45%] h-[45%] bg-cyan-500/8 rounded-full blur-[150px]"></div>
        <div class="absolute bottom-[-12%] left-[-10%] w-[42%] h-[42%] bg-lime-400/6 rounded-full blur-[150px]"></div>
    </div>

    @if(config('features.player_wager.enabled'))
        <livewire:match.head-to-head-duel-prompt />
    @endif

    <!-- ─────────────────────────────────────
         MOBILE BOTTOM NAV — "More" Backdrop
    ───────────────────────────────────────── -->
    <div id="mobile-more-backdrop"></div>

    <!-- ─────────────────────────────────────
         MOBILE BOTTOM NAV — "More" Slide-Up Panel
    ───────────────────────────────────────── -->
    <div id="mobile-more-panel">
        <!-- Drag handle -->
        <div class="more-panel-handle"></div>

        @php
            $moreItems = [
                ['label' => __('Weekly Tournaments'), 'icon' => 'calendar-range', 'url' => '/tournaments/browse?frequency=weekly', 'pattern' => null],
                ['label' => __('Monthly Tournaments'), 'icon' => 'calendar-fold', 'url' => '/tournaments/browse?frequency=monthly', 'pattern' => null],
                ['label' => __('Leaderboard'), 'icon' => 'award',          'url' => '/leaderboards',    'pattern' => 'leaderboards'],
                ['label' => __('Streams'),     'icon' => 'tv',              'url' => '/streams',         'pattern' => 'streams'],
                ['label' => __('Chat'),        'icon' => 'message-square',  'url' => '/chat',            'pattern' => 'chat'],
                ['label' => __('Squads'),      'icon' => 'users',           'url' => '/teams',           'pattern' => 'teams'],
                ['label' => __('Wallet'),      'icon' => 'wallet',          'url' => '/wallet',          'pattern' => 'wallet'],
                ['label' => __('Profile'),     'icon' => 'user-round',      'url' => '/profile',         'pattern' => 'profile'],
                ['label' => __('Support'),     'icon' => 'headphones',      'url' => '/contact',         'pattern' => 'contact'],
                ['label' => __('Review Us'),   'icon' => 'star',            'url' => '/reviews',         'pattern' => 'reviews'],
            ];
            if (config('features.team_finder.enabled')) {
                array_splice($moreItems, 4, 0, [[
                    'label' => __('Find Team'), 'icon' => 'user-search', 'url' => '/teams#find-team', 'pattern' => 'teams/find-team',
                ]]);
            }
            if(auth()->user()?->hasAnyRole(['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER'])) {
                $moreItems[] = ['label' => __('Admin'), 'icon' => 'shield', 'url' => '/admin', 'pattern' => 'admin*'];
            }
        @endphp

        <div class="mx-4 mb-3"><x-app-clock /></div>

        <!-- Grid of more items -->
        <div class="more-panel-grid">
            @foreach($moreItems as $item)
                <a href="{{ $item['url'] }}" wire:navigate
                   class="more-panel-item {{ request()->is($item['pattern']) ? 'active' : '' }}"
                   id="more-item-{{ $loop->index }}">
                    <i data-lucide="{{ $item['icon'] }}" class="more-item-icon"></i>
                    <span class="more-item-label">{{ $item['label'] }}</span>
                    @if($item['url'] === '/chat')<x-chat-unread-badge />@endif
                </a>
            @endforeach
        </div>

        <div class="mx-4 mb-2 rounded-xl border border-cyan-500/15 bg-black/20">
            <livewire:identity.theme-switcher variant="menu" key="player-mobile-theme" />
        </div>

        <div class="px-4 py-2">
            <x-localization.language-switcher variant="player" align="left" class="w-full" />
        </div>

        <!-- Logout -->
        <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="button" onclick="document.getElementById('logout-confirmation')?.showModal()" class="more-panel-logout">
                <i data-lucide="log-out" class="more-panel-logout-icon"></i>
                {{ __('Logout') }}
            </button>
        </form>
    </div>

    <!-- Main Outer Container -->
    <div class="player-dashboard-shell relative z-10 flex min-h-screen w-full"
         x-data="{
             sidebarExpanded: true,
             sidebarTooltip: null,
             showSidebarTooltip(target) {
                 const item = target.closest('[data-sidebar-tooltip]');
                 if (this.sidebarExpanded || !item) {
                     this.sidebarTooltip = null;
                     return;
                 }
                 const rect = item.getBoundingClientRect();
                 this.sidebarTooltip = {
                     label: item.dataset.sidebarTooltip,
                     top: rect.top + rect.height / 2,
                     left: this.$refs.sidebar.getBoundingClientRect().right + 12,
                 };
             }
         }"
         data-sidebar-expanded="true"
         :data-sidebar-expanded="sidebarExpanded ? 'true' : 'false'">

        <!-- Desktop Sidebar Panel -->
        <aside id="desktop-sidebar" x-ref="sidebar"
               @mouseover="showSidebarTooltip($event.target)"
               @mouseleave="sidebarTooltip = null"
               @focusin="showSidebarTooltip($event.target)"
               @focusout="sidebarTooltip = null"
               @scroll.capture="sidebarTooltip = null"
               @click="sidebarTooltip = null"
               @keydown.escape="sidebarTooltip = null"
               class="theme-sidebar group/sidebar hidden md:flex fixed top-0 left-0 h-screen border-r backdrop-blur-2xl z-50 flex-col justify-between py-4 overflow-hidden">

            <!-- Sidebar Header / Brand -->
            <div class="h-16 px-3 flex shrink-0 items-center justify-center border-b border-white/[0.06]">
                <a href="/dashboard" wire:navigate data-sidebar-tooltip="{{ __('Dashboard') }}" aria-label="{{ __('Dashboard') }}" class="flex items-center justify-center w-full gap-2.5">
                    <img src="/gamersrival-logo.webp" alt="GamersRival logo" width="64" height="48" class="player-sidebar-brand-logo object-contain">
                </a>
            </div>

            <!-- Navigation Links -->
            @php
                $playerTournamentFrequency = request()->query('frequency', 'daily');
                if (! in_array($playerTournamentFrequency, ['daily', 'weekly', 'monthly'], true)) {
                    $playerTournamentFrequency = 'daily';
                }
                $navItems = [
                    ['label' => __('Overview'),    'icon' => 'layout-dashboard', 'url' => '/dashboard',        'active' => request()->is('dashboard'), 'group' => __('Arena')],
                    ['label' => __('Daily Tournaments'), 'icon' => 'calendar-days', 'url' => '/tournaments/browse?frequency=daily', 'active' => request()->is('tournaments/browse*') && $playerTournamentFrequency === 'daily', 'group' => __('Compete')],
                    ['label' => __('Weekly Tournaments'), 'icon' => 'calendar-range', 'url' => '/tournaments/browse?frequency=weekly', 'active' => request()->is('tournaments/browse*') && $playerTournamentFrequency === 'weekly', 'group' => __('Compete')],
                    ['label' => __('Monthly Tournaments'), 'icon' => 'calendar-fold', 'url' => '/tournaments/browse?frequency=monthly', 'active' => request()->is('tournaments/browse*') && $playerTournamentFrequency === 'monthly', 'group' => __('Compete')],
                    ['label' => __('My Games'),    'icon' => 'trophy',           'url' => '/my-tournaments',   'active' => request()->is('my-tournaments'), 'group' => __('Arena')],
                    ['label' => __('Leaderboard'), 'icon' => 'award',            'url' => '/leaderboards',     'active' => request()->is('leaderboards'), 'group' => __('Community')],
                    ['label' => __('Streams'),     'icon' => 'tv',               'url' => '/streams',          'active' => request()->is('streams'), 'group' => __('Community')],
                    ['label' => __('Chat'),        'icon' => 'message-square',   'url' => '/chat',             'active' => request()->is('chat'), 'group' => __('Community')],
                    ['label' => __('Squads'),      'icon' => 'users',            'url' => '/teams',            'active' => request()->is('teams'), 'group' => __('Community')],
                    ['label' => __('Support'),     'icon' => 'headphones',       'url' => '/contact',          'active' => request()->is('contact'), 'group' => __('More')],
                    ['label' => __('Review Us'),   'icon' => 'star',             'url' => '/reviews',          'active' => request()->is('reviews'), 'group' => __('More')],
                ];
                if (config('features.team_finder.enabled')) {
                    array_splice($navItems, 7, 0, [[
                        'label' => __('Find Team'), 'icon' => 'user-search', 'url' => '/teams#find-team', 'active' => false, 'group' => __('Community'),
                    ]]);
                }
                if (config('features.tournament_v2.enabled')) {
                    array_splice($navItems, 3, 0, [[
                        'label' => __('Head-to-Head'), 'icon' => 'swords', 'url' => '/h2h', 'active' => request()->is('h2h'), 'group' => __('Compete'),
                    ]]);
                }
                if (config('features.player_wager.enabled')) {
                    array_splice($navItems, 4, 0, [[
                        'label' => __('Head-to-Head Duels'), 'icon' => 'swords', 'url' => '/head-to-head', 'active' => request()->is('head-to-head'), 'group' => __('Compete'),
                    ]]);
                }
                $previousGroup = null;
            @endphp

            <nav class="my-4 min-h-0 flex-grow space-y-1 overflow-y-auto px-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @foreach($navItems as $item)
                    @if($item['group'] !== $previousGroup)
                        @if($previousGroup !== null)
                            <div class="my-2 border-t border-white/[0.05]"></div>
                        @endif
                        <p x-show="sidebarExpanded" class="sidebar-label px-3 pt-1 pb-1 text-[9px] font-black uppercase tracking-[0.22em] text-zinc-500">
                            {{ $item['group'] }}
                        </p>
                        @php $previousGroup = $item['group']; @endphp
                    @endif
                    <a href="{{ $item['url'] }}" wire:navigate data-sidebar-tooltip="{{ $item['label'] }}"
                       class="theme-nav-item relative flex items-center group/item h-11 px-3 rounded-xl border transition-all duration-200
                       {{ $item['active']
                          ? 'theme-nav-active bg-[#c6ff3d]/12 border-[#c6ff3d]/40 text-[#c6ff3d] shadow-[0_0_16px_rgba(198,255,61,0.12)]'
                          : 'bg-transparent border-transparent text-zinc-400 hover:text-white hover:bg-white/[0.04] hover:border-cyan-500/20' }}">
                        @if($item['active'])
                            <span class="absolute left-0 top-2 bottom-2 w-1 rounded-r-full bg-[#c6ff3d] shadow-[0_0_8px_rgba(198,255,61,0.8)]" aria-hidden="true"></span>
                        @endif
                        <div class="flex-shrink-0 w-6 h-6 flex items-center justify-center">
                            <i data-lucide="{{ $item['icon'] }}" class="w-4.5 h-4.5 transition-transform duration-200 group-hover/item:scale-110 {{ $item['active'] ? 'text-[#c6ff3d]' : 'text-zinc-400 group-hover/item:text-cyan-300' }}"></i>
                        </div>
                        <span class="sidebar-label ml-3.5 font-orbitron text-[11px] font-bold uppercase tracking-wider transition-opacity duration-200">
                            {{ $item['label'] }}
                        </span>
                        @if($item['url'] === '/chat')<span :class="sidebarExpanded ? 'ml-auto' : 'absolute right-0 top-0'"><x-chat-unread-badge /></span>@endif
                    </a>
                @endforeach
            </nav>

            <!-- Sidebar Bottom Action -->
            <div class="px-3 pt-2 border-t border-white/[0.06]">
                <div x-show="sidebarExpanded" x-cloak class="mb-2.5"><x-app-clock /></div>
                @if(auth()->user()?->hasAnyRole(['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER']))
                <a href="/admin" wire:navigate data-sidebar-tooltip="{{ __('Admin Panel') }}"
                   class="w-full flex items-center h-11 px-3 mb-1.5 rounded-xl border border-amber-400/30 bg-amber-400/10 text-amber-300 hover:text-amber-200 hover:bg-amber-400/15 hover:border-amber-400/50 transition-all duration-200">
                    <div class="flex-shrink-0 w-6 h-6 flex items-center justify-center">
                        <i data-lucide="shield" class="w-4.5 h-4.5"></i>
                    </div>
                    <span class="sidebar-label ml-3.5 font-orbitron text-[11px] font-bold uppercase tracking-wider transition-opacity duration-200">
                        {{ __('Admin Panel') }}
                    </span>
                </a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="button" data-sidebar-tooltip="{{ __('Logout') }}" onclick="document.getElementById('logout-confirmation')?.showModal()"
                            class="w-full flex items-center h-11 px-3 rounded-xl border border-transparent text-zinc-500 hover:text-red-400 hover:bg-red-500/10 hover:border-red-500/20 transition-all duration-200">
                        <div class="flex-shrink-0 w-6 h-6 flex items-center justify-center">
                            <i data-lucide="log-out" class="w-4.5 h-4.5"></i>
                        </div>
                        <span class="sidebar-label ml-3.5 font-orbitron text-[11px] font-bold uppercase tracking-wider transition-opacity duration-200">
                            {{ __('Logout') }}
                        </span>
                    </button>
                </form>
            </div>
        </aside>

        {{-- Outside the sidebar so the scroll container cannot clip the tooltip. --}}
        <div x-show="sidebarTooltip && !sidebarExpanded"
             x-cloak
             x-transition.opacity.duration.100ms
             x-text="sidebarTooltip?.label"
             :style="sidebarTooltip ? { top: sidebarTooltip.top + 'px', left: sidebarTooltip.left + 'px' } : {}"
             class="player-sidebar-tooltip fixed z-[60] hidden md:block pointer-events-none"
             aria-hidden="true"></div>

        <!-- Right Side: Header + Content Pane -->
        <div class="player-dashboard-pane flex-1 flex flex-col min-w-0 relative">

            <!-- Topbar sticky header -->
            <header class="theme-header sticky top-0 z-40 h-16 md:h-18 border-b border-cyan-500/15 bg-[#060b14]/88 backdrop-blur-xl px-4 sm:px-6 flex items-center justify-between shadow-[0_6px_24px_rgba(0,0,0,0.45)]">
                <!-- Left: Logo (mobile only) + Desktop section title -->
                <div class="flex min-w-0 items-center space-x-3">
                    <!-- Mobile Logo -->
                    <a href="/dashboard" wire:navigate class="md:hidden flex shrink-0 items-center justify-center">
                        <img src="/gamersrival-logo.webp" alt="GamersRival logo" width="48" height="36" style="width:48px;height:36px;object-fit:contain">
                    </a>

                    <!-- Mobile Page Title -->
                    <h1 class="theme-page-title min-w-0 truncate md:hidden text-xs font-black tracking-widest text-white font-orbitron uppercase">
                        @yield('dashboard_title', __('TERMINAL'))
                    </h1>

                    <!-- Desktop section title -->
                    <div class="hidden md:flex items-center space-x-3">
                        <button type="button"
                                class="player-sidebar-toggle inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border focus-visible:outline-2 focus-visible:outline-offset-2"
                                :class="{ 'is-expanded': sidebarExpanded }"
                                @click="sidebarExpanded = !sidebarExpanded; sidebarTooltip = null"
                                aria-controls="desktop-sidebar"
                                aria-expanded="false"
                                aria-label="{{ __('Expand sidebar') }}"
                                :aria-expanded="sidebarExpanded"
                                :aria-label="sidebarExpanded ? @js(__('Collapse sidebar')) : @js(__('Expand sidebar'))"
                                :title="sidebarExpanded ? @js(__('Collapse sidebar')) : @js(__('Expand sidebar'))">
                            <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="16" rx="3" />
                                <path d="M9 4v16" />
                                <rect x="4.5" y="5.5" width="3" height="13" rx="1" fill="currentColor" stroke="none" :opacity="sidebarExpanded ? '0.45' : '0.15'" />
                                <path d="M12 12h6" />
                                <polyline points="15,9 18,12 15,15" :points="sidebarExpanded ? '15,9 12,12 15,15' : '15,9 18,12 15,15'" />
                            </svg>
                        </button>
                        <span class="theme-title-marker hidden xs:block w-1.5 h-5 bg-gradient-to-b from-[#c6ff3d] to-[#22e6ff] rounded-full shadow-[0_0_10px_rgba(198,255,61,0.5)]"></span>
                        <h1 class="theme-page-title text-xs sm:text-sm md:text-base font-black tracking-widest text-white font-orbitron uppercase truncate max-w-[150px] sm:max-w-none">
                            @yield('dashboard_title', __('DASHBOARD'))
                        </h1>
                    </div>
                </div>

                <!-- Right: Stats & Controls -->
                <div class="flex shrink-0 items-center space-x-2 sm:space-x-3">

                    <!-- Live wallet balance stays visible on mobile. -->
                    <a href="/wallet" wire:navigate aria-label="{{ __('Wallet balance') }}" class="flex shrink-0 items-center space-x-2 bg-[#0a1220]/90 border border-emerald-500/25 hover:border-[#c6ff3d]/50 rounded-xl px-2.5 sm:px-3.5 py-1.5 transition-all duration-200 group">
                        <div class="w-1.5 h-1.5 rounded-full bg-[#c6ff3d] shadow-[0_0_8px_rgba(198,255,61,0.9)]"></div>
                        <div class="flex flex-col text-right">
                            <span class="text-[8px] text-zinc-500 font-bold uppercase tracking-wider">{{ __('BAL') }}</span>
                            <livewire:wallet.wallet-balance />
                        </div>
                    </a>

                    <!-- Deposit CTA Button -->
                    <a href="/wallet" wire:navigate class="theme-primary-action hidden sm:inline-flex items-center justify-center rounded-xl bg-[#c6ff3d] hover:bg-[#d5ff66] border border-[#c6ff3d] px-3.5 py-2 font-orbitron text-[10px] font-black uppercase tracking-widest text-[#05080f] shadow-[0_0_18px_rgba(198,255,61,0.28)] hover:shadow-[0_0_24px_rgba(198,255,61,0.5)] transition-all duration-200">
                        <span>{{ __('+ Deposit') }}</span>
                    </a>

                    <!-- Notifications Bell -->
                    <livewire:notification.notification-bell />

                    <!-- Profile Dropdown (Avatar + Theme Switcher inside menu) -->
                    @php
                        $topbarAvatarUrl = auth()->user()?->profile?->avatar_url;
                    @endphp
                    <div class="relative" x-data="{ open: false, avatarUrl: @js($topbarAvatarUrl ?: '') }" @avatar-updated.window="avatarUrl = $event.detail.url" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="flex items-center space-x-2 bg-[#0a1220]/80 hover:bg-[#0e182a] border border-cyan-500/25 hover:border-cyan-400/50 rounded-full py-1.5 pl-1.5 pr-2.5 transition-all duration-200">
                            <div class="w-6 h-6 md:w-7 md:h-7 rounded-full bg-gradient-to-br from-[#c6ff3d] to-[#22e6ff] p-[1.5px]">
                                <div class="w-full h-full bg-[#05080f] rounded-full overflow-hidden flex items-center justify-center text-cyan-300 text-[9px] font-bold font-orbitron">
                                    <img x-show="avatarUrl" :src="avatarUrl" alt="{{ auth()->user()->username }}" class="h-full w-full object-cover" x-cloak>
                                    <span x-show="!avatarUrl">{{ strtoupper(substr(auth()->user()->username, 0, 2)) }}</span>
                                </div>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-zinc-200 hidden md:inline">
                                {{ auth()->user()->username }}
                            </span>
                            <i data-lucide="chevron-down" class="w-3 h-3 text-zinc-400"></i>
                        </button>

                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="theme-popover absolute right-0 mt-3 w-64 bg-[#0a1220] border border-cyan-500/25 rounded-2xl shadow-[0_18px_45px_rgba(0,0,0,0.85)] z-50 py-1.5"
                             x-cloak>
                            {{-- Role badge --}}
                            @php
                                $role = auth()->user()?->roles?->pluck('name')?->first();
                                $profileUrl = auth()->user()?->hasAnyRole(['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER'])
                                    ? '/admin/profile'
                                    : '/profile';
                            @endphp
                            @if($role && $role !== 'PLAYER')
                            <div class="px-3.5 py-2 border-b border-white/[0.06]">
                                <span class="inline-flex items-center gap-1 text-[9px] font-bold uppercase tracking-wider bg-amber-900/40 text-amber-300 border border-amber-700/30 rounded-full px-2 py-0.5">
                                    <i data-lucide="shield" class="w-2.5 h-2.5"></i>
                                    {{ $role }}
                                </span>
                            </div>
                            @endif
                            <a href="{{ $profileUrl }}" wire:navigate class="flex items-center space-x-2.5 px-3.5 py-2.5 text-xs font-semibold text-zinc-200 hover:bg-cyan-500/10 hover:text-white transition-colors">
                                <i data-lucide="user" class="w-4 h-4 text-cyan-400"></i>
                                <span>{{ __('My Profile') }}</span>
                            </a>
                            @if(!auth()->user()->hasAnyRole(['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER']))
                            <a href="/wallet" wire:navigate class="flex items-center space-x-2.5 px-3.5 py-2.5 text-xs font-semibold text-zinc-200 hover:bg-cyan-500/10 hover:text-white transition-colors">
                                <i data-lucide="wallet" class="w-4 h-4 text-[#c6ff3d]"></i>
                                <span>{{ __('My Wallet') }}</span>
                            </a>
                            @endif
                            @if(auth()->user()?->hasAnyRole(['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER']))
                            <a href="/admin" wire:navigate class="flex items-center space-x-2.5 px-3.5 py-2.5 text-xs font-semibold text-amber-300 hover:bg-amber-950/30 hover:text-amber-200 transition-colors">
                                <i data-lucide="shield" class="w-4 h-4"></i>
                                <span>{{ __('Admin Panel') }}</span>
                            </a>
                            @endif
                            <hr class="border-white/[0.06] my-1">
                            <livewire:identity.theme-switcher variant="menu" key="player-header-theme" />
                            <hr class="border-white/[0.06] my-1">
                            <form method="POST" action="{{ route('logout') }}" class="m-0">
                                @csrf
                                <button type="button" onclick="document.getElementById('logout-confirmation')?.showModal()" class="w-full flex items-center space-x-2.5 px-3.5 py-2.5 text-xs font-semibold text-red-400 hover:bg-red-500/10 transition-colors text-left">
                                    <i data-lucide="log-out" class="w-4 h-4 text-red-500"></i>
                                    <span>{{ __('Logout') }}</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <x-localization.language-switcher variant="player" class="hidden sm:block" />

                </div>
            </header>

            <!-- Main Scrollable Pane -->
            <main class="flex-grow p-4 sm:p-6 md:p-8 flex flex-col relative player-main-content">
                @auth
                    @if(! auth()->user()->hasVerifiedEmail())
                        <aside class="mb-5 flex flex-col gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-3"><i data-lucide="mail-warning" class="mt-0.5 h-5 w-5 shrink-0 text-amber-300"></i><p class="text-xs leading-relaxed text-amber-100">Your email is not verified. You can keep playing, but withdrawals and email notifications stay unavailable until you verify it.</p></div>
                            <a href="/profile" wire:navigate class="shrink-0 rounded-lg border border-amber-400/30 bg-zinc-950/50 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-amber-200 hover:bg-amber-500/15">Verify email</a>
                        </aside>
                    @endif
                @endauth
                @php($activePromotion = \App\Modules\Community\Models\Advertisement::query()->currentlyVisible()->latest()->first())
                @if($activePromotion)
                    <aside x-data="{ visible: true }" x-show="visible" class="mb-5 overflow-hidden rounded-2xl border border-cyan-500/25 bg-gradient-to-r from-[#0a1220] to-[#0e182a] shadow-lg">
                        <div class="flex items-center gap-4 p-4">
                            @if($activePromotion->image_url)<img src="{{ $activePromotion->image_url }}" alt="" class="h-16 w-24 rounded-lg object-cover">@endif
                            <div class="min-w-0 flex-1"><p class="text-[10px] font-black uppercase tracking-widest text-cyan-300">Sponsored promotion</p><h2 class="mt-1 font-bold text-white">{{ $activePromotion->title }}</h2>@if($activePromotion->description)<p class="mt-1 text-xs text-zinc-400">{{ $activePromotion->description }}</p>@endif</div>
                            @if($activePromotion->target_url)<a href="{{ route('promotions.click', $activePromotion) }}" target="_blank" rel="noopener sponsored" class="rounded-lg bg-[#c6ff3d] px-4 py-2 text-xs font-black uppercase tracking-wider text-[#05080f]">{{ $activePromotion->cta_label }}</a>@endif
                            <button type="button" @click="visible = false" aria-label="Dismiss promotion" class="text-zinc-500 hover:text-white">×</button>
                        </div>
                    </aside>
                @endif
                <div id="player-page-loader" class="player-page-loader" aria-hidden="true" data-page-type="default">
                    <div class="player-page-loader-shell">
                        <div class="player-loader-hud">
                            <div>
                                <p class="player-loader-kicker">{{ __('Loading arena') }}</p>
                                <p class="player-loader-title">{{ __('Syncing player terminal') }}</p>
                            </div>
                            <div class="player-loader-ring" aria-hidden="true"></div>
                        </div>

                        <div class="player-loader-stage" aria-hidden="true">
                            <div class="player-loader-scan"></div>
                            <div class="player-loader-grid">
                                <span></span><span></span><span></span><span></span>
                                <span></span><span></span><span></span><span></span>
                                <span></span><span></span><span></span><span></span>
                            </div>
                        </div>

                        <div class="player-page-skeleton" aria-hidden="true">
                            <div class="player-skeleton-line player-skeleton-title"></div>
                            <div class="player-skeleton-line player-skeleton-hero"></div>
                            <div class="player-skeleton-metrics">
                                <div class="player-skeleton-card"></div>
                                <div class="player-skeleton-card"></div>
                                <div class="player-skeleton-card"></div>
                            </div>
                            <div class="player-skeleton-content">
                                <div class="player-skeleton-block"></div>
                                <div class="player-skeleton-block"></div>
                            </div>
                        </div>
                    </div>
                </div>
                {{ $slot }}
            </main>

        </div>
    </div>

    <!-- ─────────────────────────────────────
         MOBILE BOTTOM NAVIGATION BAR
    ───────────────────────────────────────── -->
    <nav id="mobile-bottom-nav" role="navigation" aria-label="Main navigation">
        <div class="mobile-nav-items">
            @foreach($bottomNavItems as $item)
                @if($item['url'])
                    <a href="{{ $item['url'] }}" wire:navigate
                       class="mobile-nav-item {{ $item['pattern'] && request()->is($item['pattern']) ? 'active' : '' }}"
                       aria-label="{{ $item['label'] }}"
                       id="mobile-nav-{{ $loop->index }}">
                        <i data-lucide="{{ $item['icon'] }}" class="mobile-nav-icon"></i>
                        <span class="mobile-nav-label">{{ $item['label'] }}</span>
                    </a>
                @else
                    {{-- "More" button triggers the slide-up panel --}}
                    <button type="button"
                            id="mobile-more-btn"
                            aria-label="{{ __('More navigation options') }}"
                            aria-expanded="false"
                            class="mobile-nav-item relative">
                        <span class="absolute right-1 top-0"><x-chat-unread-badge /></span>
                        <i data-lucide="{{ $item['icon'] }}" class="mobile-nav-icon"></i>
                        <span class="mobile-nav-label">{{ $item['label'] }}</span>
                    </button>
                @endif
            @endforeach
        </div>
    </nav>

    <x-logout-confirmation />
    <x-pwa-update-prompt />
    @livewireScripts

    <script src="https://unpkg.com/lucide@latest"></script>
</body>
</html>
