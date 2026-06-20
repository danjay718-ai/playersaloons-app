<div class="space-y-8" x-data="{ searchFilter: '', topPlayers: @js($topPlayers) }">
    @if(count($topPlayers) > 0)
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            @foreach(array_slice($topPlayers, 0, 3) as $player)
                <x-player.panel class="{{ $player['rank'] === 1 ? 'border-purple-500/40 bg-purple-950/20' : '' }}">
                    <div class="flex items-center justify-between">
                        <span class="font-orbitron text-xs font-black text-purple-400">#{{ $player['rank'] }}</span>
                        <span class="rounded-full border border-zinc-800 bg-zinc-950 px-2 py-1 font-orbitron text-[9px] font-bold uppercase text-zinc-400">
                            {{ $player['tier'] }}
                        </span>
                    </div>
                    <div class="mt-6 flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-full border border-purple-500/30 bg-zinc-950 font-orbitron text-sm font-black text-purple-300">
                            {{ $player['avatar'] }}
                        </div>
                        <div class="min-w-0">
                            <h3 class="truncate font-orbitron text-sm font-black uppercase tracking-wide text-white">{{ $player['name'] }}</h3>
                            <p class="mt-1 text-xs text-zinc-500">{{ $player['wins'] }} wins / {{ $player['losses'] }} losses</p>
                        </div>
                    </div>
                    <div class="mt-5 grid grid-cols-2 gap-3 border-t border-zinc-800 pt-4 text-xs">
                        <div>
                            <span class="block text-zinc-500">Win rate</span>
                            <span class="font-orbitron font-black text-cyan-400">{{ $player['winrate'] }}</span>
                        </div>
                        <div class="text-right">
                            <span class="block text-zinc-500">Prizes</span>
                            <span class="font-orbitron font-black text-emerald-400">{{ $player['cash'] }}</span>
                        </div>
                    </div>
                </x-player.panel>
            @endforeach
        </div>

        <x-player.panel>
            <div class="mb-4 flex flex-col gap-4 border-b border-purple-500/10 pb-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="award" class="h-5 w-5 text-purple-400"></i>
                    <h3 class="font-orbitron text-sm font-black uppercase tracking-wider text-zinc-200">Standings</h3>
                </div>

                <input type="text"
                       x-model="searchFilter"
                       placeholder="SEARCH PLAYER..."
                       class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-3.5 py-2 text-[10px] font-bold uppercase tracking-widest text-purple-300 focus:border-purple-500 focus:outline-none md:w-64">
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-[11px] font-medium">
                    <thead>
                        <tr class="border-b border-purple-500/10 font-orbitron font-bold uppercase tracking-widest text-zinc-500">
                            <th class="px-2 py-3.5">Rank</th>
                            <th class="px-2 py-3.5">Player</th>
                            <th class="px-2 py-3.5">Tier</th>
                            <th class="px-2 py-3.5 text-center">Wins</th>
                            <th class="px-2 py-3.5 text-center">Losses</th>
                            <th class="px-2 py-3.5 text-center">Win Rate</th>
                            <th class="px-2 py-3.5 text-right">Prizes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="player in topPlayers" :key="player.rank">
                            <tr x-show="player.name.toLowerCase().includes(searchFilter.toLowerCase())"
                                class="border-b border-purple-500/5 transition-colors hover:bg-purple-950/10">
                                <td class="px-2 py-3.5 font-orbitron text-xs font-black text-purple-400" x-text="'#' + player.rank"></td>
                                <td class="px-2 py-3.5 font-orbitron text-xs font-bold text-white" x-text="player.name"></td>
                                <td class="px-2 py-3.5 font-orbitron font-bold text-fuchsia-400" x-text="player.tier"></td>
                                <td class="px-2 py-3.5 text-center font-mono font-bold text-emerald-400" x-text="player.wins"></td>
                                <td class="px-2 py-3.5 text-center font-mono text-red-400" x-text="player.losses"></td>
                                <td class="px-2 py-3.5 text-center font-mono text-cyan-400" x-text="player.winrate"></td>
                                <td class="px-2 py-3.5 text-right font-orbitron font-black text-emerald-400" x-text="player.cash"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </x-player.panel>
    @else
        <div class="player-empty-state">
            <div class="player-empty-icon">
                <i data-lucide="award" class="h-10 w-10"></i>
            </div>
            <h3 class="font-orbitron text-xl font-black tracking-wider text-zinc-200">NO RANKED RESULTS YET</h3>
            <p class="mx-auto mt-2 max-w-sm text-sm font-medium text-zinc-500">
                Leaderboards populate from completed or forfeited matches with verified winners.
            </p>
        </div>
    @endif
</div>
