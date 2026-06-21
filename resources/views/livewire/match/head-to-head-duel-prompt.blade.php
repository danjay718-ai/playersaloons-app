<div wire:poll.10s>
    @if($prompt)
        <div class="fixed inset-0 z-[80] flex items-center justify-center bg-black/75 px-4 backdrop-blur-sm">
            <div class="w-full max-w-md overflow-hidden rounded-2xl border border-fuchsia-500/30 bg-[#0a0718] shadow-[0_0_45px_rgba(217,70,239,0.25)]">
                <div class="border-b border-fuchsia-500/15 bg-fuchsia-500/10 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-orbitron text-[9px] font-black uppercase tracking-widest text-cyan-300">Head-to-Head Alert</p>
                            <h3 class="mt-2 font-orbitron text-base font-black uppercase tracking-widest text-white">{{ $prompt['title'] }}</h3>
                        </div>
                        <button type="button"
                                wire:click="dismiss('{{ $prompt['type'] }}', {{ $prompt['id'] }})"
                                class="rounded-lg border border-zinc-800 bg-zinc-950 p-2 text-zinc-500 transition-colors hover:border-zinc-600 hover:text-white"
                                aria-label="Dismiss head-to-head alert">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>

                <div class="space-y-4 p-5">
                    <p class="text-sm font-semibold leading-relaxed text-zinc-300">{{ $prompt['message'] }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950 p-3">
                            <p class="font-orbitron text-[8px] font-black uppercase tracking-widest text-zinc-600">Game</p>
                            <p class="mt-1 truncate text-xs font-bold text-cyan-300">{{ $prompt['game'] }}</p>
                        </div>
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950 p-3">
                            <p class="font-orbitron text-[8px] font-black uppercase tracking-widest text-zinc-600">Stake</p>
                            <p class="mt-1 text-xs font-black text-emerald-300">${{ $prompt['stake'] }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <a href="/head-to-head" wire:navigate class="inline-flex items-center justify-center gap-2 rounded-xl border border-fuchsia-400/30 bg-fuchsia-500/15 px-4 py-3 font-orbitron text-[10px] font-black uppercase tracking-widest text-fuchsia-100 hover:bg-fuchsia-500/25">
                            <i data-lucide="swords" class="h-4 w-4"></i>
                            Open H2H
                        </a>
                        <button type="button"
                                wire:click="dismiss('{{ $prompt['type'] }}', {{ $prompt['id'] }})"
                                class="rounded-xl border border-zinc-800 bg-zinc-950 px-4 py-3 font-orbitron text-[10px] font-black uppercase tracking-widest text-zinc-400 hover:bg-zinc-900 hover:text-white">
                            Later
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
