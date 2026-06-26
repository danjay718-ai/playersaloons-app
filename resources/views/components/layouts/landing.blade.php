<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'PlayerSaloons | Play. Win. Cash.' }}</title>
    <meta name="description" content="The ultimate battleground for competitive gamers. Join high-stakes tournaments, dominate the bracket, and secure instant payouts.">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#050311">
    <link rel="apple-touch-icon" href="/playersaloons_logo.webp">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <!-- Google Fonts: Orbitron + Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;800;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen overflow-x-hidden bg-[#050311] font-sans text-zinc-100 antialiased selection:bg-cyan-500 selection:text-white">

    @include('components.layouts.partials.public-navigation')

    {{ $slot }}

    @livewireScripts
</body>
</html>
