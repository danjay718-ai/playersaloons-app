{{--
  Partial: broadcast/_confirm-modal.blade.php
  Props: $confirmAction ('delete' | 'expire')
--}}
@if($showConfirmModal)
<div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
    <div class="bg-[#0f172a] border border-slate-700 rounded-2xl shadow-2xl w-full max-w-sm">

        <div class="px-6 py-5 text-center space-y-3">
            <div class="mx-auto w-12 h-12 rounded-full flex items-center justify-center
                {{ $confirmAction === 'delete' ? 'bg-red-500/10 border border-red-500/30 text-red-400' : 'bg-amber-500/10 border border-amber-500/30 text-amber-400' }}">
                <i data-lucide="{{ $confirmAction === 'delete' ? 'trash-2' : 'clock' }}" class="w-5 h-5"></i>
            </div>
            <h3 class="text-sm font-bold uppercase tracking-widest text-slate-200">
                {{ $confirmAction === 'delete' ? 'Delete Broadcast?' : 'Expire Broadcast?' }}
            </h3>
            <p class="text-xs text-slate-400 leading-relaxed">
                @if($confirmAction === 'delete')
                    This will permanently remove the broadcast. This action cannot be undone.
                @else
                    This will set the end time to now, immediately stopping the broadcast.
                @endif
            </p>
        </div>

        <div class="px-6 pb-5 flex gap-3">
            <button wire:click="cancelConfirm"
                    class="flex-1 py-2 text-xs font-bold uppercase tracking-wider border border-slate-700 text-slate-400 hover:text-white rounded-lg transition-colors">
                Cancel
            </button>
            <button wire:click="executeConfirm" wire:loading.attr="disabled"
                    class="flex-1 py-2 text-xs font-bold uppercase tracking-wider rounded-lg transition-colors disabled:opacity-50
                    {{ $confirmAction === 'delete'
                        ? 'bg-red-600 hover:bg-red-500 text-white'
                        : 'bg-amber-600 hover:bg-amber-500 text-white' }}">
                <span wire:loading.remove wire:target="executeConfirm">Confirm</span>
                <span wire:loading wire:target="executeConfirm">Working...</span>
            </button>
        </div>
    </div>
</div>
@endif
