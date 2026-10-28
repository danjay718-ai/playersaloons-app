<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-brand-icons />
    <x-site-fonts />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('GamersRival') }}</title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0a0718">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Tailwind CSS & Fonts -->
    <x-localization.interface-messages />
    <x-currency-config />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css" />
</head>
<body class="{{ auth()->guest() ? (config('features.landing.version') === 'v3' ? 'guest-experience' : 'gr-experience') : '' }} bg-[#05080f] text-zinc-100 min-h-screen flex flex-col font-sans selection:bg-[#c6ff3d] selection:text-[#05080f] antialiased overflow-x-clip">

    <!-- Global Background FX -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-cyan-500/5 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-lime-400/5 rounded-full blur-[120px]"></div>
    </div>

    @include('components.layouts.partials.public-navigation')

    <!-- Main Content Area -->
    <main id="guest-main" class="relative z-10 flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-8 md:pt-24 md:pb-12">
        {{ $slot }}
    </main>

    @include('components.layouts.partials.public-footer')
    @auth
        <x-logout-confirmation />
    @endauth
    <x-cookie-consent />

    @livewireScripts
</body>
</html>
