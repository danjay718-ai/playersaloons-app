<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('GamersRival') }}</title>
    <meta name="theme-color" content="#090716">
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <x-brand-icons />
    <x-site-fonts />
    <x-localization.interface-messages />
    <x-currency-config />
    <script src="https://unpkg.com/lucide@latest"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="guest-experience guest-auth-page">
    <header class="guest-auth-nav">
        <a href="/" aria-label="{{ __('GamersRival home') }}"><x-public-brand /></a>
        <div><a href="/" class="guest-auth-back">← {{ __('Back to arena') }}</a>
            <button type="button" class="pwa-install-btn guest-install" data-pwa-install-desktop data-pwa-install-mobile><i data-lucide="download" aria-hidden="true"></i><span data-pwa-install-label>{{ __('Install App') }}</span></button>
        </div>
    </header>
    <main id="guest-main">{{ $slot }}</main>
    @include('components.layouts.partials.guest-footer')
    <x-pwa-update-prompt />
    @livewireScripts
</body>
</html>
