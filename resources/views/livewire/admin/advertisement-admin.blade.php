<div class="grid gap-6 xl:grid-cols-[420px_1fr]">
    <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-5">
        @if(session('success'))<div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-200">{{ session('success') }}</div>@endif
        <h2 class="text-lg font-bold text-white">{{ $editingId ? 'Edit promotion' : 'New promotion' }}</h2>
        <p class="mt-1 text-xs leading-5 text-slate-500">Create a scheduled banner shown across authenticated player pages. External image and destination URLs are optional.</p>
        <form wire:submit="save" class="mt-5 space-y-3">
            <input wire:model="title" placeholder="Promotion title" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-white">@error('title')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
            <textarea wire:model="description" rows="3" placeholder="Short description" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-white"></textarea>
            <input wire:model="imageUrl" type="url" placeholder="Image URL (optional)" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-white">@error('imageUrl')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
            <input wire:model="targetUrl" type="url" placeholder="Destination URL (optional)" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-white">@error('targetUrl')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
            <input wire:model="ctaLabel" placeholder="Button label" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-white">
            <div class="grid grid-cols-2 gap-3"><div><label class="text-xs text-slate-500">Starts</label><input wire:model="startsAt" type="datetime-local" class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-xs text-white"></div><div><label class="text-xs text-slate-500">Ends</label><input wire:model="endsAt" type="datetime-local" class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-xs text-white"></div></div>
            <label class="flex items-center justify-between rounded-lg border border-slate-800 p-3 text-sm text-slate-300">Active and player-visible<input wire:model="isActive" type="checkbox"></label>
            <button class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-xs font-bold uppercase tracking-wider text-white">Save promotion</button>
        </form>
    </section>
    <section class="space-y-3">
        @forelse($advertisements as $ad)
            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4"><div class="flex justify-between gap-4"><div><div class="flex items-center gap-2"><h3 class="font-bold text-white">{{ $ad->title }}</h3><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $ad->is_active ? 'bg-emerald-500/10 text-emerald-300' : 'bg-slate-800 text-slate-500' }}">{{ $ad->is_active ? 'ACTIVE' : 'INACTIVE' }}</span></div><p class="mt-2 text-xs text-slate-500">{{ $ad->description }}</p><p class="mt-2 text-[11px] text-slate-600">{{ $ad->starts_at?->format('M d, Y H:i') ?? 'Immediately' }} → {{ $ad->ends_at?->format('M d, Y H:i') ?? 'No end date' }} · {{ $ad->clicks }} clicks</p></div><div class="flex gap-2"><button wire:click="edit({{ $ad->id }})" class="text-xs text-indigo-300">Edit</button><button wire:click="delete({{ $ad->id }})" wire:confirm="Delete this promotion?" class="text-xs text-red-300">Delete</button></div></div></div>
        @empty<p class="rounded-xl border border-slate-800 p-8 text-center text-sm text-slate-500">No advertisements or promotions yet.</p>@endforelse
    </section>
</div>
