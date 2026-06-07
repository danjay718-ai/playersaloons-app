<div class="min-h-[70vh] flex items-center justify-center py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-zinc-900 border border-zinc-800 rounded-2xl p-6 md:p-8 shadow-2xl shadow-violet-950/10 text-center relative overflow-hidden">
        
        <div class="absolute -top-10 -right-10 w-40 h-40 bg-violet-600/10 rounded-full blur-3xl pointer-events-none"></div>
        
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-violet-950/50 border border-violet-900/50 text-violet-400">
            <i data-lucide="mail-check" class="w-8 h-8"></i>
        </div>

        <div class="space-y-2">
            <h2 class="text-2xl font-black font-orbitron tracking-wider text-white">
                VERIFY YOUR EMAIL
            </h2>
            <p class="text-sm text-zinc-400">
                To start competing, you need to verify your email address. In a real-world scenario, you would receive a validation link, but for this MVP, you can verify instantly below.
            </p>
        </div>

        <div class="pt-4">
            <button wire:click="verify" 
                class="w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-zinc-900 focus:ring-violet-500 transition-all duration-200 shadow-md shadow-violet-900/30">
                Verify Email Address
            </button>
        </div>

        <div class="pt-2">
            <a href="/dashboard" wire:navigate class="text-xs text-zinc-500 hover:text-zinc-300 transition-colors">
                Back to Dashboard
            </a>
        </div>
    </div>
</div>
