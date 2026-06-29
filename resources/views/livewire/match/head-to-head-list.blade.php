<div class="space-y-6" x-data="{ showInitiateDrawer: false }">
    <x-ui.toasts />

    <!-- Game Filter & Actions Panel -->
    <x-player.panel class="relative overflow-hidden border-fuchsia-500/30 shadow-[0_0_20px_rgba(217,70,239,0.05)]">
        <div class="absolute inset-0 bg-gradient-to-r from-purple-900/10 to-fuchsia-900/10 pointer-events-none"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <i data-lucide="swords" class="h-6 w-6 text-fuchsia-400"></i>
                    <h2 class="font-orbitron text-xl font-black uppercase tracking-widest text-white drop-shadow-[0_0_8px_rgba(217,70,239,0.5)]">Head-to-Head Duels</h2>
                </div>
                <p class="mt-2 max-w-2xl text-xs font-semibold uppercase tracking-wider text-zinc-400">
                    Find matches, lock stakes, and report results.
                </p>
            </div>

            <div class="flex w-full flex-col gap-4 lg:w-auto lg:flex-row lg:items-end">
                <div class="min-w-full lg:min-w-64">
                    <label for="gameId" class="text-[10px] font-black uppercase tracking-widest text-fuchsia-400 drop-shadow-[0_0_5px_rgba(217,70,239,0.8)]">Active Game Filter</label>
                    <div class="relative mt-2">
                        <select wire:model.live="gameId" id="gameId" class="w-full appearance-none rounded-xl border-2 border-fuchsia-500/40 bg-black/60 px-4 py-3.5 pr-10 font-orbitron text-sm font-black text-white shadow-[0_0_15px_rgba(217,70,239,0.2)] focus:border-fuchsia-400 focus:shadow-[0_0_20px_rgba(217,70,239,0.4)] focus:outline-none transition-all">
                            @foreach($games as $game)
                                <option value="{{ $game->id }}" class="bg-zinc-900">{{ $game->localizedName() }}</option>
                            @endforeach
                        </select>
                        <i data-lucide="chevron-down" class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-fuchsia-400"></i>
                    </div>
                    @error('gameId') <p class="mt-1 text-xs font-bold text-red-400">{{ $message }}</p> @enderror
                </div>

                <button type="button" @click="showInitiateDrawer = true" class="w-full lg:w-auto flex shrink-0 items-center justify-center gap-2 rounded-xl border border-fuchsia-400/50 bg-gradient-to-r from-fuchsia-600 to-purple-600 px-6 py-3.5 font-orbitron text-xs font-black uppercase tracking-widest text-white shadow-[0_0_15px_rgba(217,70,239,0.4)] transition-all hover:scale-[1.02] hover:from-fuchsia-500 hover:to-purple-500">
                    <i data-lucide="zap" class="h-4 w-4"></i>
                    Initiate Challenge
                </button>
            </div>
        </div>
    </x-player.panel>

    <div class="relative mb-6">
        <div class="flex space-x-1 overflow-x-auto rounded-xl bg-zinc-950/50 p-1 backdrop-blur-sm border border-white/5">
            @php
                $activeCount = $activeMatches->count();
            @endphp
            @foreach([
                ['key' => 'open', 'label' => 'Open Challenges', 'icon' => 'globe'],
                ['key' => 'active', 'label' => 'Active Duels', 'icon' => 'swords', 'badge' => $activeCount],
                ['key' => 'history', 'label' => 'History', 'icon' => 'history'],
            ] as $tab)
                <button type="button"
                        wire:click="$set('activeTab', '{{ $tab['key'] }}')"
                        class="relative flex min-w-max flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-xs font-black uppercase tracking-widest transition-all
                        {{ $activeTab === $tab['key'] 
                            ? 'bg-gradient-to-r from-purple-600 to-fuchsia-600 text-white shadow-[0_0_15px_rgba(192,38,211,0.4)]' 
                            : 'text-zinc-500 hover:bg-white/5 hover:text-zinc-300' }}">
                    <i data-lucide="{{ $tab['icon'] }}" class="h-4 w-4"></i>
                    {{ $tab['label'] }}
                    
                    @if(isset($tab['badge']) && $tab['badge'] > 0)
                        <span class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 font-mono text-[10px] font-black text-white shadow-[0_0_10px_rgba(239,68,68,0.8)]">
                            {{ $tab['badge'] }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>
    
    <div wire:loading.class="opacity-30 blur-sm grayscale-[50%] pointer-events-none scale-[0.99]" wire:target="activeTab, gameId" class="transition-all duration-300 relative">
        <div wire:loading.flex wire:target="activeTab, gameId" class="absolute inset-0 z-50 items-center justify-center">
            <i data-lucide="loader-2" class="h-10 w-10 animate-spin text-fuchsia-500 drop-shadow-[0_0_10px_rgba(217,70,239,0.8)]"></i>
        </div>

        @if($activeTab === 'open')
            <x-player.panel>
                <div class="mb-5 flex items-center justify-between border-b border-purple-500/10 pb-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="globe" class="h-5 w-5 text-purple-400"></i>
                        <h3 class="font-orbitron text-sm font-black uppercase tracking-wider text-zinc-200">Open Challenges</h3>
                    </div>
                    <span class="rounded bg-purple-950 px-2 py-0.5 font-mono text-[9px] font-bold text-purple-400">{{ $waitingChallenges->count() }} Open</span>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @forelse($waitingChallenges as $challenge)
                        @php
                            $gameName = $challenge->game->localizedName();
                        @endphp
                        <article class="rounded-xl border border-purple-500/10 bg-zinc-950/60 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 items-center justify-center rounded-full border border-purple-500/20 bg-purple-900/30 font-orbitron text-[9px] font-bold text-purple-400">
                                            {{ strtoupper(substr($challenge->creator?->username ?? '??', 0, 2)) }}
                                        </div>
                                        <span class="font-orbitron text-xs font-bold text-zinc-300">{{ $challenge->creator?->username }}</span>
                                    </div>
                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <span class="rounded border border-fuchsia-900/40 bg-fuchsia-950/40 px-2 py-0.5 font-orbitron text-[8px] font-bold text-fuchsia-400">{{ $gameName }}</span>
                                        <span class="font-mono text-xs font-black text-emerald-400">${{ number_format((float) $challenge->stake_amount, 2) }}</span>
                                    </div>
                                    <p class="mt-2 text-[10px] text-zinc-500">
                                        {{ $challenge->platform?->name ?? 'Any platform' }}
                                        @if($challenge->region)
                                            / {{ $challenge->region }}
                                        @endif
                                    </p>
                                </div>

                                @if($challenge->creator_user_id === auth()->id())
                                    <button wire:click="cancelChallenge({{ $challenge->id }})" class="rounded-lg border border-red-500/30 bg-red-950/40 px-3 py-1.5 font-orbitron text-[8px] font-black uppercase tracking-widest text-red-300 hover:bg-red-900">
                                        Cancel
                                    </button>
                                @else
                                    <button wire:click="acceptChallenge({{ $challenge->id }})" class="rounded-lg border border-purple-500/30 bg-purple-950 px-3 py-1.5 font-orbitron text-[8px] font-black uppercase tracking-widest text-purple-300 hover:bg-purple-900 hover:text-white">
                                        Accept
                                    </button>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-zinc-800 bg-zinc-950/40 p-8 text-center text-sm text-zinc-500 md:col-span-2 xl:col-span-3">
                            No open challenges for this game.
                        </div>
                    @endforelse
                </div>
            </x-player.panel>
        @elseif($activeTab === 'active')
            <x-player.panel>
                <div class="mb-5 flex items-center justify-between border-b border-purple-500/10 pb-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="swords" class="h-5 w-5 text-purple-400"></i>
                        <h3 class="font-orbitron text-sm font-black uppercase tracking-wider text-zinc-200">Active Duels (All Games)</h3>
                    </div>
                    <span class="rounded bg-purple-950 px-2 py-0.5 font-mono text-[9px] font-bold text-purple-400">{{ $activeMatches->count() }} Active</span>
                </div>

                <div class="space-y-4">
                    @forelse($activeMatches as $match)
                        @include('livewire.match.partials._head-to-head-match-card', ['match' => $match])
                    @empty
                        <div class="rounded-2xl border border-dashed border-zinc-800 bg-zinc-950/40 p-8 text-center text-sm text-zinc-500">
                            No active duels. Go initiate one!
                        </div>
                    @endforelse
                </div>
            </x-player.panel>
        @else
            <x-player.panel>
                <div class="mb-5 flex items-center justify-between border-b border-purple-500/10 pb-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="history" class="h-5 w-5 text-purple-400"></i>
                        <h3 class="font-orbitron text-sm font-black uppercase tracking-wider text-zinc-200">Duel History</h3>
                    </div>
                    <span class="rounded bg-purple-950 px-2 py-0.5 font-mono text-[9px] font-bold text-purple-400">{{ $historyMatches->count() }} Recent</span>
                </div>

                <div class="space-y-4">
                    @forelse($historyMatches as $match)
                        @include('livewire.match.partials._head-to-head-match-card', ['match' => $match])
                    @empty
                        <div class="rounded-2xl border border-dashed border-zinc-800 bg-zinc-950/40 p-8 text-center text-sm text-zinc-500">
                            No completed duels for this game yet.
                        </div>
                    @endforelse
                </div>
            </x-player.panel>
        @endif
    </div>

    <!-- Initiate Challenge Drawer -->
    <div x-show="showInitiateDrawer" x-cloak class="fixed inset-0 z-50 flex justify-end bg-black/70 backdrop-blur-sm" @click.self="showInitiateDrawer = false">
        <aside 
            x-show="showInitiateDrawer"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="h-full w-full max-w-md overflow-y-auto border-l border-fuchsia-500/30 bg-zinc-950 p-6 shadow-[0_0_50px_rgba(217,70,239,0.15)] flex flex-col"
        >
            <div class="flex items-start justify-between gap-4 border-b border-purple-500/10 pb-5">
                <div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="zap" class="h-5 w-5 text-fuchsia-400"></i>
                        <h3 class="font-orbitron text-lg font-black uppercase tracking-wider text-zinc-200">Initiate Challenge</h3>
                    </div>
                    <p class="mt-1 text-xs font-semibold text-zinc-450">Set your stakes and find an opponent.</p>
                </div>
                <button type="button" @click="showInitiateDrawer = false" class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-300 hover:text-white hover:bg-zinc-800 transition-colors">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <form wire:submit="createChallenge" class="mt-6 flex-1 flex flex-col gap-5">
                <div class="space-y-2">
                    <label class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Selected Game</label>
                    <div class="w-full rounded-xl border border-purple-500/20 bg-zinc-900 px-4 py-3 font-orbitron text-sm font-black text-purple-300">
                        {{ $games->firstWhere('id', $gameId)?->localizedName() ?? __('Unknown Game') }}
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="platformId" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Platform</label>
                    <select wire:model="platformId" id="platformId" class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 font-orbitron text-xs font-bold text-purple-300 focus:border-purple-500 focus:outline-none">
                        <option value="">Any Platform</option>
                        @foreach($platforms as $platform)
                            <option value="{{ $platform->id }}">{{ $platform->name }}</option>
                        @endforeach
                    </select>
                    @error('platformId') <p class="text-xs font-bold text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label for="gameHandle" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Game ID / Handle</label>
                    <input wire:model="gameHandle" id="gameHandle" type="text" placeholder="Riot ID, Steam, PSN, lobby name..."
                           class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 text-xs font-bold text-zinc-200 placeholder-zinc-700 focus:border-purple-500 focus:outline-none">
                    @error('gameHandle') <p class="text-xs font-bold text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label for="stakeAmount" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Stake</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 font-mono font-black text-emerald-400">$</span>
                        <input wire:model="stakeAmount" id="stakeAmount" type="number" min="1" max="1000" step="1"
                               class="w-full rounded-xl border border-emerald-500/30 bg-zinc-950 pl-8 pr-4 py-3 font-orbitron text-sm font-black text-emerald-400 shadow-[0_0_10px_rgba(16,185,129,0.1)] focus:border-emerald-500 focus:outline-none">
                    </div>
                    @error('stakeAmount') <p class="text-xs font-bold text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label for="matchTimerMinutes" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Timer</label>
                    <select wire:model="matchTimerMinutes" id="matchTimerMinutes" class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 font-orbitron text-xs font-bold text-purple-300 focus:border-purple-500 focus:outline-none">
                        <option value="">No strict timer</option>
                        <option value="15">15 min</option>
                        <option value="30">30 min</option>
                        <option value="60">60 min</option>
                    </select>
                    @error('matchTimerMinutes') <p class="text-xs font-bold text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label for="region" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Region</label>
                    <input wire:model="region" id="region" type="text" placeholder="Optional"
                           class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 text-xs font-bold text-zinc-200 placeholder-zinc-700 focus:border-purple-500 focus:outline-none">
                    @error('region') <p class="text-xs font-bold text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="mt-auto pt-6 flex flex-col gap-3">
                    <button type="button" wire:click="findDuel" @click="showInitiateDrawer = false" class="w-full rounded-xl border border-fuchsia-400/20 bg-gradient-to-r from-purple-600 via-fuchsia-600 to-cyan-500 py-3.5 font-orbitron text-xs font-black uppercase tracking-widest text-white shadow-[0_0_15px_rgba(217,70,239,0.35)] transition-all hover:scale-[1.02]">
                        Find Matching Duel
                    </button>
                    <button type="submit" @click="showInitiateDrawer = false" class="w-full rounded-xl border border-purple-500/30 bg-zinc-950 py-3.5 font-orbitron text-xs font-black uppercase tracking-widest text-purple-300 transition-colors hover:bg-zinc-900 hover:text-white">
                        Post Open Challenge
                    </button>
                </div>
            </form>
        </aside>
    </div>
</div>
