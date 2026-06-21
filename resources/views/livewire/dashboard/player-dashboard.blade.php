<div class="space-y-8">
    <x-player.dashboard-tabs :items="$navItems" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <div class="space-y-6 lg:col-span-8">
            <section class="bg-gradient-to-r from-[#170e30] via-[#0e0a24] to-transparent border border-purple-500/20 rounded-2xl p-6 md:p-8 shadow-[0_10px_30px_rgba(0,0,0,0.5),inset_0_0_20px_rgba(168,85,247,0.05)] relative overflow-hidden">
                <!-- Glowing sci-fi elements -->
                <div class="absolute -top-20 -right-20 w-80 h-80 bg-emerald-500/5 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute top-0 right-0 w-24 h-24 border-t-2 border-r-2 border-purple-500/20 rounded-tr-2xl pointer-events-none"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 border-b-2 border-l-2 border-purple-500/20 rounded-bl-2xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                    <div>
                        <h2 class="font-orbitron text-2xl md:text-3xl font-black text-white filter drop-shadow-[0_0_8px_rgba(255,255,255,0.2)]">WELCOME BACK, {{ strtoupper($user->username) }}!</h2>
                        <div class="flex items-center gap-4 mt-4">
                            <p class="text-xs md:text-sm text-zinc-400 font-bold uppercase tracking-widest">Ready for your next challenge?</p>
                            <button class="pwa-install-btn hidden items-center space-x-2 bg-fuchsia-600/20 hover:bg-fuchsia-600/40 border border-fuchsia-500/50 text-fuchsia-300 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all disabled:cursor-not-allowed disabled:opacity-50" data-pwa-install-dashboard type="button" disabled>
                                <i data-lucide="download" class="w-3 h-3"></i>
                                <span>Install App</span>
                            </button>
                        </div>
                    </div>
                    <div class="text-left sm:text-right">
                        <span class="block text-[9px] font-bold text-zinc-500 uppercase tracking-widest font-orbitron mb-1">AVAILABLE BALANCE</span>
                        <div class="text-4xl md:text-5xl font-black font-orbitron tracking-wider text-emerald-450 filter drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]">
                            ${{ number_format((float)($user->wallet?->cached_balance ?? 0.00), 2) }}
                        </div>
                    </div>
                </div>
            </section>

            <x-player.panel title="Recent Matches">
                <div class="space-y-3">
                    @forelse($recentMatches as $match)
                        <x-player.list-row
                            :title="$match->tournament->name"
                            :meta="$match->updated_at->format('M d')" />
                    @empty
                        <p class="text-xs text-zinc-600">No recent matches found.</p>
                    @endforelse
                </div>
            </x-player.panel>

            <x-player.panel title="Upcoming Tournaments">
                <div class="space-y-3">
                    @forelse($activeTournaments as $tournament)
                        <x-player.list-row
                            :title="$tournament->name"
                            :href="'/tournaments/'.$tournament->uuid.'/view'" />
                    @empty
                        <p class="text-xs text-zinc-600">No upcoming tournaments.</p>
                    @endforelse
                </div>
            </x-player.panel>
        </div>

        <div class="space-y-6 lg:col-span-4">
            <x-player.panel title="Progression">
                <div class="rounded-xl border border-dashed border-zinc-800 bg-zinc-950/60 p-5 text-sm text-zinc-500">
                    Player XP and levels are not connected to a backend module yet.
                </div>
            </x-player.panel>

            <x-player.panel title="Announcements">
                <div class="space-y-4 text-xs text-zinc-400">
                    @forelse($announcements as $announcement)
                        <article class="space-y-1">
                            <h4 class="font-bold text-zinc-200">{{ $announcement->title }}</h4>
                            <p>{{ $announcement->message }}</p>
                        </article>
                    @empty
                        <p>No platform announcements are active.</p>
                    @endforelse
                </div>
            </x-player.panel>

            <x-player.panel padding="p-6" class="flex min-h-48 flex-col items-center justify-center text-center text-zinc-500">
                <i data-lucide="tv" class="mb-4 h-10 w-10 text-zinc-700"></i>
                <p class="text-xs">Live broadcast embeds are not implemented yet.</p>
            </x-player.panel>
        </div>
    </div>
</div>
