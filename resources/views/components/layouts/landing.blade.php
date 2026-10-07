<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-brand-icons />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('GamersRival | Play. Win. Cash.') }}</title>
    <meta name="description" content="{{ __('The ultimate battleground for competitive gamers. Join high-stakes tournaments, dominate the bracket, and secure instant payouts.') }}">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#050311">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <x-site-fonts />

    <script src="https://unpkg.com/lucide@latest"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css" />
</head>
<body class="min-h-screen overflow-x-hidden bg-[#050311] font-sans text-zinc-100 antialiased selection:bg-cyan-500 selection:text-white">

    @include('components.layouts.partials.public-navigation')

    {{ $slot }}

    @include('components.layouts.partials.public-footer')
    <x-pwa-update-prompt />

    @livewireScripts
</body>
</html>
