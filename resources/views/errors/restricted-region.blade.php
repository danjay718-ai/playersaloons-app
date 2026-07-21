<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Region Restricted | PlayerSaloons</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-[#05030c] text-zinc-100 min-h-screen font-sans antialiased flex flex-col items-center justify-center relative cyber-grid">
    <div class="absolute inset-0 bg-gradient-to-br from-violet-900/10 via-[#05030c] to-cyan-900/10 z-0"></div>
    <div class="z-10 text-center max-w-lg px-6">
        <div class="mx-auto mb-8 flex h-20 w-20 items-center justify-center rounded-full bg-red-900/20 border border-red-500/30">
            <svg class="h-10 w-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>
        <h1 class="mb-4 text-4xl font-bold tracking-tight text-white sm:text-5xl">Access Denied</h1>
        <p class="mb-6 text-lg text-zinc-400">
            {{ $message }}
        </p>
        <p class="text-sm font-semibold text-zinc-500 bg-zinc-900/50 inline-block px-4 py-2 rounded-lg border border-zinc-800">
            Detected Region: {{ $countryName }} ({{ $countryCode }})
        </p>
        <div class="mt-10">
            <a href="mailto:support@playersaloons.com" class="text-sm text-cyan-400 hover:text-cyan-300 underline underline-offset-4">Contact Support</a>
        </div>
    </div>
</body>
</html>
