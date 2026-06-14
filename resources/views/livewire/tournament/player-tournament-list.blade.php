<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($tournaments as $tournament)
            <div class="bg-zinc-900/40 border border-zinc-800 rounded-2xl p-6 hover:border-violet-500/50 transition-all">
                <h3 class="text-lg font-bold text-white">{{ $tournament->name }}</h3>
                <p class="text-sm text-zinc-500 mb-4">{{ $tournament->game->translations->first()?->name }}</p>
                <a href="/tournaments/{{ $tournament->uuid }}/view" wire:navigate class="text-indigo-400 text-sm font-bold">View Details</a>
            </div>
        @empty
            <div class="col-span-full p-10 text-center text-zinc-600">No active tournaments found.</div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $tournaments->links() }}
    </div>
</div>
