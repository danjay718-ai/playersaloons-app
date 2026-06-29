<footer class="relative z-10 border-t border-zinc-900/50 bg-zinc-950/80 px-4 py-10 text-center">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 md:flex-row">
        <div class="flex items-center gap-3 opacity-60">
            <img src="/playersaloons_logo.webp" alt="Logo" class="h-6 w-auto grayscale">
            <span class="font-orbitron text-xs font-black uppercase tracking-widest text-zinc-400">PlayerSaloons</span>
        </div>
        <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-700">
            &copy; {{ date('Y') }} ALL RIGHTS RESERVED. OPERATED BY PLAYERSALOONS SYSTEMS.
        </p>
        <div class="flex flex-wrap justify-center gap-4 text-[10px] font-black uppercase tracking-widest text-zinc-600 sm:gap-8">
            <a href="/policies/terms-and-conditions" wire:navigate class="transition-colors hover:text-cyan-400">Terms</a>
            <a href="/policies/cookie-policy" wire:navigate class="transition-colors hover:text-cyan-400">Cookies</a>
            <a href="/policies/privacy-policy" wire:navigate class="transition-colors hover:text-cyan-400">Privacy</a>
            <a href="/policies/refund-and-cancellation-policy" wire:navigate class="transition-colors hover:text-cyan-400">Refunds</a>
            <a href="/policies/disclaimer" wire:navigate class="transition-colors hover:text-cyan-400">Disclaimer</a>
        </div>
    </div>
</footer>
