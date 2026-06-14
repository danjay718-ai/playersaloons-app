<div class="space-y-10" x-data="{ activeTab: @entangle('activeTab') }">
    <!-- Tournament Tabs -->
    <div class="flex gap-2 border-b border-zinc-800 pb-2">
        <button @click="activeTab = 'overview'" :class="activeTab === 'overview' ? 'text-white border-b-2 border-indigo-500' : 'text-zinc-500 hover:text-white'" class="px-4 py-2 text-xs font-bold uppercase tracking-widest transition-colors">Overview</button>
        <button @click="activeTab = 'players'" :class="activeTab === 'players' ? 'text-white border-b-2 border-indigo-500' : 'text-zinc-500 hover:text-white'" class="px-4 py-2 text-xs font-bold uppercase tracking-widest transition-colors">Players</button>
        <button @click="activeTab = 'bracket'" :class="activeTab === 'bracket' ? 'text-white border-b-2 border-indigo-500' : 'text-zinc-500 hover:text-white'" class="px-4 py-2 text-xs font-bold uppercase tracking-widest transition-colors">Bracket</button>
        <button @click="activeTab = 'logs'" :class="activeTab === 'logs' ? 'text-white border-b-2 border-indigo-500' : 'text-zinc-500 hover:text-white'" class="px-4 py-2 text-xs font-bold uppercase tracking-widest transition-colors">Activity Logs</button>
    </div>

    @if(Auth::check() && Auth::user()->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER']))
        @include('livewire.tournament.partials.admin-content')
    @else
        @include('livewire.tournament.partials.player-content')
    @endif
    
    <!-- Activity Logs Tab -->
    <div x-show="activeTab === 'logs'" class="bg-zinc-900 border border-zinc-800 rounded-2xl p-6">
        <h3 class="text-sm font-black text-white font-orbitron uppercase tracking-widest mb-4">Activity Logs</h3>
        <div class="space-y-4">
            @forelse($activityLogs as $log)
                <div class="flex items-center text-xs text-zinc-400 gap-4">
                    <span class="font-mono text-zinc-600">{{ $log->created_at->format('M d, H:i') }}</span>
                    <span>{{ $log->description }}</span>
                </div>
            @empty
                <p class="text-zinc-600 text-xs italic">No activity logs found for this tournament.</p>
            @endforelse
        </div>
    </div>
</div>
