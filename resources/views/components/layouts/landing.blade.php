<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'PlayerSaloons' }}</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0a0718">
    <link rel="apple-touch-icon" href="/playersaloons_logo.webp">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <script src="https://unpkg.com/lucide@latest"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen overflow-x-hidden bg-zinc-950 font-sans text-zinc-100 antialiased selection:bg-cyan-500 selection:text-white">
    @include('components.layouts.partials.public-navigation')

    {{ $slot }}

    @livewireScripts
</body>
</html>
