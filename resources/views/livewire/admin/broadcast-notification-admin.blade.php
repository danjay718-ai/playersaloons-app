<div
    x-data="{
        formOpen: false,
        editingId: null,
        displayType: 'feed',
        targetSurface: 'both',
        mediaType: 'none',
        isVideoOnly: false,
        openNewModal() {
            this.editingId = null;
            this.displayType = 'feed';
            this.targetSurface = 'both';
            this.mediaType = 'none';
            this.isVideoOnly = false;
            this.$wire.$set('editingId', null, false);
            this.$wire.$set('title', '', false);
            this.$wire.$set('message', '', false);
            this.$wire.$set('displayType', 'feed', false);
            this.$wire.$set('targetSurface', 'both', false);
            this.$wire.$set('badgeLabel', '', false);
            this.$wire.$set('mediaType', 'none', false);
            this.$wire.$set('mediaUrl', '', false);
            this.$wire.$set('mediaFile', null, false);
            this.$wire.$set('isVideoOnly', false, false);
            this.$wire.$set('ctaLabel', '', false);
            this.$wire.$set('ctaUrl', '', false);
            this.$wire.$set('startsAt', '', false);
            this.$wire.$set('endsAt', '', false);
            this.formOpen = true;
        },
        openEditModal(row) {
            this.editingId = row.id;
            this.displayType = row.display_type || 'feed';
            this.targetSurface = row.target_surface || 'both';
            this.mediaType = row.media_type || 'none';
            this.isVideoOnly = !!row.is_video_only;
            this.$wire.$set('editingId', row.id, false);
            this.$wire.$set('title', row.title || '', false);
            this.$wire.$set('message', row.message || '', false);
            this.$wire.$set('displayType', this.displayType, false);
            this.$wire.$set('targetSurface', this.targetSurface, false);
            this.$wire.$set('badgeLabel', row.badge_label || '', false);
            this.$wire.$set('mediaType', this.mediaType, false);
            this.$wire.$set('mediaUrl', row.media_url || '', false);
            this.$wire.$set('mediaFile', null, false);
            this.$wire.$set('isVideoOnly', this.isVideoOnly, false);
            this.$wire.$set('ctaLabel', row.cta_label || '', false);
            this.$wire.$set('ctaUrl', row.cta_url || '', false);
            this.$wire.$set('startsAt', row.starts_at || '', false);
            this.$wire.$set('endsAt', row.ends_at || '', false);
            this.formOpen = true;
        },
        closeFormModal() {
            this.formOpen = false;
        }
    }"
    @broadcast-saved.window="formOpen = false"
    @keydown.escape.window="if (formOpen) formOpen = false"
>

    {{-- Top Bar --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
        <input type="text" wire:model.live.debounce.300ms="search"
               placeholder="Search title or message..."
               class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 w-full sm:w-72">

        <button type="button" @click="openNewModal()"
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
    <x-admin.deletion-actions resource="broadcasts" />
</div>
