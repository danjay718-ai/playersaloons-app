<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Gamer Terminal | PlayerSaloons' }}</title>

    <!-- Google Fonts for Gaming Aesthetic -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&family=Rajdhani:wght@500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">

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

    <!-- Mobile Sidebar Backdrop -->
    <div id="mobile-backdrop"></div>

    <!-- Mobile Sidebar Drawer (Visible only on mobile via trigger) -->
    <div id="mobile-sidebar">
        <div class="px-6 flex items-center space-x-4 mb-8">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-600 to-fuchsia-600 p-[1px] shadow-[0_0_15px_rgba(168,85,247,0.4)]">
                <div class="w-full h-full bg-[#0a0718] rounded-xl flex items-center justify-center">
                    <img src="/playersaloons_logo.webp" alt="Logo" class="w-7 h-7 object-contain">
                </div>
            </div>
            <span class="text-lg font-black font-orbitron tracking-widest bg-gradient-to-r from-purple-400 to-fuchsia-400 bg-clip-text text-transparent uppercase">SALOONS</span>
        </div>

        <nav class="px-4 space-y-2">
            @php
                $navItems = [
                    ['label' => 'Overview', 'icon' => 'layout-dashboard', 'url' => '/dashboard', 'active' => !request()->query('tab')],
                    ['label' => 'Tournaments', 'icon' => 'trophy', 'url' => '/dashboard?tab=tournaments', 'active' => request()->query('tab') === 'tournaments'],
                    ['label' => 'Head-to-Head', 'icon' => 'swords', 'url' => '/dashboard?tab=head-to-head', 'active' => request()->query('tab') === 'head-to-head'],
                    ['label' => 'Leaderboards', 'icon' => 'award', 'url' => '/dashboard?tab=leaderboards', 'active' => request()->query('tab') === 'leaderboards'],
                    ['label' => 'Streams', 'icon' => 'tv', 'url' => '/dashboard?tab=streams', 'active' => request()->query('tab') === 'streams'],
                    ['label' => 'Global Chat', 'icon' => 'message-square', 'url' => '/dashboard?tab=chat', 'active' => request()->query('tab') === 'chat'],
                ];
            @endphp

            @foreach($navItems as $item)
                <a href="{{ $item['url'] }}" wire:navigate 
                   class="nav-link {{ $item['active'] ? 'active' : '' }}">
                    <i data-lucide="{{ $item['icon'] }}" class="w-5 h-5 mr-3"></i>
                    <span class="font-orbitron text-xs font-bold uppercase tracking-widest">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="px-4 mt-auto">
            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" class="w-full flex items-center h-12 px-3 rounded-lg text-zinc-500 hover:text-red-400 transition-colors">
                    <i data-lucide="log-out" class="w-5 h-5 mr-3"></i>
                    <span class="font-orbitron text-xs font-bold uppercase tracking-widest">Disconnect</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Main Outer Container -->
    <div class="relative z-10 flex min-h-screen w-full">

        <!-- Desktop Sidebar Panel (Hidden on mobile, sticky on desktop) -->
        <aside id="desktop-sidebar" class="group/sidebar hidden md:flex sticky top-0 left-0 h-screen bg-[#0a0718]/90 border-r border-purple-500/15 backdrop-blur-2xl z-50 flex-col justify-between py-5 overflow-hidden shadow-[5px_0_25px_rgba(0,0,0,0.6)]">
            
            <!-- Sidebar Header / Logo -->
            <div class="px-4 flex items-center">
                <a href="/dashboard" wire:navigate class="flex items-center space-x-4 w-full">
                    <div class="relative flex-shrink-0 w-11 h-11 rounded-xl bg-gradient-to-br from-purple-600 to-fuchsia-600 p-[1px] shadow-[0_0_15px_rgba(168,85,247,0.4)] transition-transform duration-500 group-hover/sidebar:rotate-[360deg]">
                        <div class="w-full h-full bg-[#0a0718] rounded-xl flex items-center justify-center">
                            <img src="/playersaloons_logo.webp" alt="Logo" class="w-8 h-8 object-contain">
                        </div>
                    </div>
                    <span class="sidebar-label text-lg font-black font-orbitron tracking-widest bg-gradient-to-r from-purple-400 via-fuchsia-400 to-cyan-400 bg-clip-text text-transparent uppercase filter drop-shadow-[0_0_6px_rgba(168,85,247,0.3)]">
                        SALOONS
                    </span>
                </a>
            </div>

            <!-- Navigation Links -->
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
            <header class="sticky top-0 z-40 h-20 border-b border-purple-500/15 bg-[#0a0718]/80 backdrop-blur-xl px-4 sm:px-6 flex items-center justify-between shadow-[0_4px_20px_rgba(0,0,0,0.5)]">
                <!-- Left: Burger Menu (Mobile Only) + Dashboard Section Title -->
                <div class="flex items-center space-x-4">
                    <!-- Burger Button -->
                    <button id="mobile-menu-btn" class="md:hidden flex flex-col justify-center items-center w-10 h-10 bg-zinc-900/50 border border-zinc-800 rounded-lg hover:border-purple-500/40 transition-colors" aria-label="Open Menu">
                        <span class="burger-line"></span>
                        <span class="burger-line my-1"></span>
                        <span class="burger-line"></span>
                    </button>

                    <div class="flex items-center space-x-3">
                        <span class="hidden xs:block w-2 h-6 bg-gradient-to-b from-purple-500 to-fuchsia-500 rounded-full shadow-[0_0_8px_rgba(168,85,247,0.6)]"></span>
                        <h1 class="text-xs sm:text-sm md:text-base font-black tracking-widest text-purple-400 font-orbitron uppercase neon-pulse-purple truncate max-w-[150px] sm:max-w-none">
                            @yield('dashboard_title', 'SYSTEM DASHBOARD')
                        </h1>
                    </div>
                </div>

                <!-- Right: Stats & Controls -->
                <div class="flex items-center space-x-3 sm:space-x-5">
                    
                    <!-- Wallet Balance Box -->
                    <a href="/wallet" wire:navigate class="hidden xs:flex items-center space-x-2 bg-[#120a26]/70 border border-emerald-500/30 hover:border-emerald-400/60 rounded-xl px-4 py-2 transition-all duration-300 shadow-[inset_0_0_8px_rgba(16,185,129,0.05)] group">
                        <div class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></div>
                        <div class="flex flex-col text-right">
                            <span class="text-[8px] text-zinc-500 font-bold uppercase tracking-wider">BALANCE</span>
                            <span class="text-xs sm:text-sm font-black text-emerald-400 font-orbitron tracking-wider group-hover:text-emerald-300 transition-colors">
                                ${{ number_format((float)(auth()->user()->wallet?->cached_balance ?? 0.00), 2) }}
                            </span>
                        </div>
                    </a>

                    <!-- Deposit CTA Button -->
                    <a href="/wallet" wire:navigate class="relative inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-xs font-bold text-white rounded-lg group bg-gradient-to-br from-purple-600 to-fuchsia-500 hover:from-purple-500 hover:to-fuchsia-400 border border-fuchsia-400/20 shadow-[0_0_15px_rgba(217,70,239,0.3)] hover:shadow-[0_0_20px_rgba(217,70,239,0.6)] transition-all duration-300 cursor-pointer mt-2">
                        <span class="relative px-3 py-1.5 transition-all ease-in duration-75 bg-[#0a0718]/90 rounded-md group-hover:bg-transparent font-orbitron tracking-widest uppercase text-[10px]">
                            + Deposit
                        </span>
                    </a>

                    <!-- Notifications Dropdown (Bell icon) -->
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button @click="open = !open" class="relative p-2 rounded-lg bg-zinc-900/50 border border-zinc-800 hover:border-purple-500/40 text-zinc-400 hover:text-purple-300 transition-all duration-200">
                            <i data-lucide="bell" class="w-5 h-5"></i>
                            <!-- Pulse Dot for unread -->
                            <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-fuchsia-500 shadow-[0_0_6px_rgba(244,63,94,0.8)]"></span>
                        </button>
                        
                        <!-- Notifications Panel Menu -->
                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute right-0 mt-3 w-80 bg-[#0e0a24] border border-purple-500/20 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.8)] z-50 py-2"
                             x-cloak>
                            <div class="px-4 py-2 border-b border-purple-500/10 flex justify-between items-center">
                                <span class="font-orbitron font-bold text-xs text-zinc-300 uppercase tracking-wider">SYSTEM LOGS</span>
                                <span class="text-[9px] bg-purple-950 text-purple-400 border border-purple-900 px-2 py-0.5 rounded-full font-bold">2 NEW</span>
                            </div>
                            <div class="max-h-60 overflow-y-auto">
                                <a href="#" class="block px-4 py-3 hover:bg-purple-950/20 border-b border-purple-500/5 transition-colors">
                                    <div class="flex items-start space-x-3">
                                        <div class="p-1.5 bg-purple-900/30 rounded-lg text-purple-400 mt-0.5">
                                            <i data-lucide="trophy" class="w-3.5 h-3.5"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-zinc-300">Tournament Starting Soon</p>
                                            <p class="text-[10px] text-zinc-500 mt-0.5">Viking Clash begins in 15 minutes. Prepare!</p>
                                        </div>
                                    </div>
                                </a>
                                <a href="#" class="block px-4 py-3 hover:bg-purple-950/20 border-b border-purple-500/5 transition-colors">
                                    <div class="flex items-start space-x-3">
                                        <div class="p-1.5 bg-emerald-900/30 rounded-lg text-emerald-400 mt-0.5">
                                            <i data-lucide="wallet" class="w-3.5 h-3.5"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-zinc-300">Fund Deposited Successfully</p>
                                            <p class="text-[10px] text-zinc-500 mt-0.5">Your deposit of $25.00 has been credited.</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="px-4 py-1.5 text-center border-t border-purple-500/10">
                                <a href="#" class="text-[10px] font-bold text-purple-400 hover:text-purple-300 uppercase tracking-widest">Mark all as read</a>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Dropdown (Avatar) -->
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button @click="open = !open" class="flex items-center space-x-2 bg-zinc-900/40 hover:bg-zinc-800/60 border border-purple-500/20 rounded-full py-1.5 pl-1.5 pr-3 transition-all duration-300 shadow-[0_0_10px_rgba(168,85,247,0.1)] hover:shadow-[0_0_15px_rgba(168,85,247,0.25)]">
                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-purple-500 to-fuchsia-500 p-[1.5px]">
                                <div class="w-full h-full bg-[#0a0718] rounded-full flex items-center justify-center text-purple-400 text-xs font-bold font-orbitron">
                                    {{ strtoupper(substr(auth()->user()->username, 0, 2)) }}
                                </div>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-purple-300 hidden md:inline">
                                {{ auth()->user()->username }}
                            </span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-zinc-500"></i>
                        </button>

                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute right-0 mt-3 w-48 bg-[#0e0a24] border border-purple-500/20 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.8)] z-50 py-1"
                             x-cloak>
                            <a href="/profile" wire:navigate class="flex items-center space-x-2 px-4 py-2.5 text-xs text-zinc-350 hover:bg-purple-950/30 hover:text-white transition-colors">
                                <i data-lucide="user" class="w-4 h-4 text-purple-400"></i>
                                <span>My Profile</span>
                            </a>
                            <a href="/wallet" wire:navigate class="flex items-center space-x-2 px-4 py-2.5 text-xs text-zinc-350 hover:bg-purple-950/30 hover:text-white transition-colors">
                                <i data-lucide="wallet" class="w-4 h-4 text-purple-400"></i>
                                <span>My Wallet</span>
                            </a>
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

                    <!-- Language Switcher Dropdown (Globe Icon placeholder) -->
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
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
            <main class="flex-grow p-4 sm:p-6 md:p-8 flex flex-col relative">
                {{ $slot }}
            </main>

        </div>
    </div>

    @livewireScripts

    <!-- Lucide Icons initialization -->
    <script src="https://unpkg.com/lucide@latest"></script>
</body>
</html>
