<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'Gamer Terminal | PlayerSaloons' }}</title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0a0718">
    <link rel="apple-touch-icon" href="/playersaloons_logo.webp">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <!-- Google Fonts for Gaming Aesthetic -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&family=Rajdhani:wght@500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">

    @auth
        <meta name="user-uuid" content="{{ auth()->user()->uuid }}">
    @endauth

    <!-- Tailwind CSS & Fonts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-[#05030c] text-zinc-100 min-h-screen font-sans antialiased overflow-x-hidden selection:bg-violet-600 selection:text-white relative cyber-grid">
    <!-- Global Background FX -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <!-- Neon Orbs -->
        <div class="absolute top-[-10%] right-[-10%] w-[45%] h-[45%] bg-purple-700/10 rounded-full blur-[140px]"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[45%] h-[45%] bg-fuchsia-600/10 rounded-full blur-[140px]"></div>
        <div class="absolute top-[40%] left-[30%] w-[35%] h-[35%] bg-indigo-900/5 rounded-full blur-[150px]"></div>
        <!-- Scanlines for sci-fi atmosphere -->
        <div class="absolute inset-0 scanlines opacity-[0.15] mix-blend-overlay"></div>
    </div>

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
                ['label' => 'Leaderboard', 'icon' => 'award',          'url' => '/leaderboards',    'pattern' => 'leaderboards'],
                ['label' => 'Streams',     'icon' => 'tv',              'url' => '/streams',         'pattern' => 'streams'],
                ['label' => 'Chat',        'icon' => 'message-square',  'url' => '/chat',            'pattern' => 'chat'],
                ['label' => 'My Teams',    'icon' => 'users',           'url' => '/teams',           'pattern' => 'teams'],
                ['label' => 'Wallet',      'icon' => 'wallet',          'url' => '/wallet',          'pattern' => 'wallet'],
                ['label' => 'Profile',     'icon' => 'user-round',      'url' => '/profile',         'pattern' => 'profile'],
            ];
            if(auth()->user()?->hasAnyRole(['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER'])) {
                $moreItems[] = ['label' => 'Admin', 'icon' => 'shield', 'url' => '/admin', 'pattern' => 'admin*'];
            }
        @endphp

        <!-- Grid of more items -->
        <div class="more-panel-grid">
            @foreach($moreItems as $item)
                <a href="{{ $item['url'] }}" wire:navigate
                   class="more-panel-item {{ request()->is($item['pattern']) ? 'active' : '' }}"
                   id="more-item-{{ $loop->index }}">
                    <i data-lucide="{{ $item['icon'] }}" class="more-item-icon"></i>
                    <span class="more-item-label">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>

        <!-- Logout -->
        <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="submit" class="more-panel-logout">
                <i data-lucide="log-out" class="more-panel-logout-icon"></i>
                Disconnect
            </button>
        </form>
    </div>

    <!-- Main Outer Container -->
    <div class="relative z-10 flex min-h-screen w-full">

        <!-- Desktop Sidebar Panel (Hidden on mobile, sticky on desktop) -->
        <aside id="desktop-sidebar" class="group/sidebar hidden md:flex sticky top-0 left-0 h-screen bg-[#0a0718]/90 border-r border-purple-500/15 backdrop-blur-2xl z-50 flex-col justify-between py-5 overflow-hidden shadow-[5px_0_25px_rgba(0,0,0,0.6)]">
            
            <!-- Sidebar Header / Logo -->
            <div class="px-4 flex items-center justify-center">
                <a href="/dashboard" wire:navigate class="flex items-center justify-center w-full">
                    <div class="relative flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-purple-600 to-fuchsia-600 p-[1px] shadow-[0_0_15px_rgba(168,85,247,0.4)] transition-transform duration-500 group-hover/sidebar:rotate-[360deg]">
                        <div class="w-full h-full bg-[#0a0718] rounded-xl flex items-center justify-center">
                            <img src="/playersaloons_logo.webp" alt="Logo" class="w-9 h-9 object-contain">
                        </div>
                    </div>
                </a>
            </div>

            <!-- Navigation Links -->
            @php
                $navItems = [
                    ['label' => 'Overview',    'icon' => 'layout-dashboard', 'url' => '/dashboard',        'active' => request()->is('dashboard')],
                    ['label' => 'Tournaments', 'icon' => 'search',           'url' => '/tournaments/browse','active' => request()->is('tournaments/browse*')],
                    ['label' => 'My Games',    'icon' => 'trophy',           'url' => '/my-tournaments',   'active' => request()->is('my-tournaments')],
                    ['label' => 'H2H Duels',   'icon' => 'swords',          'url' => '/head-to-head',     'active' => request()->is('head-to-head')],
                    ['label' => 'Leaderboard', 'icon' => 'award',            'url' => '/leaderboards',     'active' => request()->is('leaderboards')],
                    ['label' => 'Streams',     'icon' => 'tv',               'url' => '/streams',          'active' => request()->is('streams')],
                    ['label' => 'Chat',        'icon' => 'message-square',   'url' => '/chat',             'active' => request()->is('chat')],
                ];
            @endphp

            <nav class="flex-grow my-8 px-3 space-y-2.5">
                @foreach($navItems as $item)
                    <a href="{{ $item['url'] }}" wire:navigate 
                       class="flex items-center group/item h-12 px-3 rounded-lg border transition-all duration-200 
                       {{ $item['active'] 
                          ? 'bg-purple-950/40 border-purple-500/40 text-purple-300 shadow-[0_0_12px_rgba(168,85,247,0.15)]' 
                          : 'bg-transparent border-transparent text-zinc-500 hover:text-zinc-200 hover:bg-zinc-900/40 hover:border-zinc-800' }}">
                        <div class="flex-shrink-0 w-6 h-6 flex items-center justify-center">
                            <i data-lucide="{{ $item['icon'] }}" class="w-5 h-5 transition-transform duration-200 group-hover/item:scale-110"></i>
                        </div>
                        <span class="sidebar-label ml-4 font-orbitron text-xs font-bold uppercase tracking-widest transition-opacity duration-200">
                            {{ $item['label'] }}
                        </span>
                    </a>
                @endforeach
            </nav>

            <!-- Sidebar Bottom Action -->
            <div class="px-3">
                @if(auth()->user()?->hasAnyRole(['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER']))
                <a href="/admin" wire:navigate
                   class="w-full flex items-center h-12 px-3 mb-2 rounded-lg border border-amber-500/30 bg-amber-950/20 text-amber-400 hover:text-amber-300 hover:bg-amber-950/40 hover:border-amber-400/50 transition-all duration-200">
                    <div class="flex-shrink-0 w-6 h-6 flex items-center justify-center">
                        <i data-lucide="shield" class="w-5 h-5"></i>
                    </div>
                    <span class="sidebar-label ml-4 font-orbitron text-xs font-bold uppercase tracking-widest transition-opacity duration-200">
                        Admin Panel
                    </span>
                </a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" 
                            class="w-full flex items-center h-12 px-3 rounded-lg border border-transparent text-zinc-500 hover:text-red-400 hover:bg-red-500/10 hover:border-red-500/20 transition-all duration-200">
                        <div class="flex-shrink-0 w-6 h-6 flex items-center justify-center">
                            <i data-lucide="log-out" class="w-5 h-5"></i>
                        </div>
                        <span class="sidebar-label ml-4 font-orbitron text-xs font-bold uppercase tracking-widest transition-opacity duration-200">
                            Exit Terminal
                        </span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Right Side: Header + Content Pane -->
        <div class="flex-1 flex flex-col min-w-0 relative">
            
            <!-- Topbar sticky header -->
            <header class="sticky top-0 z-40 h-16 md:h-20 border-b border-purple-500/15 bg-[#0a0718]/80 backdrop-blur-xl px-4 sm:px-6 flex items-center justify-between shadow-[0_4px_20px_rgba(0,0,0,0.5)]">
                <!-- Left: Logo (mobile only) + Desktop section title -->
                <div class="flex items-center space-x-3">
                    <!-- Mobile Logo -->
                    <a href="/dashboard" wire:navigate class="md:hidden flex items-center justify-center w-9 h-9 rounded-lg bg-gradient-to-br from-purple-600 to-fuchsia-600 p-[1px] shadow-[0_0_12px_rgba(168,85,247,0.4)]">
                        <div class="w-full h-full bg-[#0a0718] rounded-md flex items-center justify-center">
                            <img src="/playersaloons_logo.webp" alt="Logo" class="w-6 h-6 object-contain">
                        </div>
                    </a>

                    <!-- Mobile Page Title -->
                    <h1 class="md:hidden text-xs font-black tracking-widest text-purple-400 font-orbitron uppercase neon-pulse-purple">
                        @yield('dashboard_title', 'TERMINAL')
                    </h1>

                    <!-- Desktop section title -->
                    <div class="hidden md:flex items-center space-x-3">
                        <span class="hidden xs:block w-2 h-6 bg-gradient-to-b from-purple-500 to-fuchsia-500 rounded-full shadow-[0_0_8px_rgba(168,85,247,0.6)]"></span>
                        <h1 class="text-xs sm:text-sm md:text-base font-black tracking-widest text-purple-400 font-orbitron uppercase neon-pulse-purple truncate max-w-[150px] sm:max-w-none">
                            @yield('dashboard_title', 'DASHBOARD')
                        </h1>
                    </div>
                </div>

                <!-- Right: Stats & Controls -->
                <div class="flex items-center space-x-2 sm:space-x-4">
                    
                    <!-- Wallet Balance Box (hidden on small mobile, shown on xs+) -->
                    <a href="/wallet" wire:navigate class="hidden xs:flex items-center space-x-2 bg-[#120a26]/70 border border-emerald-500/30 hover:border-emerald-400/60 rounded-xl px-3 py-1.5 transition-all duration-300 shadow-[inset_0_0_8px_rgba(16,185,129,0.05)] group">
                        <div class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></div>
                        <div class="flex flex-col text-right">
                            <span class="text-[8px] text-zinc-500 font-bold uppercase tracking-wider">BAL</span>
                            <span class="text-xs font-black text-emerald-400 font-orbitron tracking-wider group-hover:text-emerald-300 transition-colors">
                                ${{ number_format((float)(auth()->user()->wallet?->cached_balance ?? 0.00), 2) }}
                            </span>
                        </div>
                    </a>

                    <!-- Deposit CTA Button (hidden on smallest screens) -->
                    <a href="/wallet" wire:navigate class="hidden sm:inline-flex relative items-center justify-center p-0.5 overflow-hidden text-xs font-bold text-white rounded-lg group bg-gradient-to-br from-purple-600 to-fuchsia-500 hover:from-purple-500 hover:to-fuchsia-400 border border-fuchsia-400/20 shadow-[0_0_15px_rgba(217,70,239,0.3)] hover:shadow-[0_0_20px_rgba(217,70,239,0.6)] transition-all duration-300 cursor-pointer">
                        <span class="relative px-3 py-1.5 transition-all ease-in duration-75 bg-[#0a0718]/90 rounded-md group-hover:bg-transparent font-orbitron tracking-widest uppercase text-[10px]">
                            + Deposit
                        </span>
                    </a>

                    <!-- Notifications Bell -->
                    <livewire:notification.notification-bell />

                    <!-- Profile Dropdown (Avatar) -->
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button @click="open = !open" class="flex items-center space-x-1.5 bg-zinc-900/40 hover:bg-zinc-800/60 border border-purple-500/20 rounded-full py-1.5 pl-1.5 pr-2 transition-all duration-300 shadow-[0_0_10px_rgba(168,85,247,0.1)] hover:shadow-[0_0_15px_rgba(168,85,247,0.25)]">
                            <div class="w-6 h-6 md:w-7 md:h-7 rounded-full bg-gradient-to-br from-purple-500 to-fuchsia-500 p-[1.5px]">
                                <div class="w-full h-full bg-[#0a0718] rounded-full flex items-center justify-center text-purple-400 text-[9px] font-bold font-orbitron">
                                    {{ strtoupper(substr(auth()->user()->username, 0, 2)) }}
                                </div>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-purple-300 hidden md:inline">
                                {{ auth()->user()->username }}
                            </span>
                            <i data-lucide="chevron-down" class="w-3 h-3 text-zinc-500"></i>
                        </button>

                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute right-0 mt-3 w-52 bg-[#0e0a24] border border-purple-500/20 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.8)] z-50 py-1"
                             x-cloak>
                            {{-- Role badge --}}
                            @php 
                                $role = auth()->user()?->roles?->pluck('name')?->first(); 
                                $profileUrl = auth()->user()?->hasAnyRole(['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER']) 
                                    ? '/admin/profile' 
                                    : '/profile';
                            @endphp
                            @if($role && $role !== 'PLAYER')
                            <div class="px-4 py-2 border-b border-purple-500/10">
                                <span class="inline-flex items-center gap-1 text-[9px] font-bold uppercase tracking-wider bg-amber-900/40 text-amber-300 border border-amber-700/30 rounded-full px-2 py-0.5">
                                    <i data-lucide="shield" class="w-2.5 h-2.5"></i>
                                    {{ $role }}
                                </span>
                            </div>
                            @endif
                            <a href="{{ $profileUrl }}" wire:navigate class="flex items-center space-x-2 px-4 py-2.5 text-xs text-zinc-350 hover:bg-purple-950/30 hover:text-white transition-colors">
                                <i data-lucide="user" class="w-4 h-4 text-purple-400"></i>
                                <span>My Profile</span>
                            </a>
                            @if(!auth()->user()->hasAnyRole(['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER']))
                            <a href="/wallet" wire:navigate class="flex items-center space-x-2 px-4 py-2.5 text-xs text-zinc-350 hover:bg-purple-950/30 hover:text-white transition-colors">
                                <i data-lucide="wallet" class="w-4 h-4 text-purple-400"></i>
                                <span>My Wallet</span>
                            </a>
                            @endif
                            @if(auth()->user()?->hasAnyRole(['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER']))
                            <a href="/admin" wire:navigate class="flex items-center space-x-2 px-4 py-2.5 text-xs text-amber-400 hover:bg-amber-950/30 hover:text-amber-300 transition-colors">
                                <i data-lucide="shield" class="w-4 h-4"></i>
                                <span>Admin Panel</span>
                            </a>
                            @endif
                            <hr class="border-purple-500/10 my-1">
                            <form method="POST" action="{{ route('logout') }}" class="m-0">
                                @csrf
                                <button type="submit" class="w-full flex items-center space-x-2 px-4 py-2.5 text-xs text-red-400 hover:bg-red-500/10 transition-colors text-left">
                                    <i data-lucide="log-out" class="w-4 h-4 text-red-500"></i>
                                    <span>Disconnect</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Language Switcher Dropdown -->
                    <div class="relative hidden sm:block" x-data="{ open: false }" @click.outside="open = false">
                        <button @click="open = !open" class="p-2 rounded-lg bg-zinc-900/50 border border-zinc-800 hover:border-purple-500/40 text-zinc-400 hover:text-purple-300 transition-all duration-200 flex items-center space-x-1">
                            <i data-lucide="globe" class="w-4 h-4"></i>
                            <span class="text-[9px] font-bold font-orbitron">EN</span>
                        </button>
                        
                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute right-0 mt-3 w-28 bg-[#0e0a24] border border-purple-500/20 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.8)] z-50 py-1"
                             x-cloak>
                            <a href="#" class="block px-4 py-2 text-xs text-zinc-350 bg-purple-950/20 text-white font-bold">English</a>
                            <a href="#" class="block px-4 py-2 text-xs text-zinc-450 hover:bg-purple-950/30 hover:text-white transition-colors">Español</a>
                            <a href="#" class="block px-4 py-2 text-xs text-zinc-450 hover:bg-purple-950/30 hover:text-white transition-colors">Tagalog</a>
                        </div>
                    </div>

                </div>
            </header>

            <!-- Main Scrollable Pane -->
            <main class="flex-grow p-4 sm:p-6 md:p-8 flex flex-col relative player-main-content">
                <div id="player-page-loader" class="player-page-loader" aria-hidden="true" data-page-type="default">
                    <div class="player-page-loader-shell">
                        <div class="player-loader-hud">
                            <div>
                                <p class="player-loader-kicker">Loading arena</p>
                                <p class="player-loader-title">Syncing player terminal</p>
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
         (shown only on mobile, < md breakpoint)
    ───────────────────────────────────────── -->
    <nav id="mobile-bottom-nav" role="navigation" aria-label="Main navigation">
        @php
            $bottomNavItems = [
                ['label' => 'Overview',    'icon' => 'layout-dashboard', 'url' => '/dashboard',        'pattern' => 'dashboard'],
                ['label' => 'Browse',      'icon' => 'search',           'url' => '/tournaments/browse','pattern' => 'tournaments/browse*'],
                ['label' => 'H2H',         'icon' => 'swords',           'url' => '/head-to-head',     'pattern' => 'head-to-head'],
                ['label' => 'My Games',    'icon' => 'trophy',           'url' => '/my-tournaments',   'pattern' => 'my-tournaments'],
                ['label' => 'More',        'icon' => 'grid-3x3',         'url' => null,                'pattern' => null],
            ];
        @endphp

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
                            aria-label="More navigation options"
                            aria-expanded="false"
                            class="mobile-nav-item">
                        <i data-lucide="{{ $item['icon'] }}" class="mobile-nav-icon"></i>
                        <span class="mobile-nav-label">{{ $item['label'] }}</span>
                    </button>
                @endif
            @endforeach
        </div>
    </nav>

    @livewireScripts

    <!-- Lucide Icons initialization -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        document.addEventListener('livewire:navigated', () => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
        document.addEventListener('livewire:init', () => {
            Livewire.hook('morph.updated', ({ el, component }) => {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
            Livewire.hook('message.processed', (message, component) => {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
        });
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>
