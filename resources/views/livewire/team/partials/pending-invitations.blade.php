<!-- Inbound Pending Invitations -->
<div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 shadow-lg shadow-black/20 space-y-6">
    <div class="border-b border-zinc-850 pb-3 flex items-center space-x-2">
        <i data-lucide="mail-open" class="w-5 h-5 text-indigo-400"></i>
        <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase">
            PENDING INVITATIONS
        </h2>
    </div>

    @if($myPendingInvites->count() > 0)
        <div class="space-y-3">
            @foreach($myPendingInvites as $invite)
                <div wire:key="invite-{{ $invite->uuid }}" class="bg-zinc-950 border border-zinc-850 rounded-xl p-4 space-y-3">
                    <div>
                        <span class="text-[10px] text-zinc-500 font-semibold block">INVITATION TO JOIN</span>
                        <h3 class="text-sm font-bold text-zinc-200 mt-0.5">{{ $invite->team->name }}</h3>
                        <span class="text-[10px] text-zinc-400 mt-1 block">{{ __('Sent by: :value1', ['value1' => ($invite->inviter?->username ?? __('Squad Leader'))]) }}</span>
                    </div>
                    <div class="flex items-center space-x-2 pt-1">
                        <button 
                            wire:click="acceptInvitation('{{ $invite->uuid }}')"
                            class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs px-3 py-1.5 rounded-lg transition-colors cursor-pointer"
                        >
                            Accept
                        </button>
                        <button 
                            wire:click="declineInvitation('{{ $invite->uuid }}')"
                            class="bg-zinc-800 hover:bg-zinc-700 text-zinc-400 font-bold text-xs px-3 py-1.5 rounded-lg transition-colors cursor-pointer"
                        >
                            Decline
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-12 text-zinc-500">
            <i data-lucide="mail" class="w-8 h-8 mx-auto text-zinc-650 mb-3"></i>
            <p class="text-xs font-semibold">No pending squad invitations.</p>
        </div>
    @endif
</div>
