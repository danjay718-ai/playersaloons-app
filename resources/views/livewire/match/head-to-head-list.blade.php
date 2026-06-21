<div class="space-y-6">
    <x-ui.toasts />

    <x-player.panel>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <i data-lucide="swords" class="h-5 w-5 text-fuchsia-400"></i>
                    <h2 class="font-orbitron text-base font-black uppercase tracking-widest text-white">Head-to-Head Duels</h2>
                </div>
                <p class="mt-2 max-w-2xl text-xs font-semibold uppercase tracking-wider text-zinc-500">
                    Pick a game first. Open challenges, active duels, and history are filtered by that game.
                </p>
            </div>

            <div class="min-w-full lg:min-w-72">
                <label for="gameId" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Game Filter</label>
                <select wire:model.live="gameId" id="gameId" class="mt-1.5 w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 font-orbitron text-xs font-bold text-purple-300 focus:border-purple-500 focus:outline-none">
                    @foreach($games as $game)
                        <option value="{{ $game->id }}">{{ $game->translations->where('locale', 'en')->first()?->name ?? $game->slug }}</option>
                    @endforeach
                </select>
                @error('gameId') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>
    </x-player.panel>

    <div class="player-tabs">
        @foreach([
            ['key' => 'create', 'label' => 'Initiate Challenge', 'icon' => 'zap'],
            ['key' => 'open', 'label' => 'Open Challenges', 'icon' => 'globe'],
            ['key' => 'active', 'label' => 'Active Duels', 'icon' => 'swords'],
            ['key' => 'history', 'label' => 'History', 'icon' => 'history'],
        ] as $tab)
            <button type="button"
                    wire:click="$set('activeTab', '{{ $tab['key'] }}')"
                    class="player-tab inline-flex items-center gap-2 {{ $activeTab === $tab['key'] ? 'player-tab-active' : '' }}">
                <i data-lucide="{{ $tab['icon'] }}" class="h-4 w-4"></i>
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>

    @if($activeTab === 'create')
        <x-player.panel>
            <div class="mb-5 flex items-center gap-2 border-b border-purple-500/10 pb-3">
                <i data-lucide="zap" class="h-5 w-5 text-fuchsia-400"></i>
                <h3 class="font-orbitron text-sm font-black uppercase tracking-wider text-zinc-200">Initiate Challenge</h3>
            </div>

            <form wire:submit="createChallenge" class="grid gap-5 lg:grid-cols-2">
                <div class="space-y-2">
                    <label for="platformId" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Platform</label>
                    <select wire:model="platformId" id="platformId" class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 font-orbitron text-xs font-bold text-purple-300 focus:border-purple-500 focus:outline-none">
                        <option value="">Any Platform</option>
                        @foreach($platforms as $platform)
                            <option value="{{ $platform->id }}">{{ $platform->name }}</option>
                        @endforeach
                    </select>
                    @error('platformId') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label for="gameHandle" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Game ID / Handle</label>
                    <input wire:model="gameHandle" id="gameHandle" type="text" placeholder="Riot ID, Steam, PSN, lobby name..."
                           class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 text-xs font-bold text-zinc-200 placeholder-zinc-700 focus:border-purple-500 focus:outline-none">
                    @error('gameHandle') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label for="stakeAmount" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Stake</label>
                    <input wire:model="stakeAmount" id="stakeAmount" type="number" min="1" max="1000" step="1"
                           class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 font-orbitron text-xs font-bold text-emerald-400 focus:border-purple-500 focus:outline-none">
                    @error('stakeAmount') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label for="matchTimerMinutes" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Timer</label>
                    <select wire:model="matchTimerMinutes" id="matchTimerMinutes" class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 font-orbitron text-xs font-bold text-purple-300 focus:border-purple-500 focus:outline-none">
                        <option value="">No strict timer</option>
                        <option value="15">15 min</option>
                        <option value="30">30 min</option>
                        <option value="60">60 min</option>
                    </select>
                    @error('matchTimerMinutes') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2 lg:col-span-2">
                    <label for="region" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Region</label>
                    <input wire:model="region" id="region" type="text" placeholder="Optional"
                           class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 text-xs font-bold text-zinc-200 placeholder-zinc-700 focus:border-purple-500 focus:outline-none">
                    @error('region') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-3 pt-2 sm:grid-cols-2 lg:col-span-2">
                    <button type="button" wire:click="findDuel" class="rounded-xl border border-fuchsia-400/20 bg-gradient-to-r from-purple-600 via-fuchsia-600 to-cyan-500 py-3 font-orbitron text-xs font-black uppercase tracking-widest text-white shadow-[0_0_15px_rgba(217,70,239,0.35)] transition-all hover:from-purple-500 hover:to-cyan-400">
                        Find Matching Duel
                    </button>
                    <button type="submit" class="rounded-xl border border-purple-500/20 bg-zinc-950 py-3 font-orbitron text-xs font-black uppercase tracking-widest text-purple-300 transition-colors hover:bg-zinc-900 hover:text-white">
                        Post Open Challenge
                    </button>
                </div>
            </form>
        </x-player.panel>
    @elseif($activeTab === 'open')
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
                        $gameName = $challenge->game->translations->where('locale', 'en')->first()?->name ?? $challenge->game->slug;
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
                    <h3 class="font-orbitron text-sm font-black uppercase tracking-wider text-zinc-200">Active Duels</h3>
                </div>
                <span class="rounded bg-purple-950 px-2 py-0.5 font-mono text-[9px] font-bold text-purple-400">{{ $activeMatches->count() }} Active</span>
            </div>

            <div class="space-y-4">
                @forelse($activeMatches as $match)
                    @include('livewire.match.partials._head-to-head-match-card', ['match' => $match])
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-800 bg-zinc-950/40 p-8 text-center text-sm text-zinc-500">
                        No active duels for this game.
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
