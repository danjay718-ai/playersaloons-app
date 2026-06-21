<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PlayerSaloons | Play. Win. Cash.</title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0a0718">
    <link rel="apple-touch-icon" href="/playersaloons_logo.webp">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Tailwind CSS & Fonts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-zinc-950 text-zinc-100 min-h-screen font-sans selection:bg-cyan-500 selection:text-white antialiased overflow-x-hidden">

    <!-- Background FX -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-cyan-500/10 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-violet-600/10 rounded-full blur-[120px]"></div>
        <div class="absolute top-[20%] right-[10%] w-[30%] h-[30%] bg-fuchsia-500/5 rounded-full blur-[100px]"></div>
        <div class="absolute inset-0 bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-20 mix-blend-overlay"></div>
    </div>

    @include('components.layouts.partials.public-navigation')

    <!-- Hero Section -->
    <main class="relative z-10 flex flex-col items-center justify-center pt-20 pb-32 px-6 text-center max-w-5xl mx-auto">
        <div class="inline-flex items-center space-x-2 bg-zinc-900/50 backdrop-blur-md border border-zinc-800 rounded-full px-4 py-1.5 mb-8 animate-fade-in-down">
            <span class="flex h-2 w-2 rounded-full bg-cyan-500 animate-pulse"></span>
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-cyan-400">Phase 1 MVP is Live</span>
        </div>

        <h1 class="text-6xl md:text-9xl font-black font-orbitron tracking-tighter text-white leading-[0.9] mb-8 filter drop-shadow-[0_0_30px_rgba(255,255,255,0.1)]">
            PLAY. WIN. <br/>
            <span class="bg-gradient-to-r from-cyan-400 via-violet-500 to-fuchsia-500 bg-clip-text text-transparent">CASH OUT.</span>
        </h1>

        <p class="text-lg md:text-xl text-zinc-500 max-w-2xl font-medium leading-relaxed mb-12">
            The ultimate battleground for competitive gamers. Join high-stakes tournaments, dominate the bracket, and secure instant payouts.
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-6 w-full sm:w-auto mt-8">
            <a href="/tournaments" class="w-full sm:w-64 flex items-center justify-center space-x-3 bg-gradient-to-r from-cyan-600 to-violet-600 hover:from-cyan-500 hover:to-violet-500 text-white font-black py-5 rounded-2xl transition-all duration-300 shadow-[0_15px_35px_-10px_rgba(124,77,255,0.5)] uppercase tracking-[0.2em] text-xs transform hover:scale-105 active:scale-95">
                <i data-lucide="trophy" class="w-5 h-5"></i>
                <span>Explore Tournaments</span>
            </a>
            <a href="/register" class="w-full sm:w-64 flex items-center justify-center space-x-3 bg-zinc-900/50 backdrop-blur-md border border-zinc-800 hover:border-zinc-600 text-white font-black py-5 rounded-2xl transition-all duration-300 uppercase tracking-[0.2em] text-xs transform hover:scale-105 active:scale-95">
                <span>Create Account</span>
            </a>
        </div>

        <!-- Floating Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mt-32 w-full pt-16 border-t border-zinc-900/50">
            <div class="space-y-1">
                <span class="block text-3xl font-black text-white font-orbitron">100%</span>
                <span class="block text-[10px] font-bold text-zinc-600 uppercase tracking-widest">Secure Payouts</span>
            </div>
            <div class="space-y-1">
                <span class="block text-3xl font-black text-white font-orbitron">0.1s</span>
                <span class="block text-[10px] font-bold text-zinc-600 uppercase tracking-widest">Match Sync</span>
            </div>
            <div class="space-y-1">
                <span class="block text-3xl font-black text-white font-orbitron">24/7</span>
                <span class="block text-[10px] font-bold text-zinc-600 uppercase tracking-widest">Global Support</span>
            </div>
            <div class="space-y-1">
                <span class="block text-3xl font-black text-white font-orbitron">KYC</span>
                <span class="block text-[10px] font-bold text-zinc-600 uppercase tracking-widest">Verified Players</span>
            </div>
        </div>
    </main>

    @include('components.layouts.partials.public-footer')
</body>
</html>
