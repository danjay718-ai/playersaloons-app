@php
    $category = str($block->category)->replace('_', ' ')->title();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Restricted | PlayerSaloons</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-[#05030c] p-5 font-sans text-zinc-100">
    <main class="w-full max-w-lg overflow-hidden rounded-3xl border border-red-500/25 bg-zinc-950 shadow-2xl shadow-red-950/30">
        <div class="border-b border-red-500/20 bg-red-950/30 p-6 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl border border-red-500/30 bg-red-500/10 text-red-300">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-7 w-7"><path d="M18 8a6 6 0 0 0-12 0v3"/><path d="M6 11h12v9H6z"/><path d="m9 15 6 0"/></svg>
            </div>
            <h1 class="mt-4 font-orbitron text-xl font-black uppercase tracking-wider text-white">Account Restricted</h1>
            <p class="mt-2 text-sm leading-relaxed text-zinc-400">Your player access is temporarily restricted following a compliance review.</p>
        </div>

        <div class="space-y-5 p-6">
            <dl class="space-y-4 text-sm">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/70 p-4"><dt class="text-[10px] font-black uppercase tracking-widest text-zinc-500">Category</dt><dd class="mt-1 font-bold text-red-200">{{ $category }}</dd></div>
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/70 p-4"><dt class="text-[10px] font-black uppercase tracking-widest text-zinc-500">Reason</dt><dd class="mt-1 leading-relaxed text-zinc-200">{{ $block->reason }}</dd></div>
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/70 p-4"><dt class="text-[10px] font-black uppercase tracking-widest text-zinc-500">Restriction period</dt><dd class="mt-1 text-zinc-200">{{ $block->expires_at ? 'Until '.$block->expires_at->format('M j, Y g:i A T') : 'Until further notice' }}</dd></div>
            </dl>
            <p class="text-center text-xs leading-relaxed text-zinc-500">If you believe this restriction was applied in error, contact support and provide the details above.</p>
            <button type="button" onclick="document.getElementById('logout-confirmation')?.showModal()" class="w-full rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-3 text-sm font-black text-zinc-200 transition hover:border-zinc-500 hover:bg-zinc-800">{{ __('Logout') }}</button>
        </div>
    </main>
    <x-logout-confirmation />
</body>
</html>
