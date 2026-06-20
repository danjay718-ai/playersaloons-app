<div class="space-y-8">
    <x-player.dashboard-tabs :items="$navItems" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <div class="space-y-6 lg:col-span-8">
            <section class="player-welcome-panel">
                <div>
                    <h2 class="font-orbitron text-2xl font-black text-white">WELCOME BACK, {{ $user->username }}!</h2>
                    <p class="mt-1 text-sm text-zinc-400">Ready for your next challenge?</p>
                </div>
                <div class="text-left sm:text-right">
                    <span class="text-xs font-bold uppercase text-zinc-500">Total Earnings</span>
                    <div class="font-orbitron text-2xl font-black text-emerald-400">${{ number_format($earnings, 2) }}</div>
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
