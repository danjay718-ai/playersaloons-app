{{--
  Partial: broadcast/_form-modal.blade.php
  Props: $editingId (null = create mode)
--}}
@if($showFormModal)
<div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
    <div class="bg-[#0f172a] border border-slate-700 rounded-2xl shadow-2xl w-full max-w-lg"
         x-data x-trap.noscroll="true">

        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800">
            <h3 class="text-sm font-bold uppercase tracking-widest text-slate-200">
                {{ $editingId ? 'Edit Broadcast' : 'New Broadcast' }}
            </h3>
            <button wire:click="$set('showFormModal', false)"
                    class="p-1.5 text-slate-500 hover:text-white transition-colors rounded-lg hover:bg-slate-800">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">
            {{-- Title --}}
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">Title</label>
                <input wire:model="title" type="text" maxlength="255"
                       class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors"
                       placeholder="e.g. Platform Maintenance Tonight">
                @error('title') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            {{-- Message --}}
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">Message</label>
                <textarea wire:model="message" rows="4" maxlength="2000"
                          class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors resize-none"
                          placeholder="Broadcast message content..."></textarea>
                @error('message') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            {{-- Dates --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">Starts At <span class="text-slate-600 normal-case">(optional)</span></label>
                    <input wire:model="startsAt" type="datetime-local"
                           class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 transition-colors">
                    @error('startsAt') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">Ends At <span class="text-slate-600 normal-case">(optional)</span></label>
                    <input wire:model="endsAt" type="datetime-local"
                           class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 transition-colors">
                    @error('endsAt') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-slate-800 flex justify-end gap-3">
            <button wire:click="$set('showFormModal', false)"
                    class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-400 hover:text-white border border-slate-700 hover:border-slate-600 rounded-lg transition-colors">
                Cancel
            </button>
            <button wire:click="save" wire:loading.attr="disabled"
                    class="px-4 py-2 text-xs font-bold uppercase tracking-wider bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors disabled:opacity-50">
                <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Create' }}</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </div>
</div>
@endif
