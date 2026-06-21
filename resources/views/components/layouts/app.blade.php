<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'PlayerSaloons' }}</title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0a0718">
    <link rel="apple-touch-icon" href="/playersaloons_logo.webp">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <script src="https://unpkg.com/lucide@latest"></script>

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

    @include('components.layouts.partials.public-navigation')

    <!-- Main Content Area -->
    <main class="relative z-10 flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12">
        {{ $slot }}
    </main>

    @include('components.layouts.partials.public-footer')

    @livewireScripts
</body>
</html>
