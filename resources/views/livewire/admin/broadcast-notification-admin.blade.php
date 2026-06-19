<div>
    {{-- Top Bar --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
        <input type="text" wire:model.live="search"
               placeholder="Search title or message..."
               class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 w-full sm:w-72">

        <button wire:click="openCreate"
                class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors whitespace-nowrap">
            <i data-lucide="plus" class="w-4 h-4"></i>
            New Broadcast
        </button>
    </div>

    {{-- Flash --}}
    @if(session()->has('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            {{ session('success') }}
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center gap-2">
            <i data-lucide="alert-circle" class="w-4 h-4"></i>
            {{ session('error') }}
        </div>
    @endif

    {{-- Table --}}
    @include('livewire.admin.partials.broadcast._table', [
        'broadcasts'   => $broadcasts,
        'isSuperAdmin' => $this->isSuperAdmin(),
    ])

    {{-- Modals --}}
    @include('livewire.admin.partials.broadcast._form-modal')
    @include('livewire.admin.partials.broadcast._confirm-modal')
</div>
