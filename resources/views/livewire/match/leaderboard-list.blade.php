<div class="space-y-8" x-data="{
    searchFilter: '',
    topPlayers: [
        { rank: 1, name: 'SaloonsKing', level: 64, wins: 142, losses: 28, cash: '$1,820.00', tier: 'Challenger', avatar: 'SK' },
        { rank: 2, name: 'ViperZero', level: 45, wins: 98, losses: 18, cash: '$1,240.00', tier: 'Challenger', avatar: 'VZ' },
        { rank: 3, name: 'ShadowBlade', level: 52, wins: 110, losses: 26, cash: '$1,190.00', tier: 'Challenger', avatar: 'SB' },
        { rank: 4, name: 'HyperDrift', level: 38, wins: 76, losses: 22, cash: '$840.00', tier: 'Diamond', avatar: 'HD' },
        { rank: 5, name: 'GamerGod', level: 42, wins: 80, losses: 28, cash: '$780.00', tier: 'Diamond', avatar: 'GG' },
        { rank: 6, name: 'NeonSpecter', level: 38, wins: 72, losses: 26, cash: '$690.00', tier: 'Diamond', avatar: 'NS' },
        { rank: 7, name: 'CyberKnight', level: 32, wins: 56, losses: 22, cash: '$490.00', tier: 'Platinum', avatar: 'CK' }
    ]
}">
    
    <!-- Podium Grid Top 3 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end pt-4">
        
        <!-- 2nd Place Podium Card -->
        <div class="order-2 md:order-1 bg-[#0c081d]/80 border border-purple-500/15 rounded-2xl p-5 text-center flex flex-col justify-between h-[280px] relative">
            <div class="absolute top-2 left-2 text-xs font-black font-orbitron text-zinc-500">#2</div>
            <div class="space-y-4">
                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-zinc-300 to-zinc-500 p-[1.5px] mx-auto shadow-[0_0_15px_rgba(200,200,200,0.25)]">
                    <div class="w-full h-full bg-[#0a0718] rounded-full flex items-center justify-center text-zinc-300 text-lg font-bold font-orbitron">VZ</div>
                </div>
                <div>
                    <h4 class="text-sm font-black font-orbitron text-zinc-200 tracking-wider">VIPERZERO</h4>
                    <span class="text-[9px] font-bold text-zinc-400 bg-zinc-950 px-2 py-0.5 border border-zinc-800 rounded font-orbitron">CHALLENGER</span>
                </div>
            </div>
            <div class="bg-zinc-950 border border-zinc-900 rounded-xl py-2 px-4 flex justify-between text-[10px] mt-4 font-orbitron">
                <span class="text-zinc-500 font-bold">CASH: $1,240</span>
                <span class="text-cyan-400 font-bold">W/L: 98-18</span>
            </div>
        </div>

        <!-- 1st Place Podium Card -->
        <div class="order-1 md:order-2 bg-[#120729]/90 border-2 border-purple-500/40 rounded-2xl p-6 text-center flex flex-col justify-between h-[320px] relative shadow-[0_10px_40px_rgba(168,85,247,0.2),0_0_15px_rgba(168,85,247,0.15)] neon-border-glow">
            <div class="absolute top-2 left-2 text-xs font-black font-orbitron text-purple-400">#1</div>
            <div class="space-y-4">
                <div class="relative w-20 h-20 rounded-full bg-gradient-to-br from-purple-500 via-fuchsia-500 to-yellow-500 p-[2px] mx-auto shadow-[0_0_20px_rgba(217,70,239,0.5)]">
                    <!-- Golden crown badge -->
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-yellow-500 text-black px-1.5 py-0.5 rounded text-[8px] font-black font-orbitron">CROWN</div>
                    <div class="w-full h-full bg-[#0a0718] rounded-full flex items-center justify-center text-purple-300 text-xl font-bold font-orbitron">SK</div>
                </div>
                <div>
                    <h4 class="text-base font-black font-orbitron text-white tracking-widest neon-pulse-purple">SALOONSKING</h4>
                    <span class="text-[9px] font-bold text-fuchsia-400 bg-fuchsia-950/60 px-3 py-1 border border-fuchsia-900/60 rounded-full font-orbitron">CHALLENGER T1</span>
                </div>
            </div>
            <div class="bg-zinc-950 border border-purple-500/10 rounded-xl py-2 px-4 flex justify-between text-[11px] mt-4 font-orbitron">
                <span class="text-emerald-400 font-bold">CASH: $1,820</span>
                <span class="text-fuchsia-400 font-bold">W/L: 142-28</span>
            </div>
        </div>

        <!-- 3rd Place Podium Card -->
        <div class="order-3 md:order-3 bg-[#0c081d]/80 border border-purple-500/15 rounded-2xl p-5 text-center flex flex-col justify-between h-[260px] relative">
            <div class="absolute top-2 left-2 text-xs font-black font-orbitron text-zinc-650">#3</div>
            <div class="space-y-4">
                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-amber-700 to-amber-900 p-[1.5px] mx-auto shadow-[0_0_15px_rgba(180,100,50,0.25)]">
                    <div class="w-full h-full bg-[#0a0718] rounded-full flex items-center justify-center text-amber-500 text-lg font-bold font-orbitron">SB</div>
                </div>
                <div>
                    <h4 class="text-sm font-black font-orbitron text-zinc-200 tracking-wider">SHADOWBLADE</h4>
                    <span class="text-[9px] font-bold text-zinc-400 bg-zinc-950 px-2 py-0.5 border border-zinc-800 rounded font-orbitron">CHALLENGER</span>
                </div>
            </div>
            <div class="bg-zinc-950 border border-zinc-900 rounded-xl py-2 px-4 flex justify-between text-[10px] mt-4 font-orbitron">
                <span class="text-zinc-500 font-bold">CASH: $1,190</span>
                <span class="text-cyan-400 font-bold">W/L: 110-26</span>
            </div>
        </div>

    </div>

    <!-- Table section -->
    <div class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 space-y-4">
        <div class="flex flex-col md:flex-row items-center justify-between border-b border-purple-500/10 pb-4 gap-4">
            <div class="flex items-center space-x-2">
                <i data-lucide="award" class="w-5 h-5 text-purple-400"></i>
                <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase font-bold">STANDINGS</h3>
            </div>
            
            <!-- Search inside leaderboard -->
            <div class="relative w-full md:w-64">
                <input type="text" x-model="searchFilter" placeholder="SEARCH PLAYER..." class="w-full bg-zinc-950 border border-purple-500/20 hover:border-purple-500/40 focus:border-purple-500 focus:outline-none rounded-xl py-1.5 px-3.5 text-[10px] font-bold font-orbitron uppercase tracking-widest text-purple-300">
            </div>
        </div>

        <!-- Leaderboard Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[11px] font-medium">
                <thead>
                    <tr class="border-b border-purple-500/10 text-zinc-500 font-bold font-orbitron uppercase tracking-widest">
                        <th class="py-3.5 px-2">RANK</th>
                        <th class="py-3.5 px-2">PLAYER</th>
                        <th class="py-3.5 px-2">TIER</th>
                        <th class="py-3.5 px-2 text-center">LEVEL</th>
                        <th class="py-3.5 px-2 text-center">WINS</th>
                        <th class="py-3.5 px-2 text-center">LOSSES</th>
                        <th class="py-3.5 px-2 text-right">TOTAL CASH</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="p in topPlayers" :key="p.rank">
                        <tr x-show="p.name.toLowerCase().includes(searchFilter.toLowerCase())"
                            class="border-b border-purple-500/5 hover:bg-purple-950/10 transition-colors">
                            <td class="py-3.5 px-2 font-black font-orbitron text-purple-400 text-xs" x-text="'#' + p.rank"></td>
                            <td class="py-3.5 px-2 font-bold font-orbitron text-white text-xs" x-text="p.name"></td>
                            <td class="py-3.5 px-2 font-bold font-orbitron text-fuchsia-400" x-text="p.tier"></td>
                            <td class="py-3.5 px-2 text-center font-mono text-zinc-300" x-text="p.level"></td>
                            <td class="py-3.5 px-2 text-center font-mono text-emerald-400 font-bold" x-text="p.wins"></td>
                            <td class="py-3.5 px-2 text-center font-mono text-red-400" x-text="p.losses"></td>
                            <td class="py-3.5 px-2 text-right font-black font-orbitron text-emerald-400" x-text="p.cash"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
