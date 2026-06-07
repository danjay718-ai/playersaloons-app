<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'PlayerSaloons' }}</title>

    <!-- Tailwind CSS & Fonts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-zinc-950 text-zinc-100 min-h-screen flex flex-col font-sans selection:bg-violet-600 selection:text-white antialiased">

    <!-- Top Desktop Navbar -->
    <header class="sticky top-0 z-40 bg-zinc-950/80 backdrop-blur-md border-b border-zinc-800/80 px-4 sm:px-6 lg:px-8 py-3 md:py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <!-- Logo & Brand -->
            <a href="/" wire:navigate class="flex items-center space-x-3 group">
                <img src="/playersaloons_logo.webp" alt="PlayerSaloons Logo" class="h-10 md:h-12 w-auto object-contain transition-transform duration-300 group-hover:scale-105">
                <span class="text-xl md:text-2xl font-black font-orbitron tracking-wider bg-gradient-to-r from-violet-400 via-fuchsia-400 to-indigo-400 bg-clip-text text-transparent">
                    PLAYERSALOONS
                </span>
            </a>

            <!-- Desktop Nav Links -->
            <nav class="hidden md:flex items-center space-x-8 text-sm font-semibold">
                <a href="/tournaments" wire:navigate class="text-zinc-300 hover:text-white transition-colors duration-200 flex items-center space-x-2">
                    <i data-lucide="trophy" class="w-4 h-4 text-violet-400"></i>
                    <span>Tournaments</span>
                </a>
                <a href="/teams" wire:navigate class="text-zinc-300 hover:text-white transition-colors duration-200 flex items-center space-x-2">
                    <i data-lucide="users" class="w-4 h-4 text-fuchsia-400"></i>
                    <span>Teams</span>
                </a>
                @auth
                    <a href="/dashboard" wire:navigate class="text-zinc-300 hover:text-white transition-colors duration-200 flex items-center space-x-2">
                        <i data-lucide="layout-dashboard" class="w-4 h-4 text-indigo-400"></i>
                        <span>Dashboard</span>
                    </a>
                @endauth
            </nav>

            <!-- Right: Auth actions or Profile Info -->
            <div class="flex items-center space-x-4">
                @auth
                    <!-- Wallet quick info -->
                    <a href="/wallet" wire:navigate class="hidden sm:flex items-center space-x-2 bg-zinc-900 border border-zinc-800 hover:border-zinc-700 rounded-full px-4 py-1.5 transition-all duration-200">
                        <i data-lucide="wallet" class="w-4 h-4 text-emerald-400"></i>
                        <span class="text-xs text-zinc-400 font-medium">Balance:</span>
                        <span class="text-sm font-bold text-emerald-400 font-orbitron">
                            ${{ number_format((float)(auth()->user()->wallet?->cached_balance ?? 0.00), 2) }}
                        </span>
                    </a>

                    <!-- User Actions / Profile Link -->
                    <div class="flex items-center space-x-3">
                        <a href="/profile" wire:navigate class="flex items-center space-x-2 text-zinc-300 hover:text-white transition-colors duration-200 bg-zinc-900/60 hover:bg-zinc-900 border border-zinc-800 rounded-full py-1.5 px-3">
                            <i data-lucide="user" class="w-4 h-4 text-zinc-400"></i>
                            <span class="text-xs md:text-sm font-medium hidden md:inline">
                                {{ auth()->user()->profile?->display_name ?? auth()->user()->username }}
                            </span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="inline m-0">
                            @csrf
                            <button type="submit" class="bg-zinc-900 hover:bg-red-950/40 border border-zinc-850 hover:border-red-900/60 text-zinc-400 hover:text-red-400 p-2 rounded-full transition-all duration-200" title="Logout">
                                <i data-lucide="log-out" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                @else
                    <div class="flex items-center space-x-3 text-sm font-semibold">
                        <a href="/login" wire:navigate class="text-zinc-400 hover:text-white px-3 py-2 transition-colors duration-200">
                            Sign In
                        </a>
                        <a href="/register" wire:navigate class="bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white px-4.5 py-2 rounded-full transition-all duration-200 shadow-md shadow-violet-900/20">
                            Register
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-10 pb-24 md:pb-10">
        {{ $slot }}
    </main>

    <!-- Footer (Desktop-only to avoid duplicate nav on mobile) -->
    <footer class="hidden md:block bg-zinc-950 border-t border-zinc-900 py-8 px-4 text-center text-xs text-zinc-600">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between">
            <p>&copy; {{ date('Year') ?? '2026' }} PlayerSaloons. All rights reserved.</p>
            <div class="flex space-x-6 mt-4 md:mt-0">
                <a href="#" class="hover:text-zinc-400 transition-colors">Terms of Service</a>
                <a href="#" class="hover:text-zinc-400 transition-colors">Privacy Policy</a>
                <a href="#" class="hover:text-zinc-400 transition-colors">Rules & Regulations</a>
            </div>
        </div>
    </footer>

    <!-- Bottom Mobile Navbar (sticky navigation at bottom for mobile layout) -->
    <div class="fixed bottom-0 left-0 right-0 z-50 bg-zinc-950/90 backdrop-blur-md border-t border-zinc-800 md:hidden flex justify-around items-center h-16 py-2 px-3 shadow-lg shadow-black/80">
        <a href="/tournaments" wire:navigate class="flex flex-col items-center justify-center w-12 text-center {{ request()->is('tournaments*') ? 'text-violet-400' : 'text-zinc-500 hover:text-zinc-300' }}">
            <i data-lucide="trophy" class="w-5 h-5"></i>
            <span class="text-[10px] mt-1 font-medium">Tourneys</span>
        </a>
        <a href="/teams" wire:navigate class="flex flex-col items-center justify-center w-12 text-center {{ request()->is('teams*') ? 'text-fuchsia-400' : 'text-zinc-500 hover:text-zinc-300' }}">
            <i data-lucide="users" class="w-5 h-5"></i>
            <span class="text-[10px] mt-1 font-medium">Teams</span>
        </a>
        @auth
            <a href="/dashboard" wire:navigate class="flex flex-col items-center justify-center w-12 text-center {{ request()->is('dashboard*') ? 'text-indigo-400' : 'text-zinc-500 hover:text-zinc-300' }}">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                <span class="text-[10px] mt-1 font-medium">Dashboard</span>
            </a>
            <a href="/wallet" wire:navigate class="flex flex-col items-center justify-center w-12 text-center {{ request()->is('wallet*') ? 'text-emerald-400' : 'text-zinc-500 hover:text-zinc-300' }}">
                <i data-lucide="wallet" class="w-5 h-5"></i>
                <span class="text-[10px] mt-1 font-medium">Wallet</span>
            </a>
            <a href="/profile" wire:navigate class="flex flex-col items-center justify-center w-12 text-center {{ request()->is('profile*') ? 'text-zinc-300' : 'text-zinc-500 hover:text-zinc-300' }}">
                <i data-lucide="user" class="w-5 h-5"></i>
                <span class="text-[10px] mt-1 font-medium">Profile</span>
            </a>
        @else
            <a href="/login" wire:navigate class="flex flex-col items-center justify-center w-12 text-center {{ request()->is('login*') ? 'text-violet-400' : 'text-zinc-500 hover:text-zinc-300' }}">
                <i data-lucide="log-in" class="w-5 h-5"></i>
                <span class="text-[10px] mt-1 font-medium">Sign In</span>
            </a>
            <a href="/register" wire:navigate class="flex flex-col items-center justify-center w-12 text-center {{ request()->is('register*') ? 'text-violet-400' : 'text-zinc-500 hover:text-zinc-300' }}">
                <i data-lucide="user-plus" class="w-5 h-5"></i>
                <span class="text-[10px] mt-1 font-medium">Register</span>
            </a>
        @endauth
    </div>

    @livewireScripts

    <!-- Lucide Icons Loading and Re-triggering on Navigation -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
        });
        document.addEventListener('livewire:navigated', () => {
            lucide.createIcons();
        });
    </script>
</body>
</html>
