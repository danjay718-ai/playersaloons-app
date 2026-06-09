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
<body class="bg-zinc-950 text-zinc-100 min-h-screen flex flex-col font-sans selection:bg-cyan-500 selection:text-white antialiased overflow-x-hidden">

    <!-- Global Background FX -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-cyan-500/5 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-violet-600/5 rounded-full blur-[120px]"></div>
    </div>

    <!-- Top Navigation (Glassmorphism) -->
    <header class="sticky top-0 z-50 bg-zinc-950/60 backdrop-blur-xl border-b border-zinc-900/50 px-4 sm:px-6 lg:px-8 py-3 md:py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <!-- Logo & Brand -->
            <a href="/" wire:navigate class="flex items-center group">
                <img src="/playersaloons_logo.webp" alt="PlayerSaloons Logo" class="h-10 md:h-12 w-auto object-contain transition-transform duration-500 group-hover:scale-110">
            </a>

            <!-- Desktop Nav Links -->
            <nav class="hidden md:flex items-center space-x-8">
                <a href="/tournaments" wire:navigate class="text-[10px] font-black uppercase tracking-[0.2em] {{ request()->is('tournaments*') ? 'text-cyan-400' : 'text-zinc-500 hover:text-white' }} transition-colors duration-300 flex items-center space-x-2">
                    <i data-lucide="trophy" class="w-4 h-4"></i>
                    <span>Tournaments</span>
                </a>
                <a href="/teams" wire:navigate class="text-[10px] font-black uppercase tracking-[0.2em] {{ request()->is('teams*') ? 'text-fuchsia-400' : 'text-zinc-500 hover:text-white' }} transition-colors duration-300 flex items-center space-x-2">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    <span>Teams</span>
                </a>
                @auth
                    <a href="/dashboard" wire:navigate class="text-[10px] font-black uppercase tracking-[0.2em] {{ request()->is('dashboard*') ? 'text-indigo-400' : 'text-zinc-500 hover:text-white' }} transition-colors duration-300 flex items-center space-x-2">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                        <span>Dashboard</span>
                    </a>
                @endauth
            </nav>

            <!-- Right: Auth actions -->
            <div class="flex items-center space-x-4">
                @auth
                    <!-- Wallet quick info -->
                    <a href="/wallet" wire:navigate class="hidden sm:flex items-center space-x-3 bg-zinc-900/50 border border-zinc-800 hover:border-emerald-500/50 rounded-full px-5 py-2 transition-all duration-300">
                        <i data-lucide="wallet" class="w-4 h-4 text-emerald-400"></i>
                        <span class="text-[10px] font-black text-emerald-400 font-orbitron tracking-wider">
                            ${{ number_format((float)(auth()->user()->wallet?->cached_balance ?? 0.00), 2) }}
                        </span>
                    </a>

                    <!-- User Actions -->
                    <div class="flex items-center space-x-3">
                        <a href="/profile" wire:navigate class="flex items-center space-x-2 bg-zinc-900/50 hover:bg-zinc-800 border border-zinc-800 rounded-full py-1.5 px-1.5 pr-4 transition-all duration-300">
                            <div class="w-7 h-7 bg-zinc-950 rounded-full flex items-center justify-center text-zinc-500">
                                <i data-lucide="user" class="w-4 h-4"></i>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-zinc-300 hidden md:inline">
                                {{ auth()->user()->profile?->display_name ?? auth()->user()->username }}
                            </span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="inline m-0">
                            @csrf
                            <button type="submit" class="bg-zinc-900/50 hover:bg-red-500/10 border border-zinc-800 hover:border-red-500/30 text-zinc-500 hover:text-red-400 p-2.5 rounded-full transition-all duration-300" title="Logout">
                                <i data-lucide="log-out" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                @else
                    <div class="flex items-center space-x-4">
                        <a href="/login" wire:navigate class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400 hover:text-white transition-colors duration-300">
                            Sign In
                        </a>
                        <a href="/register" wire:navigate class="bg-white text-black px-6 py-2 rounded-full text-[10px] font-black uppercase tracking-[0.2em] hover:bg-cyan-400 transition-all duration-300 shadow-lg shadow-white/5">
                            Register
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="relative z-10 flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12 pb-28 md:pb-12">
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="relative z-10 hidden md:block bg-zinc-950/80 border-t border-zinc-900/50 py-12 px-4 text-center">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between">
            <div class="flex items-center space-x-3 opacity-50">
                <img src="/playersaloons_logo.webp" alt="Logo" class="h-6 w-auto grayscale">
                <span class="text-xs font-black font-orbitron tracking-widest text-zinc-400 uppercase">PlayerSaloons</span>
            </div>
            <p class="text-[10px] font-bold text-zinc-700 uppercase tracking-widest mt-4 md:mt-0">
                &copy; {{ date('Y') }} ALL RIGHTS RESERVED. OPERATED BY PLAYERSALOONS SYSTEMS.
            </p>
            <div class="flex space-x-8 mt-6 md:mt-0 text-[10px] font-black text-zinc-600 uppercase tracking-widest">
                <a href="#" class="hover:text-cyan-400 transition-colors">Terms</a>
                <a href="#" class="hover:text-cyan-400 transition-colors">Privacy</a>
                <a href="#" class="hover:text-cyan-400 transition-colors">Support</a>
            </div>
        </div>
    </footer>

    <!-- Bottom Mobile Navbar (Ultra Neon) -->
    <div class="fixed bottom-0 left-0 right-0 z-50 bg-zinc-950/80 backdrop-blur-2xl border-t border-zinc-900/50 md:hidden flex justify-around items-center h-20 pb-4 pt-2 px-4">
        <a href="/tournaments" wire:navigate class="flex flex-col items-center justify-center w-14 text-center group">
            <i data-lucide="trophy" class="w-5 h-5 transition-all duration-300 {{ request()->is('tournaments*') ? 'text-cyan-400 scale-110 drop-shadow-[0_0_8px_rgba(34,211,238,0.5)]' : 'text-zinc-600 group-hover:text-zinc-400' }}"></i>
            <span class="text-[8px] mt-1.5 font-black uppercase tracking-widest {{ request()->is('tournaments*') ? 'text-cyan-400' : 'text-zinc-600' }}">Events</span>
        </a>
        <a href="/teams" wire:navigate class="flex flex-col items-center justify-center w-14 text-center group">
            <i data-lucide="users" class="w-5 h-5 transition-all duration-300 {{ request()->is('teams*') ? 'text-fuchsia-400 scale-110 drop-shadow-[0_0_8px_rgba(192,38,211,0.5)]' : 'text-zinc-600 group-hover:text-zinc-400' }}"></i>
            <span class="text-[8px] mt-1.5 font-black uppercase tracking-widest {{ request()->is('teams*') ? 'text-fuchsia-400' : 'text-zinc-600' }}">Clans</span>
        </a>
        @auth
            <a href="/dashboard" wire:navigate class="flex flex-col items-center justify-center w-16 h-16 -mt-10 bg-gradient-to-br from-cyan-500 to-violet-600 rounded-2xl shadow-[0_8px_20px_-5px_rgba(124,77,255,0.6)] border border-white/20">
                <i data-lucide="layout-dashboard" class="w-6 h-6 text-white"></i>
            </a>
            <a href="/wallet" wire:navigate class="flex flex-col items-center justify-center w-14 text-center group">
                <i data-lucide="wallet" class="w-5 h-5 transition-all duration-300 {{ request()->is('wallet*') ? 'text-emerald-400 scale-110 drop-shadow-[0_0_8px_rgba(16,185,129,0.5)]' : 'text-zinc-600 group-hover:text-zinc-400' }}"></i>
                <span class="text-[8px] mt-1.5 font-black uppercase tracking-widest {{ request()->is('wallet*') ? 'text-emerald-400' : 'text-zinc-600' }}">Bank</span>
            </a>
            <a href="/profile" wire:navigate class="flex flex-col items-center justify-center w-14 text-center group">
                <i data-lucide="user" class="w-5 h-5 transition-all duration-300 {{ request()->is('profile*') ? 'text-white scale-110 drop-shadow-[0_0_8px_rgba(255,255,255,0.3)]' : 'text-zinc-600 group-hover:text-zinc-400' }}"></i>
                <span class="text-[8px] mt-1.5 font-black uppercase tracking-widest {{ request()->is('profile*') ? 'text-white' : 'text-zinc-600' }}">Profile</span>
            </a>
        @else
            <a href="/login" wire:navigate class="flex flex-col items-center justify-center w-14 text-center group">
                <i data-lucide="log-in" class="w-5 h-5 transition-all duration-300 {{ request()->is('login*') ? 'text-white scale-110' : 'text-zinc-600 group-hover:text-zinc-400' }}"></i>
                <span class="text-[8px] mt-1.5 font-black uppercase tracking-widest {{ request()->is('login*') ? 'text-white' : 'text-zinc-600' }}">Enter</span>
            </a>
            <a href="/register" wire:navigate class="flex flex-col items-center justify-center w-14 text-center group">
                <i data-lucide="user-plus" class="w-5 h-5 transition-all duration-300 {{ request()->is('register*') ? 'text-white scale-110' : 'text-zinc-600 group-hover:text-zinc-400' }}"></i>
                <span class="text-[8px] mt-1.5 font-black uppercase tracking-widest {{ request()->is('register*') ? 'text-white' : 'text-zinc-600' }}">Join</span>
            </a>
        @endauth
    </div>

    @livewireScripts

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</body>
</html>
