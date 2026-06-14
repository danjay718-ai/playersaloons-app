<div class="space-y-6">
    <!-- Tabs -->
    <div class="flex gap-2">
        <button wire:click="$set('tSubTab', 'active')" class="px-4 py-2 rounded-lg text-sm font-bold uppercase {{ $tSubTab === 'active' ? 'bg-indigo-600 text-white' : 'bg-zinc-800 text-zinc-400 hover:text-white' }}">Active</button>
        <button wire:click="$set('tSubTab', 'history')" class="px-4 py-2 rounded-lg text-sm font-bold uppercase {{ $tSubTab === 'history' ? 'bg-indigo-600 text-white' : 'bg-zinc-800 text-zinc-400 hover:text-white' }}">History</button>
    </div>

    <!-- Tournaments Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($tournaments as $tournament)
            <div class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6 hover:border-violet-500/50 transition-all">
                <h3 class="text-lg font-bold text-white">{{ $tournament->name }}</h3>
                <p class="text-sm text-zinc-500 mb-4">{{ $tournament->game->translations->first()?->name }}</p>
                <a href="/tournaments/{{ $tournament->uuid }}" wire:navigate class="text-indigo-400 text-sm font-bold">View Details</a>
            </div>
        @empty
            <div class="col-span-full p-10 text-center text-zinc-600">No tournaments found.</div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $tournaments->links() }}
    </div>
</div>
