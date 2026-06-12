<div>
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <!-- Search and Filters -->
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <input type="text" wire:model.live="search" placeholder="Search matches or players..." 
                   class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 w-full sm:w-64">
            
            <select wire:model.live="statusFilter" 
                    class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="">All Statuses</option>
                @foreach(\App\Shared\Enums\MatchStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ strtoupper($status->name) }}</option>
                @endforeach
            </select>

            <label class="inline-flex items-center cursor-pointer bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-300">
                <input type="checkbox" wire:model.live="disputeFilter" class="sr-only peer">
                <div class="relative w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-slate-400 after:border-slate-350 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-red-600 peer-checked:after:bg-white"></div>
                <span class="ms-3 text-xs font-semibold text-slate-300">Disputes Only</span>
            </label>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if(session()->has('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <i data-lucide="alert-circle" class="w-4 h-4 mr-2"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Matches Table -->
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                        <th class="p-4">Tournament / Round</th>
                        <th class="p-4">Competitor A</th>
                        <th class="p-4">Competitor B</th>
                        <th class="p-4">Winner</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Last Updated</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @forelse($matches as $match)
                        <tr class="hover:bg-slate-900/40">
                            <td class="p-4 font-semibold text-slate-200">
                                <span class="block text-slate-200 hover:text-indigo-400 cursor-pointer" wire:click="selectMatch({{ $match->id }})">
                                    {{ $match->tournament->name }}
                                </span>
                                <span class="block text-[10px] text-slate-500 font-normal mt-0.5">Round {{ $match->round->round_number ?? 'N/A' }}</span>
                            </td>
                            <td class="p-4 text-slate-300">
                                @if($match->playerARegistration?->user)
                                    <span class="font-semibold">{{ $match->playerARegistration->user->username }}</span>
                                @else
                                    <span class="text-slate-550 italic">TBD (Bye/Pending)</span>
                                @endif
                            </td>
                            <td class="p-4 text-slate-300">
                                @if($match->playerBRegistration?->user)
                                    <span class="font-semibold">{{ $match->playerBRegistration->user->username }}</span>
                                @else
                                    <span class="text-slate-550 italic">TBD (Bye/Pending)</span>
                                @endif
                            </td>
                            <td class="p-4 text-slate-300 font-bold">
                                @if($match->winnerRegistration?->user)
                                    <span class="text-emerald-450">{{ $match->winnerRegistration->user->username }}</span>
                                @else
                                    <span class="text-slate-500 font-normal italic">—</span>
                                @endif
                            </td>
                            <td class="p-4">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-slate-800 text-slate-400 border-slate-700',
                                        'ready' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                        'in_progress' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                        'result_submitted' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                                        'completed' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                                        'disputed' => 'bg-red-500/10 text-red-450 border-red-500/20 animate-pulse',
                                        'forfeited' => 'bg-slate-700 text-slate-400 border-slate-600',
                                    ];
                                    $col = $statusColors[$match->status->value] ?? 'bg-slate-800 text-slate-400 border-slate-750';
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold uppercase {{ $col }}">
                                    {{ str_replace('_', ' ', $match->status->value) }}
                                </span>
                            </td>
                            <td class="p-4 text-slate-400">
                                {{ $match->updated_at->diffForHumans() }}
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <button wire:click="selectMatch({{ $match->id }})" class="p-1.5 text-slate-400 hover:text-white bg-slate-800 rounded-lg" title="Details">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                                
                                @if($match->status->value === 'disputed' && $match->disputes->where('status', \App\Shared\Enums\DisputeStatus::OPEN)->first())
                                    <button wire:click="openDisputeModal({{ $match->disputes->where('status', \App\Shared\Enums\DisputeStatus::OPEN)->first()->id }})" 
                                            class="p-1.5 text-red-400 hover:text-white bg-red-950/40 border border-red-900/50 rounded-lg" title="Resolve Dispute">
                                        <i data-lucide="shield-alert" class="w-4 h-4"></i>
                                    </button>
                                @endif

                                @if($match->status->value !== 'completed' && $match->status->value !== 'forfeited' && $match->player_a_registration_id && $match->player_b_registration_id)
                                    <button wire:click="openOverrideModal({{ $match->id }})" 
                                            class="p-1.5 text-indigo-450 hover:text-white bg-indigo-950/40 border border-indigo-900/50 rounded-lg" title="Override Result">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500 italic">No matches found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div>
        {{ $matches->links() }}
    </div>

    <!-- Detail Modal -->
    @if($showDetailModal && $selectedMatch)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showDetailModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-2xl w-full overflow-hidden shadow-2xl relative z-10 max-h-[85vh] flex flex-col">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Match Details</h3>
                        <p class="text-[9px] text-slate-500 font-mono mt-0.5">{{ $selectedMatch->uuid }}</p>
                    </div>
                    <button wire:click="$set('showDetailModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-6 flex-grow text-xs">
                    <!-- Players comparison card -->
                    <div class="grid grid-cols-3 items-center bg-[#0b0f19] border border-slate-800 rounded-xl p-4 text-center">
                        <div>
                            <span class="text-[9px] text-slate-500 font-bold block uppercase tracking-wider">PLAYER A</span>
                            <p class="text-sm font-bold text-slate-200 mt-1">
                                {{ $selectedMatch->playerARegistration?->user->username ?? 'TBD' }}
                            </p>
                            @if($selectedMatch->winner_registration_id === $selectedMatch->player_a_registration_id && $selectedMatch->winner_registration_id !== null)
                                <span class="inline-block mt-2 px-2 py-0.5 bg-emerald-500/10 text-emerald-450 border border-emerald-500/20 rounded font-bold uppercase text-[9px]">WINNER</span>
                            @endif
                        </div>
                        <div class="flex flex-col items-center">
                            <span class="text-xs font-black text-slate-650 font-mono">VS</span>
                            <span class="inline-block mt-2 px-2 py-0.5 rounded border text-[9px] font-bold uppercase 
                                  {{ $selectedMatch->status->value === 'disputed' ? 'bg-red-500/10 text-red-450 border-red-500/20' : 'bg-slate-800 text-slate-400 border-slate-700' }}">
                                {{ $selectedMatch->status->value }}
                            </span>
                        </div>
                        <div>
                            <span class="text-[9px] text-slate-500 font-bold block uppercase tracking-wider">PLAYER B</span>
                            <p class="text-sm font-bold text-slate-200 mt-1">
                                {{ $selectedMatch->playerBRegistration?->user->username ?? 'TBD' }}
                            </p>
                            @if($selectedMatch->winner_registration_id === $selectedMatch->player_b_registration_id && $selectedMatch->winner_registration_id !== null)
                                <span class="inline-block mt-2 px-2 py-0.5 bg-emerald-500/10 text-emerald-450 border border-emerald-500/20 rounded font-bold uppercase text-[9px]">WINNER</span>
                            @endif
                        </div>
                    </div>

                    <!-- Tournament & Round details -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-900/40 border border-slate-850 p-3 rounded-lg">
                            <span class="text-slate-500 font-medium block">Tournament</span>
                            <span class="text-slate-200 font-semibold mt-0.5 block">{{ $selectedMatch->tournament->name }}</span>
                        </div>
                        <div class="bg-slate-900/40 border border-slate-850 p-3 rounded-lg">
                            <span class="text-slate-500 font-medium block">Round & Schedule</span>
                            <span class="text-slate-200 font-semibold mt-0.5 block">Round {{ $selectedMatch->round->round_number ?? 'N/A' }}</span>
                        </div>
                    </div>

                    <!-- Disputes Section -->
                    @if($selectedMatch->disputes->count() > 0)
                        <div>
                            <span class="text-[10px] text-red-400 font-bold uppercase tracking-wider block mb-2">Disputes logged ({{ $selectedMatch->disputes->count() }})</span>
                            <div class="space-y-3">
                                @foreach($selectedMatch->disputes as $disp)
                                    <div class="bg-red-500/5 border border-red-500/15 rounded-lg p-4 space-y-3">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <span class="text-slate-550 block">Opened By</span>
                                                <span class="text-slate-300 font-semibold">{{ $disp->openedBy->username }}</span>
                                            </div>
                                            <div>
                                                <span class="text-slate-550 block">Dispute status</span>
                                                <span class="inline-block px-2 py-0.5 rounded border font-bold uppercase text-[9px]
                                                      {{ $disp->status->value === 'resolved' ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20' }}">
                                                    {{ $disp->status->value }}
                                                </span>
                                            </div>
                                        </div>

                                        @if($disp->status->value === 'resolved')
                                            <div class="grid grid-cols-2 gap-2 border-t border-slate-800/60 pt-2 text-xs">
                                                <div>
                                                    <span class="text-slate-550">Resolution</span>
                                                    <span class="text-slate-300 font-bold block uppercase mt-0.5">{{ str_replace('_', ' ', $disp->resolution->value) }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-550">Resolved By Admin ID</span>
                                                    <span class="text-slate-300 block mt-0.5">{{ $disp->resolved_by }} (on {{ $disp->resolved_at?->format('M d, H:i') }})</span>
                                                </div>
                                            </div>
                                        @endif

                                        <!-- Evidence uploads -->
                                        @if($disp->evidence->count() > 0)
                                            <div class="border-t border-slate-800/60 pt-2">
                                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-2">Evidence Files</span>
                                                <div class="grid grid-cols-2 gap-3">
                                                    @foreach($disp->evidence as $ev)
                                                        <div class="bg-slate-900 border border-slate-850 p-2.5 rounded-lg flex items-center justify-between">
                                                            <div class="truncate mr-2">
                                                                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">UPLOADED BY</span>
                                                                <span class="text-slate-300 truncate block">{{ $ev->uploadedBy->username }}</span>
                                                            </div>
                                                            <a href="/storage/{{ $ev->file_path }}" target="_blank" class="p-1.5 bg-slate-800 hover:bg-slate-750 text-indigo-400 rounded-lg" title="Open File">
                                                                <i data-lucide="external-link" class="w-4 h-4"></i>
                                                            </a>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <!-- Resolve CTA inside details -->
                                        @if($disp->status->value === 'open')
                                            <div class="pt-2 text-right">
                                                <button wire:click="openDisputeModal({{ $disp->id }})" class="bg-red-650 hover:bg-red-600 text-white font-bold text-[10px] uppercase tracking-wider px-3 py-1.5 rounded-lg">
                                                    Resolve Dispute
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="px-6 py-4 border-t border-slate-800 bg-[#0b0f19] flex justify-end space-x-3">
                    @if($selectedMatch->status->value !== 'completed' && $selectedMatch->status->value !== 'forfeited' && $selectedMatch->player_a_registration_id && $selectedMatch->player_b_registration_id)
                        <button wire:click="openOverrideModal({{ $selectedMatch->id }})" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Override Result
                        </button>
                    @endif
                    <button wire:click="$set('showDetailModal', false)" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                        Close Details
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Override Result Modal -->
    @if($showOverrideModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" wire:click="$set('showOverrideModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Override Match Winner</h3>
                    <button wire:click="$set('showOverrideModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                @php
                    $overrideMatch = \App\Modules\Match\Models\GameMatch::with(['playerARegistration.user', 'playerBRegistration.user'])->find($selectedMatchId);
                @endphp

                @if($overrideMatch)
                    <form wire:submit.prevent="overrideResult" class="p-6 space-y-4">
                        <div class="bg-indigo-500/5 border border-indigo-500/10 text-indigo-350 p-3 rounded-lg text-xs leading-relaxed">
                            <i data-lucide="info" class="w-4 h-4 inline mr-1 text-indigo-400 align-text-bottom"></i>
                            <span>You are overriding the match result. This will force-advance the selected winner in the tournament bracket.</span>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Select the winner</label>
                            <div class="space-y-2">
                                <label class="flex items-center bg-slate-900 border border-slate-800 rounded-lg p-3 cursor-pointer hover:border-slate-700">
                                    <input type="radio" wire:model="winnerRegistrationId" value="{{ $overrideMatch->player_a_registration_id }}" class="text-indigo-600 focus:ring-indigo-500 mr-3">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-200 block">Player A</span>
                                        <span class="text-slate-450">{{ $overrideMatch->playerARegistration?->user->username }}</span>
                                    </div>
                                </label>
                                <label class="flex items-center bg-slate-900 border border-slate-800 rounded-lg p-3 cursor-pointer hover:border-slate-700">
                                    <input type="radio" wire:model="winnerRegistrationId" value="{{ $overrideMatch->player_b_registration_id }}" class="text-indigo-600 focus:ring-indigo-500 mr-3">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-200 block">Player B</span>
                                        <span class="text-slate-450">{{ $overrideMatch->playerBRegistration?->user->username }}</span>
                                    </div>
                                </label>
                            </div>
                            @error('winnerRegistrationId') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                            <button type="button" wire:click="$set('showOverrideModal', false)" 
                                    class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                                Save Winner
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <!-- Dispute Resolution Modal -->
    @if($showDisputeModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" wire:click="$set('showDisputeModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-red-400 uppercase tracking-wider">Resolve Match Dispute</h3>
                    <button wire:click="$set('showDisputeModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                @php
                    $resolveDispute = \App\Modules\Match\Models\MatchDispute::with('match.playerARegistration.user', 'match.playerBRegistration.user')->find($selectedDisputeId);
                @endphp

                @if($resolveDispute)
                    <form wire:submit.prevent="resolveDispute" class="p-6 space-y-4">
                        <div class="bg-red-500/5 border border-red-500/10 text-red-400 p-3 rounded-lg text-xs leading-relaxed">
                            <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-1 text-red-400 align-text-bottom"></i>
                            <span>You are resolving this dispute as an administrator. Select the correct resolution outcome below.</span>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Resolution Action</label>
                            <div class="space-y-2">
                                <label class="flex items-center bg-slate-900 border border-slate-800 rounded-lg p-3 cursor-pointer hover:border-slate-700">
                                    <input type="radio" wire:model="resolution" value="player_a" class="text-indigo-600 focus:ring-indigo-500 mr-3">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-200 block">Award Win to A</span>
                                        <span class="text-slate-450">{{ $resolveDispute->match->playerARegistration?->user->username }}</span>
                                    </div>
                                </label>
                                <label class="flex items-center bg-slate-900 border border-slate-800 rounded-lg p-3 cursor-pointer hover:border-slate-700">
                                    <input type="radio" wire:model="resolution" value="player_b" class="text-indigo-600 focus:ring-indigo-500 mr-3">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-200 block">Award Win to B</span>
                                        <span class="text-slate-450">{{ $resolveDispute->match->playerBRegistration?->user->username }}</span>
                                    </div>
                                </label>
                                <label class="flex items-center bg-slate-900 border border-slate-800 rounded-lg p-3 cursor-pointer hover:border-slate-700">
                                    <input type="radio" wire:model="resolution" value="rematch" class="text-indigo-600 focus:ring-indigo-500 mr-3">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-200 block">Schedule Rematch</span>
                                        <span class="text-slate-450">Creates a new fresh match slot for these players</span>
                                    </div>
                                </label>
                            </div>
                            @error('resolution') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                            <button type="button" wire:click="$set('showDisputeModal', false)" 
                                    class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="bg-red-600 hover:bg-red-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                                Resolve Dispute
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
