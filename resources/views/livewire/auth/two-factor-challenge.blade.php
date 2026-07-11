<div class="flex min-h-[70vh] items-center justify-center px-4 py-8">
    <div class="w-full max-w-md rounded-lg border border-zinc-800 bg-zinc-900 p-6 shadow-2xl md:p-8">
        <div class="mb-6 text-center">
            <i data-lucide="shield-check" class="mx-auto h-10 w-10 text-violet-400"></i>
            <h1 class="mt-4 text-xl font-black text-white font-orbitron">SECURITY CHECK</h1>
            <p class="mt-2 text-sm text-zinc-400">{{ $useRecoveryCode ? 'Enter one unused recovery code.' : 'Enter the six-digit code from your authenticator app.' }}</p>
        </div>
        <form wire:submit="verify" class="space-y-4">
            <input wire:model="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-4 py-3 text-center text-lg font-bold text-white focus:border-violet-500 focus:outline-none">
            @error('code')<p class="text-center text-xs text-red-400">{{ $message }}</p>@enderror
            <button class="w-full rounded-lg bg-violet-600 px-4 py-3 text-sm font-bold text-white hover:bg-violet-500">Verify identity</button>
        </form>
        <button wire:click="$toggle('useRecoveryCode')" wire:key="recovery-toggle-{{ (int) $useRecoveryCode }}" class="mt-5 w-full text-center text-xs text-zinc-400 hover:text-white">{{ $useRecoveryCode ? 'Use authenticator code' : 'Use a recovery code' }}</button>
    </div>
</div>
