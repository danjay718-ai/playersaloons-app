<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Admin Panel | PlayerSaloons' }}</title>

    <!-- Google Fonts for Professional Aesthetic (Inter only, no Orbitron for admin) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-[#090d16] text-slate-100 min-h-screen antialiased flex flex-col">

    <!-- Mobile Header -->
    <header class="md:hidden flex items-center justify-between bg-[#0f172a] border-b border-slate-800 px-4 py-3 sticky top-0 z-50">
        <div class="flex items-center space-x-3">
            <span class="w-1.5 h-6 bg-indigo-500 rounded-full"></span>
            <span class="font-bold text-sm tracking-wider uppercase text-slate-200">PS ADMIN</span>
        </div>
        <div class="flex items-center space-x-2">
            @php $mobileRole = auth()->user()?->roles?->pluck('name')?->first() ?? 'Staff'; @endphp
            <span class="text-[9px] font-bold uppercase tracking-wider text-indigo-300 bg-indigo-900/40 border border-indigo-700/30 rounded-full px-2 py-0.5">
                {{ $mobileRole }}
            </span>
            <button id="mobile-menu-toggle" class="p-2 text-slate-400 hover:text-white focus:outline-none">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </header>

    <div class="flex flex-1 flex-col md:flex-row relative">
        <!-- Sidebar Navigation -->
        <aside id="admin-sidebar" class="hidden md:flex flex-col w-64 bg-[#0f172a] border-r border-slate-800 sticky top-0 h-screen z-40">
            <!-- Logo Section -->
            <div class="h-16 flex items-center px-6 border-b border-slate-800 bg-[#0b0f19]">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-white shadow-[0_0_10px_rgba(79,70,229,0.3)]">
                        PS
                    </div>
                    <div>
                        <h1 class="text-xs font-extrabold tracking-widest text-slate-200 uppercase">PlayerSaloons</h1>
                        <p class="text-[9px] font-bold text-indigo-400 uppercase tracking-widest">Control Terminal</p>
                    </div>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
                @php
                    $adminNavItems = [
                        ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'url' => '/admin'],
                        ['label' => 'Tournaments', 'icon' => 'trophy', 'url' => '/admin/tournaments'],
                        ['label' => 'Matches & Disputes', 'icon' => 'swords', 'url' => '/admin/matches'],
                        ['label' => 'KYC Submissions', 'icon' => 'file-check', 'url' => '/admin/kyc'],
                        ['label' => 'Withdrawals', 'icon' => 'wallet', 'url' => '/admin/withdrawals'],
                        ['label' => 'User Directory', 'icon' => 'users', 'url' => '/admin/users'],
                        ['label' => 'Audit Logs', 'icon' => 'file-text', 'url' => '/admin/audit-logs'],
                        ['label' => 'CMS & Games', 'icon' => 'database', 'url' => '/admin/cms'],
                    ];
                @endphp

                @foreach($adminNavItems as $item)
                    @php
                        $isActive = request()->is(ltrim($item['url'], '/')) || (request()->is('admin') && $item['url'] === '/admin');
                    @endphp
                    <a href="{{ $item['url'] }}" wire:navigate 
                       class="flex items-center px-4 py-2.5 rounded-lg border text-sm transition-all duration-150 group
                       {{ $isActive 
                          ? 'bg-indigo-600/15 border-indigo-500/20 text-indigo-300 font-semibold shadow-[inset_0_0_8px_rgba(99,102,241,0.05)]' 
                          : 'bg-transparent border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-800/40 hover:border-slate-800' }}">
                        <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4 mr-3 transition-colors {{ $isActive ? 'text-indigo-400' : 'text-slate-400 group-hover:text-slate-300' }}"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <!-- Sidebar Footer -->
            <div class="p-4 border-t border-slate-800 bg-[#0b0f19]/50 space-y-2">
                <a href="/dashboard" wire:navigate class="flex items-center px-4 py-2 rounded-lg text-xs font-semibold text-slate-400 hover:text-white hover:bg-slate-800/60 transition-colors">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5 mr-2"></i>
                    <span>Player Terminal</span>
                </a>
                <div class="flex items-center justify-between px-4 py-2 bg-slate-900/40 rounded-lg border border-slate-800/50">
                    <div class="truncate">
                        <p class="text-[11px] font-bold text-slate-350 truncate">{{ auth()->user()->username }}</p>
                        <p class="text-[8px] text-slate-500 font-semibold uppercase tracking-wider mt-0.5">{{ str_replace('_', ' ', auth()->user()->roles->pluck('name')->first() ?? 'Staff') }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="p-1.5 text-slate-500 hover:text-red-400 transition-colors" title="Disconnect">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Topbar (Desktop only) -->
            <header class="hidden md:flex h-16 border-b border-slate-800 bg-[#0f172a] px-8 items-center justify-between sticky top-0 z-30">
                <div class="flex items-center space-x-3">
                    <span class="w-1.5 h-6 bg-indigo-500 rounded-full"></span>
                    <h2 class="font-extrabold text-sm uppercase tracking-widest text-slate-200">
                        {{ $admin_title ?? 'System Administration' }}
                    </h2>
                </div>
                
                <div class="flex items-center space-x-4">
                    @php
                        $systemStatus = 'System: Online';
                        $statusColor = 'bg-emerald-500';
                        $pulseClass = 'animate-pulse';
                        
                        try {
                            // Verify database connectivity by checking system settings
                            $maintenanceMode = \App\Modules\Operations\Models\SystemSetting::where('key', 'system.maintenance_mode')->value('value');
                            
                            if ($maintenanceMode === 'true') {
                                $systemStatus = 'System: Maintenance';
                                $statusColor = 'bg-amber-500';
                                $pulseClass = '';
                            }
                        } catch (\Exception $e) {
                            $systemStatus = 'System: Offline';
                            $statusColor = 'bg-red-500';
                            $pulseClass = '';
                        }
                    @endphp
                    <div class="flex items-center space-x-2 text-xs bg-slate-900 border border-slate-800 rounded-full px-3 py-1.5 text-slate-400">
                        <span class="w-2 h-2 rounded-full {{ $statusColor }} {{ $pulseClass }}"></span>
                        <span>{{ $systemStatus }}</span>
                    </div>
                    {{-- Logged-in user badge --}}
                    @php
                        $adminRole = auth()->user()?->roles?->pluck('name')?->first() ?? 'Staff';
                        $roleColors = [
                            'SUPER_ADMIN'         => 'bg-red-900/40 text-red-300 border-red-700/30',
                            'ADMIN'               => 'bg-indigo-900/40 text-indigo-300 border-indigo-700/30',
                            'MODERATOR'           => 'bg-purple-900/40 text-purple-300 border-purple-700/30',
                            'FINANCE_OPERATOR'    => 'bg-emerald-900/40 text-emerald-300 border-emerald-700/30',
                            'KYC_REVIEWER'        => 'bg-sky-900/40 text-sky-300 border-sky-700/30',
                            'SUPPORT_AGENT'       => 'bg-amber-900/40 text-amber-300 border-amber-700/30',
                            'TOURNAMENT_ORGANIZER'=> 'bg-orange-900/40 text-orange-300 border-orange-700/30',
                        ];
                        $roleClass = $roleColors[$adminRole] ?? 'bg-slate-800 text-slate-300 border-slate-700/30';
                    @endphp
                    <a href="/admin/profile" wire:navigate class="flex items-center space-x-3 bg-slate-900/60 border border-slate-700/50 hover:border-slate-650/80 hover:bg-slate-800/40 rounded-xl px-3.5 py-2 transition-all duration-150">
                        <div class="w-9 h-9 rounded-full bg-indigo-600/30 border border-indigo-500/30 flex items-center justify-center text-indigo-300 text-xs font-bold shrink-0">
                            {{ strtoupper(substr(auth()->user()?->username ?? 'A', 0, 2)) }}
                        </div>
                        <div class="flex flex-col items-start min-w-0">
                            <span class="text-xs font-semibold text-slate-200 truncate leading-tight">{{ auth()->user()?->username }}</span>
                            <span class="text-[9px] font-semibold uppercase tracking-wider {{ $roleClass }} inline-flex items-center gap-1 border rounded-full px-2 py-0.5 mt-1">
                                <i data-lucide="shield" class="w-2.5 h-2.5"></i>
                                {{ str_replace('_', ' ', $adminRole) }}
                            </span>
                        </div>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" title="Sign out" class="p-2 text-slate-500 hover:text-red-400 transition-colors rounded-lg hover:bg-red-950/20">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                        </button>
                    </form>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="flex-grow p-6 md:p-8 overflow-y-auto">
                {{ $slot }}
            </main>
        </div>
    </div>

    <!-- Mobile Drawer JS & Menu Backdrop -->
    <div id="mobile-menu" class="fixed inset-0 z-40 hidden md:hidden flex">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" id="mobile-menu-overlay"></div>
        <div class="relative flex-1 flex flex-col max-w-xs w-full bg-[#0f172a] border-r border-slate-800 pt-5 pb-4">
            <div class="px-6 flex items-center justify-between pb-4 border-b border-slate-800">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-white">PS</div>
                    <span class="font-extrabold tracking-widest text-slate-200 uppercase text-xs">PlayerSaloons</span>
                </div>
                <button id="mobile-menu-close" class="p-2 text-slate-400 hover:text-white">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <nav class="flex-1 px-4 py-4 space-y-1.5 overflow-y-auto">
                @foreach($adminNavItems as $item)
                    @php
                        $isActive = request()->is(ltrim($item['url'], '/')) || (request()->is('admin') && $item['url'] === '/admin');
                    @endphp
                    <a href="{{ $item['url'] }}" wire:navigate 
                       class="flex items-center px-4 py-2.5 rounded-lg border text-sm transition-all duration-150
                       {{ $isActive 
                          ? 'bg-indigo-600/15 border-indigo-500/20 text-indigo-300 font-semibold' 
                          : 'bg-transparent border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-800/40 hover:border-slate-800' }}">
                        <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4 mr-3"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="p-4 border-t border-slate-800 bg-[#0b0f19]/50 space-y-2">
                <a href="/dashboard" wire:navigate class="flex items-center px-4 py-2 rounded-lg text-xs font-semibold text-slate-400 hover:text-white hover:bg-slate-800/60 transition-colors">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5 mr-2"></i>
                    <span>Player Terminal</span>
                </a>
                <div class="flex items-center justify-between px-4 py-2 bg-slate-900/40 rounded-lg border border-slate-800/50 text-xs">
                    <div class="truncate">
                        <p class="font-bold text-slate-350 truncate">{{ auth()->user()->username }}</p>
                        <p class="text-[9px] text-slate-500 uppercase tracking-wider mt-0.5">{{ str_replace('_', ' ', auth()->user()->roles->pluck('name')->first() ?? 'Staff') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @livewireScripts
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
            
            const toggle = document.getElementById('mobile-menu-toggle');
            const close = document.getElementById('mobile-menu-close');
            const overlay = document.getElementById('mobile-menu-overlay');
            const menu = document.getElementById('mobile-menu');

            if(toggle && menu) {
                toggle.addEventListener('click', () => {
                    menu.classList.remove('hidden');
                });
            }

            const closeMenu = () => {
                if(menu) menu.classList.add('hidden');
            };

            if(close) close.addEventListener('click', closeMenu);
            if(overlay) overlay.addEventListener('click', closeMenu);
        });
    </script>
</body>
</html>
