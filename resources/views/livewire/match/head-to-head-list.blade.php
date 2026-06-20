<div class="grid grid-cols-1 lg:grid-cols-3 gap-8" x-data="{
    searching: @entangle('isSearching'),
    selectedGame: @entangle('selectedGame'),
    stake: @entangle('stakeAmount'),
    step: 0,
    matchedUser: @entangle('matchedOpponent'),
    
    init() {
        this.$watch('searching', value => {
            if (value) {
                this.step = 1;
                setTimeout(() => {
                    if (this.searching) {
                        this.step = 2;
                        $wire.simulateMatchFound();
                    }
                }, 3000);
            } else {
                this.step = 0;
            }
        });
    }
}">
    
    <!-- Left Side: Initiate Duel & Matchmaker -->
    <div class="lg:col-span-1 bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 shadow-xl flex flex-col justify-between relative overflow-hidden">
        <div class="absolute -top-20 -right-20 w-40 h-40 bg-purple-500/5 rounded-full blur-2xl pointer-events-none"></div>

        <!-- Setup form -->
        <div class="space-y-6">
            <div class="flex items-center space-x-2 border-b border-purple-500/10 pb-3">
                <i data-lucide="zap" class="w-5 h-5 text-fuchsia-400"></i>
                <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase">INITIATE CHALLENGE</h3>
            </div>

            <div class="rounded-xl border border-amber-500/20 bg-amber-950/10 p-3 text-[10px] font-medium text-amber-300">
                Prototype only: challenges and match search are held in Livewire memory and are not persisted yet.
            </div>

            <!-- Select Game -->
            <div class="space-y-2">
                <label class="text-[9px] text-zinc-500 font-bold uppercase tracking-wider font-orbitron">Select Title</label>
                <select x-model="selectedGame" class="w-full bg-zinc-950 border border-purple-500/20 focus:border-purple-500 focus:outline-none rounded-xl py-3 px-4 text-xs font-bold font-orbitron tracking-wider text-purple-300">
                    <option value="Valorant">VALORANT</option>
                    <option value="CS2">COUNTER-STRIKE 2</option>
                    <option value="FIFA 24">FIFA 24</option>
                    <option value="Tekken 8">TEKKEN 8</option>
                    <option value="Dota 2">DOTA 2</option>
                </select>
            </div>

            <!-- Stake Amount -->
            <div class="space-y-3">
                <div class="flex justify-between">
                    <label class="text-[9px] text-zinc-500 font-bold uppercase tracking-wider font-orbitron">STAKE WEIGHT ($)</label>
                    <span class="text-xs font-black text-emerald-400 font-orbitron" x-text="'$' + stake"></span>
                </div>
                <input type="range" x-model="stake" min="5" max="100" step="5" class="w-full h-1 bg-zinc-900 rounded-lg appearance-none cursor-pointer accent-fuchsia-500 border border-purple-500/10">
                <div class="flex justify-between text-[8px] text-zinc-600 font-mono">
                    <span>$5 (MIN)</span>
                    <span>$50</span>
                    <span>$100 (MAX)</span>
                </div>
            </div>
        </div>

        <!-- Matchmaker Action Button -->
        <div class="pt-6">
            <button @click="$wire.findDuel()" 
                    class="w-full bg-gradient-to-r from-purple-600 via-fuchsia-600 to-cyan-500 hover:from-purple-500 hover:to-cyan-400 border border-fuchsia-400/20 text-xs font-black font-orbitron uppercase tracking-widest text-white py-3 rounded-xl transition-all duration-300 shadow-[0_0_15px_rgba(217,70,239,0.35)] hover:shadow-[0_0_25px_rgba(34,211,238,0.6)] cursor-pointer">
                ENTER QUICK DUEL QUEUE
            </button>
            
            <button @click="$wire.createChallenge()"
                    class="w-full mt-3 bg-zinc-950 hover:bg-zinc-900 border border-purple-500/20 text-[9px] font-black font-orbitron uppercase tracking-widest text-purple-300 hover:text-white py-2.5 rounded-xl transition-colors cursor-pointer">
                BROADCAST CUSTOM LOBBY CHALLENGE
            </button>
        </div>
    </div>

    <!-- Right Side: Matchmaking Display Terminal (RADAR / OPPONENT CARD) -->
    <div class="lg:col-span-2 bg-zinc-950 border border-purple-500/15 rounded-2xl shadow-2xl relative overflow-hidden flex flex-col items-center justify-center min-h-[350px]">
        
        <!-- Radar background visual grid -->
        <div class="absolute inset-0 cyber-grid opacity-[0.2] pointer-events-none"></div>

        <!-- State 0: Empty Idle State -->
        <div x-show="step === 0" class="text-center p-6 space-y-4 relative z-10">
            <div class="w-16 h-16 rounded-full bg-purple-950/40 border border-purple-500/35 flex items-center justify-center mx-auto shadow-[0_0_15px_rgba(168,85,247,0.1)]">
                <i data-lucide="shield-alert" class="w-8 h-8 text-purple-400 animate-pulse"></i>
            </div>
            <div>
                <h4 class="text-sm font-black font-orbitron tracking-widest text-zinc-300 uppercase">MATCHMAKER OFFLINE</h4>
                <p class="text-[10px] text-zinc-500 mt-1 max-w-xs mx-auto">Initiate a duel search from the control panel to seek active players in your region.</p>
            </div>
        </div>

        <!-- State 1: Matching Radar Animation -->
        <div x-show="step === 1" class="text-center p-6 space-y-6 relative z-10 flex flex-col items-center" x-cloak>
            
            <!-- Radar Circle -->
            <div class="relative w-36 h-36 border border-purple-500/30 rounded-full flex items-center justify-center shadow-[0_0_20px_rgba(168,85,247,0.1)]">
                <!-- Scanning Sweeper Line -->
                <div class="absolute inset-0 rounded-full border-t border-l border-fuchsia-500/50 animate-spin" style="animation-duration: 2s;"></div>
                <!-- Inner circles -->
                <div class="w-24 h-24 border border-purple-500/20 rounded-full flex items-center justify-center">
                    <div class="w-12 h-12 border border-purple-500/10 rounded-full bg-purple-500/5 flex items-center justify-center">
                        <span class="w-2.5 h-2.5 rounded-full bg-fuchsia-400 animate-ping"></span>
                    </div>
                </div>
            </div>

            <div class="space-y-1">
                <h4 class="text-sm font-black font-orbitron tracking-widest text-fuchsia-400 uppercase neon-pulse-purple">LOCATING OPPONENT</h4>
                <div class="text-[8px] font-mono text-zinc-500 uppercase tracking-widest space-y-0.5">
                    <div>PING: 14MS // SERVER: US-EAST</div>
                    <div class="animate-pulse">LOOKING FOR STAKE WEIGHT DEPOSIT...</div>
                </div>
            </div>

            <button @click="$wire.cancelSearch()" class="px-5 py-1.5 bg-red-950/60 border border-red-500/30 text-[8px] font-black font-orbitron uppercase tracking-widest text-red-400 hover:bg-red-900 rounded-lg transition-colors cursor-pointer">
                CANCEL MATCHMAKING
            </button>
        </div>

        <!-- State 2: Opponent Found Screen -->
        <div x-show="step === 2" class="w-full p-6 space-y-6 relative z-10" x-cloak>
            <template x-if="matchedUser">
                <div class="space-y-6 text-center">
                    
                    <!-- Banner Title -->
                    <div>
                        <span class="text-[9px] font-bold text-emerald-400 bg-emerald-950 border border-emerald-900 px-3 py-1 rounded-full font-orbitron uppercase tracking-widest">
                            MATCH DETECTED
                        </span>
                        <h4 class="text-lg font-black font-orbitron tracking-widest text-white uppercase mt-3 filter drop-shadow-[0_0_8px_rgba(168,85,247,0.3)]">READY FOR DUEL</h4>
                    </div>

                    <!-- Opponent Match VS Panel -->
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-6 sm:gap-12 py-4">
                        <!-- Self -->
                        <div class="text-center">
                            <div class="w-16 h-16 rounded-full bg-gradient-to-br from-purple-500 to-fuchsia-500 p-[1.5px] mx-auto shadow-[0_0_15px_rgba(168,85,247,0.3)]">
                                <div class="w-full h-full bg-[#0a0718] rounded-full flex items-center justify-center text-purple-400 text-base font-bold font-orbitron">
                                    {{ strtoupper(substr(auth()->user()->username, 0, 2)) }}
                                </div>
                            </div>
                            <span class="block text-xs font-bold font-orbitron text-zinc-300 mt-2 uppercase tracking-wide">{{ auth()->user()->username }}</span>
                            <span class="block text-[8px] font-mono text-zinc-500">{{ strtoupper(auth()->user()->username) }} // YOU</span>
                        </div>

                        <!-- VS Shield -->
                        <div class="relative bg-zinc-950 border-2 border-purple-500/30 w-12 h-12 rounded-xl flex items-center justify-center shadow-[0_0_15px_rgba(168,85,247,0.2)]">
                            <span class="text-xs font-black font-orbitron text-fuchsia-400 animate-pulse">VS</span>
                        </div>

                        <!-- Opponent -->
                        <div class="text-center">
                            <div class="w-16 h-16 rounded-full bg-gradient-to-br from-cyan-500 to-indigo-600 p-[1.5px] mx-auto shadow-[0_0_15px_rgba(34,211,238,0.3)]">
                                <div class="w-full h-full bg-[#0a0718] rounded-full flex items-center justify-center text-cyan-400 text-base font-bold font-orbitron" x-text="matchedUser.avatar">
                                </div>
                            </div>
                            <span class="block text-xs font-bold font-orbitron text-cyan-400 mt-2 uppercase tracking-wide" x-text="matchedUser.username"></span>
                            <span class="block text-[8px] font-mono text-zinc-500" x-text="'LEVEL ' + matchedUser.level + ' // WINRATE ' + matchedUser.winrate"></span>
                        </div>
                    </div>

                    <!-- Info Stake Board -->
                    <div class="max-w-xs mx-auto bg-[#0c081d] border border-purple-500/15 rounded-xl p-3.5 flex justify-between items-center text-[10px]">
                        <div>
                            <span class="text-zinc-500 font-bold uppercase tracking-wider block">CHALLENGE GAME</span>
                            <span class="font-bold text-white font-orbitron tracking-wider block mt-0.5" x-text="matchedUser.game"></span>
                        </div>
                        <div class="text-right">
                            <span class="text-zinc-500 font-bold uppercase tracking-wider block">ESCROW STAKE</span>
                            <span class="font-black text-emerald-400 font-orbitron tracking-widest block mt-0.5" x-text="'$' + (stake * 2) + '.00 ($' + stake + '/PLAYER)'"></span>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-center justify-center space-x-3 pt-2">
                        <button @click="$wire.cancelSearch()" class="px-5 py-2.5 bg-zinc-900 border border-zinc-800 text-[10px] font-black font-orbitron uppercase tracking-widest text-zinc-400 hover:text-white rounded-xl transition-all cursor-pointer">
                            DECLINE BATTLE
                        </button>
                        <button @click="alert('Connecting to Lobby Server... Please prepare your client!')" class="px-6 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-500 border border-emerald-400/20 text-[10px] font-black font-orbitron uppercase tracking-widest text-white rounded-xl shadow-[0_0_15px_rgba(16,185,129,0.4)] transition-all cursor-pointer">
                            COMMENCE BATTLE
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Bottom: Public Open Lobby Board -->
    <div class="lg:col-span-3 bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-purple-500/10 pb-3">
            <div class="flex items-center space-x-2">
                <i data-lucide="globe" class="w-4.5 h-4.5 text-purple-400"></i>
                <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase">BROADCASTED STAGED DUELS</h3>
            </div>
            <span class="text-[9px] bg-purple-950 text-purple-400 px-2 py-0.5 rounded font-bold font-mono">3 DUELS OPEN</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <template x-for="ch in $wire.challenges" :key="ch.id">
                <div class="bg-zinc-950/60 border border-purple-500/10 hover:border-purple-500/20 rounded-xl p-4 flex items-center justify-between gap-4 transition-all duration-200">
                    <div>
                        <div class="flex items-center space-x-2">
                            <div class="w-6 h-6 rounded-full bg-purple-900/30 border border-purple-500/20 flex items-center justify-center text-[9px] font-bold font-orbitron text-purple-400" x-text="ch.avatar"></div>
                            <span class="text-xs font-bold text-zinc-300 font-orbitron" x-text="ch.username"></span>
                        </div>
                        <div class="mt-2.5 flex items-center space-x-2">
                            <span class="text-[8px] font-bold text-fuchsia-400 bg-fuchsia-950/40 border border-fuchsia-900/40 px-2 py-0.5 rounded font-orbitron" x-text="ch.game"></span>
                            <span class="text-xs font-black text-emerald-400 font-mono" x-text="'$' + ch.stake + '.00'"></span>
                        </div>
                    </div>
                    <button @click="alert('Challenge Accepted! Processing Stake Escrow...')" class="bg-purple-950 border border-purple-500/30 hover:bg-purple-900 text-[8px] font-black font-orbitron uppercase tracking-widest text-purple-300 hover:text-white py-1.5 px-3 rounded-lg transition-colors cursor-pointer">
                        ACCEPT CHALLENGE
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>
