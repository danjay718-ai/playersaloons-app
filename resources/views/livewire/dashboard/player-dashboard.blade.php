<div class="space-y-8" x-data="{ activeTab: @entangle('tab') }">
    <!-- Dashboard Tabs -->
    <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
        <a href="/dashboard" wire:navigate class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ request()->is('dashboard') ? 'bg-indigo-600 text-white' : 'bg-zinc-900 text-zinc-400 hover:bg-zinc-800' }}">Overview</a>
        <a href="/my-tournaments" wire:navigate class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ request()->is('my-tournaments') ? 'bg-indigo-600 text-white' : 'bg-zinc-900 text-zinc-400 hover:bg-zinc-800' }}">My Tournaments</a>
        <a href="/tournaments/browse" wire:navigate class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ request()->is('tournaments/browse*') ? 'bg-indigo-600 text-white' : 'bg-zinc-900 text-zinc-400 hover:bg-zinc-800' }}">Browse</a>
        <a href="/head-to-head" wire:navigate class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ request()->is('head-to-head') ? 'bg-indigo-600 text-white' : 'bg-zinc-900 text-zinc-400 hover:bg-zinc-800' }}">H2H Duels</a>
        <a href="/leaderboards" wire:navigate class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ request()->is('leaderboards') ? 'bg-indigo-600 text-white' : 'bg-zinc-900 text-zinc-400 hover:bg-zinc-800' }}">Leaderboard</a>
        <a href="/streams" wire:navigate class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ request()->is('streams') ? 'bg-indigo-600 text-white' : 'bg-zinc-900 text-zinc-400 hover:bg-zinc-800' }}">Streams</a>
        <a href="/chat" wire:navigate class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ request()->is('chat') ? 'bg-indigo-600 text-white' : 'bg-zinc-900 text-zinc-400 hover:bg-zinc-800' }}">Chat</a>
    </div>

    <!-- Overview Tab -->
    @if(request()->is('dashboard'))
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Main Col (8 columns) -->
            <div class="lg:col-span-8 space-y-6">
                <!-- Welcome Header -->
                <div class="bg-gradient-to-r from-zinc-900 to-zinc-950 border border-zinc-800 rounded-2xl p-8 flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-black text-white font-orbitron">Welcome back, {{ auth()->user()->username }}!</h2>
                        <p class="text-zinc-400 text-sm mt-1">Ready for your next challenge?</p>
                    </div>
                    <div class="text-right">
                        <span class="text-zinc-500 text-xs uppercase font-bold">Total Earnings</span>
                        <div class="text-2xl font-black text-emerald-400 font-orbitron">${{ number_format($earnings, 2) }}</div>
                    </div>
                </div>

                <!-- Recent Matches -->
                <div class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6">
                    <h3 class="text-sm font-black text-zinc-300 font-orbitron uppercase mb-4">Recent Matches</h3>
                    <div class="space-y-3">
                        @forelse($recentMatches as $match)
                            <div class="flex items-center justify-between bg-zinc-950 p-4 rounded-xl border border-zinc-800 text-sm">
                                <span class="font-bold text-white">{{ $match->tournament->name }}</span>
                                <span class="text-xs text-zinc-500">{{ $match->updated_at->format('M d') }}</span>
                            </div>
                        @empty
                            <p class="text-zinc-600 text-xs">No recent matches found.</p>
                        @endforelse
                    </div>
                </div>

                <!-- Recent Tournaments -->
                <div class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6">
                    <h3 class="text-sm font-black text-zinc-300 font-orbitron uppercase mb-4">Upcoming Tournaments</h3>
                    <div class="space-y-3">
                        @forelse($activeTournaments as $tournament)
                            <div class="flex items-center justify-between bg-zinc-950 p-4 rounded-xl border border-zinc-800 text-sm">
                                <span class="font-bold text-white">{{ $tournament->name }}</span>
                                <a href="/tournaments/{{ $tournament->uuid }}/view" class="text-xs text-indigo-400 hover:text-indigo-300">View</a>
                            </div>
                        @empty
                            <p class="text-zinc-600 text-xs">No upcoming tournaments.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Sidebar Col (4 columns) -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Stats / XP Panel -->
                <div class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6">
                    <h3 class="text-sm font-black text-zinc-300 font-orbitron uppercase mb-4">Progression</h3>
                    <div class="space-y-4">
                        <div class="h-24 bg-zinc-950 rounded-xl flex items-center justify-center border border-zinc-800">
                            <span class="text-zinc-700 text-xs font-bold font-orbitron">LEVEL 42 (MOCK)</span>
                        </div>
                        <div class="text-xs text-zinc-500">XP: 4500 / 5000</div>
                    </div>
                </div>

                <!-- Announcements -->
                <div class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6">
                    <h3 class="text-sm font-black text-zinc-300 font-orbitron uppercase mb-4">Announcements</h3>
                    <div class="space-y-4 text-xs text-zinc-400">
                        <p>• New Valorant tournament starts this Friday!</p>
                        <p>• Prize pool updated for the weekly cup.</p>
                        <p>• Maintenance scheduled for 2:00 AM UTC.</p>
                    </div>
                </div>
                
                <!-- Placeholder for Video -->
                <div class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-2 h-48 flex items-center justify-center text-zinc-600">
                    [VIDEO DISPLAY]
                </div>
            </div>
        </div>
    @endif
</div>
