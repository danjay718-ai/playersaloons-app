<div>
    {{-- ─── Top Filter Bar ───────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
        <div class="flex flex-wrap items-center gap-2 w-full">
            {{-- Search (debounced 400ms so it doesn't fire on every keystroke) --}}
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500 pointer-events-none"></i>
                <input type="text"
                       wire:model.live.debounce.400ms="search"
                       placeholder="Search tournament or player..."
                       class="bg-slate-900 border border-slate-800 rounded-lg pl-8 pr-4 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 w-56">
            </div>

            {{-- Status filter --}}
            <select wire:model.live="statusFilter"
                    class="bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="">All Statuses</option>
                @foreach(\App\Shared\Enums\MatchStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ str_replace('_', ' ', strtoupper($status->name)) }}</option>
                @endforeach
            </select>

            {{-- Game filter --}}
            <select wire:model.live="gameFilter"
                    class="bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="">All Games</option>
                @foreach($games as $game)
                    <option value="{{ $game->id }}">{{ $game->translations->first()?->name ?? $game->slug }}</option>
                @endforeach
            </select>

            {{-- Disputes only toggle --}}
            <label class="inline-flex items-center cursor-pointer bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-300 gap-2">
                <input type="checkbox" wire:model.live="disputeFilter" class="sr-only peer">
                <div class="relative w-8 h-4 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-slate-400 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-red-600 peer-checked:after:bg-white"></div>
                <span class="font-semibold text-slate-300">Disputes Only</span>
            </label>

            {{-- Per-page --}}
            <select wire:model.live="perPage"
                    class="bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="15">15 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </select>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session()->has('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center gap-2">
            <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- ─── Matches Table ────────────────────────────────────────────────────── --}}
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6 relative">
        {{-- Table loading overlay --}}
        <div wire:loading.flex wire:target="search,statusFilter,gameFilter,disputeFilter,perPage,gotoPage,previousPage,nextPage"
             class="absolute inset-0 bg-slate-950/60 backdrop-blur-[1px] z-10 items-center justify-center rounded-xl">
            <div class="flex items-center gap-2 text-slate-400 text-xs">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                Loading…
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                        <th class="p-4">Tournament / Game / Round</th>
                        <th class="p-4">Player A</th>
                        <th class="p-4">Player B</th>
                        <th class="p-4">Winner</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Updated</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @forelse($matches as $match)
                        <tr class="hover:bg-slate-900/40 transition-colors" wire:key="match-{{ $match->id }}">
                            <td class="p-4">
                                <span class="block font-semibold text-slate-200 hover:text-indigo-400 cursor-pointer transition-colors"
                                      wire:click="selectMatch({{ $match->id }})">
                                    {{ $match->tournament->name }}
                                </span>
                                <span class="block text-[10px] text-indigo-400/70 mt-0.5">
                                    {{ $match->tournament->game->translations->first()?->name ?? $match->tournament->game->slug ?? '—' }}
                                </span>
                                <span class="block text-[10px] text-slate-500 font-normal">
                                    Round {{ $match->round->round_number ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="p-4 text-slate-300">
                                @if($match->playerARegistration?->user)
                                    <span class="font-semibold">{{ $match->playerARegistration->user->username }}</span>
                                @else
                                    <span class="text-slate-600 italic">TBD</span>
                                @endif
                            </td>
                            <td class="p-4 text-slate-300">
                                @if($match->playerBRegistration?->user)
                                    <span class="font-semibold">{{ $match->playerBRegistration->user->username }}</span>
                                @else
                                    <span class="text-slate-600 italic">TBD</span>
                                @endif
                            </td>
                            <td class="p-4 font-bold">
                                @if($match->winnerRegistration?->user)
                                    <span class="text-emerald-400">{{ $match->winnerRegistration->user->username }}</span>
                                @else
                                    <span class="text-slate-600 font-normal">—</span>
                                @endif
                            </td>
                            <td class="p-4">
                                @php
                                    $statusColors = [
                                        'pending'          => 'bg-slate-800 text-slate-400 border-slate-700',
                                        'ready'            => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                        'in_progress'      => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                        'waiting_for_confirmation' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                                        'result_submitted' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                                        'completed'        => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                                        'disputed'         => 'bg-red-500/10 text-red-400 border-red-500/20 animate-pulse',
                                        'forfeited'        => 'bg-slate-700 text-slate-400 border-slate-600',
                                    ];
                                    $col = $statusColors[$match->status->value] ?? 'bg-slate-800 text-slate-400 border-slate-700';
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold uppercase {{ $col }}">
                                    {{ str_replace('_', ' ', $match->status->value) }}
                                </span>
                            </td>
                            <td class="p-4 text-slate-500 text-[10px]">
                                {{ $match->updated_at->diffForHumans() }}
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    {{-- Details --}}
                                    <button wire:click="selectMatch({{ $match->id }})"
                                            wire:loading.attr="disabled" wire:target="selectMatch({{ $match->id }})"
                                            class="p-1.5 text-slate-400 hover:text-white bg-slate-800 hover:bg-slate-700 rounded-lg transition-colors"
                                            title="View Details">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>

                                    {{-- Quick dispute badge (opens detail modal) --}}
                                    @if(isset($match->disputes) && $match->disputes->where('status', \App\Shared\Enums\DisputeStatus::OPEN)->first())
                                        <button wire:click="selectMatch({{ $match->id }})"
                                                class="p-1.5 text-red-400 hover:text-white bg-red-950/40 hover:bg-red-900/60 border border-red-900/50 rounded-lg transition-colors"
                                                title="View Dispute">
                                            <i data-lucide="shield-alert" class="w-4 h-4"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-10 text-center text-slate-600 italic">
                                No matches found for these filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    <div>{{ $matches->links() }}</div>

    {{-- ─── Detail Modal ─────────────────────────────────────────────────────── --}}
    @if($showDetailModal && $this->selectedMatch)
        @php $selectedMatch = $this->selectedMatch; @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="closeDetailModal"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-2xl w-full overflow-hidden shadow-2xl relative z-10 max-h-[85vh] flex flex-col"
                 wire:key="detail-modal-{{ $selectedMatch->id }}">

                {{-- Header --}}
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-start shrink-0">
                    <div>
                        <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Match Details</h3>
                        <p class="text-[9px] text-slate-500 font-mono mt-0.5">{{ $selectedMatch->uuid }}</p>
                    </div>
                    <button wire:click="closeDetailModal" class="text-slate-400 hover:text-white transition-colors mt-0.5">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                {{-- Scrollable body --}}
                <div class="p-6 overflow-y-auto space-y-5 flex-grow text-xs">

                    {{-- VS card --}}
                    <div class="grid grid-cols-3 items-center bg-[#0b0f19] border border-slate-800 rounded-xl p-4 text-center">
                        <div>
                            <span class="text-[9px] text-slate-500 font-bold block uppercase tracking-wider">PLAYER A</span>
                            <p class="text-sm font-bold text-slate-200 mt-1">
                                {{ $selectedMatch->playerARegistration?->user->username ?? 'TBD' }}
                            </p>
                            @if($selectedMatch->winner_registration_id === $selectedMatch->player_a_registration_id && $selectedMatch->winner_registration_id !== null)
                                <span class="inline-block mt-2 px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded font-bold uppercase text-[9px]">WINNER</span>
                            @endif
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <span class="text-xs font-black text-slate-700 font-mono">VS</span>
                            <span class="inline-block px-2 py-0.5 rounded border text-[9px] font-bold uppercase
                                  {{ $selectedMatch->status->value === 'disputed' ? 'bg-red-500/10 text-red-400 border-red-500/20 animate-pulse' : 'bg-slate-800 text-slate-400 border-slate-700' }}">
                                {{ str_replace('_', ' ', $selectedMatch->status->value) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-[9px] text-slate-500 font-bold block uppercase tracking-wider">PLAYER B</span>
                            <p class="text-sm font-bold text-slate-200 mt-1">
                                {{ $selectedMatch->playerBRegistration?->user->username ?? 'TBD' }}
                            </p>
                            @if($selectedMatch->winner_registration_id === $selectedMatch->player_b_registration_id && $selectedMatch->winner_registration_id !== null)
                                <span class="inline-block mt-2 px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded font-bold uppercase text-[9px]">WINNER</span>
                            @endif
                        </div>
                    </div>

                    {{-- Tournament & Round info --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-slate-900/40 border border-slate-800 p-3 rounded-lg">
                            <span class="text-slate-500 font-medium block">Tournament</span>
                            <span class="text-slate-200 font-semibold mt-0.5 block">{{ $selectedMatch->tournament->name }}</span>
                            <span class="text-indigo-400/70 text-[10px] block mt-0.5">
                                {{ $selectedMatch->tournament->game->translations->first()?->name ?? $selectedMatch->tournament->game->slug }}
                            </span>
                        </div>
                        <div class="bg-slate-900/40 border border-slate-800 p-3 rounded-lg">
                            <span class="text-slate-500 font-medium block">Round</span>
                            <span class="text-slate-200 font-semibold mt-0.5 block">Round {{ $selectedMatch->round->round_number ?? 'N/A' }}</span>
                        </div>
                    </div>

                    {{-- Disputes section --}}
                    @if($selectedMatch->disputes->count() > 0)
                        <div>
                            <span class="text-[10px] text-red-400 font-bold uppercase tracking-wider block mb-2">
                                Disputes Logged ({{ $selectedMatch->disputes->count() }})
                            </span>
                            <div class="space-y-4">
                                @foreach($selectedMatch->disputes as $disp)
                                    <div class="bg-red-500/5 border border-red-500/15 rounded-xl p-4 space-y-3"
                                         wire:key="disp-{{ $disp->id }}">

                                        {{-- Header: filed by + status badge --}}
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">Filed By</span>
                                                <span class="text-slate-200 font-semibold text-sm">{{ $disp->openedBy->username }}</span>
                                                @if($disp->created_at)
                                                    <span class="text-slate-600 text-[10px] block">{{ $disp->created_at->format('M d, Y · H:i') }}</span>
                                                @endif
                                            </div>
                                            <span class="inline-block shrink-0 px-2.5 py-1 rounded-lg border font-bold uppercase text-[9px] tracking-wider
                                                  {{ match($disp->status->value) {
                                                      'resolved'     => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                                                      'under_review' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                                      default        => 'bg-red-500/10 text-red-400 border-red-500/20',
                                                  } }}">
                                                {{ str_replace('_', ' ', $disp->status->value) }}
                                            </span>
                                        </div>

                                        {{-- Player's note --}}
                                        @if($disp->reason)
                                            <div class="bg-slate-900/70 border border-slate-800 rounded-lg px-3 py-2.5">
                                                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block mb-1">Player's Note</span>
                                                <p class="text-slate-300 leading-relaxed">{{ $disp->reason }}</p>
                                            </div>
                                        @endif

                                        {{-- Evidence thumbnails --}}
                                        @if($disp->evidence->count() > 0)
                                            <div class="border-t border-slate-800/60 pt-3">
                                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-2">
                                                    Evidence Screenshots ({{ $disp->evidence->count() }})
                                                </span>
                                                <div class="grid grid-cols-2 gap-2">
                                                    @foreach($disp->evidence as $ev)
                                                        <a href="/storage/{{ $ev->file_path }}" target="_blank"
                                                           class="group relative block rounded-lg overflow-hidden border border-slate-800 bg-slate-950 hover:border-indigo-500/50 transition-colors">
                                                            <img src="/storage/{{ $ev->file_path }}"
                                                                 alt="Evidence"
                                                                 class="w-full h-28 object-cover opacity-80 group-hover:opacity-100 transition-opacity"
                                                                 onerror="this.closest('a').innerHTML='<div class=\'flex items-center justify-center h-28 text-slate-600\'><svg xmlns=\'http://www.w3.org/2000/svg\' class=\'w-6 h-6\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\'/></svg></div>'">
                                                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-2">
                                                                <span class="text-[10px] text-white font-semibold truncate">{{ $ev->uploadedBy->username }}</span>
                                                                <i data-lucide="external-link" class="w-3 h-3 text-white ml-auto shrink-0"></i>
                                                            </div>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @else
                                            <p class="text-[10px] text-slate-600 italic border-t border-slate-800/60 pt-2">No evidence screenshots uploaded yet.</p>
                                        @endif

                                        {{-- Resolved info --}}
                                        @if($disp->status->value === 'resolved')
                                            <div class="grid grid-cols-2 gap-2 border-t border-slate-800/60 pt-3 text-xs">
                                                <div>
                                                    <span class="text-slate-500 block">Resolution</span>
                                                    <span class="text-slate-300 font-bold block uppercase mt-0.5">{{ str_replace('_', ' ', $disp->resolution->value) }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500 block">Resolved By (Admin ID)</span>
                                                    <span class="text-slate-300 block mt-0.5">{{ $disp->resolved_by }} · {{ $disp->resolved_at?->format('M d, H:i') }}</span>
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Resolve CTA --}}
                                        @if(in_array($disp->status->value, ['open', 'under_review']))
                                            <div class="pt-2 flex justify-end">
                                                <button wire:click="openDisputeModal({{ $disp->id }})"
                                                        class="inline-flex items-center gap-1.5 bg-red-600/90 hover:bg-red-500 text-white font-bold text-[10px] uppercase tracking-wider px-3 py-1.5 rounded-lg transition-colors">
                                                    <i data-lucide="gavel" class="w-3.5 h-3.5"></i>
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

                {{-- Footer --}}
                <div class="px-6 py-4 border-t border-slate-800 bg-[#0b0f19] flex justify-end gap-3 shrink-0">
                    @if(!in_array($selectedMatch->status->value, ['completed','forfeited']) && $selectedMatch->player_a_registration_id && $selectedMatch->player_b_registration_id)
                        <button wire:click="openOverrideModal({{ $selectedMatch->id }})"
                                class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                            Override Result
                        </button>
                    @endif
                    <button wire:click="closeDetailModal"
                            class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ─── Override Result Modal ────────────────────────────────────────────── --}}
    @if($showOverrideModal && $this->selectedMatch)
        @php $selectedMatch = $this->selectedMatch; @endphp
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" wire:click="$set('showOverrideModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10"
                 wire:key="override-modal-{{ $selectedMatch->id }}">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Override Match Winner</h3>
                    <button wire:click="$set('showOverrideModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form wire:submit.prevent="overrideResult" class="p-6 space-y-4">
                    <div class="bg-indigo-500/5 border border-indigo-500/10 text-indigo-300 p-3 rounded-lg text-xs leading-relaxed">
                        <i data-lucide="info" class="w-4 h-4 inline mr-1 text-indigo-400 align-text-bottom"></i>
                        This will force-advance the selected winner in the tournament bracket.
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Select the winner</label>
                        <div class="space-y-2">
                            <label class="flex items-center bg-slate-900 border border-slate-800 rounded-xl p-3.5 cursor-pointer hover:border-emerald-600/50 hover:bg-emerald-900/10 transition-all has-[:checked]:border-emerald-500/50 has-[:checked]:bg-emerald-900/10">
                                <input type="radio" wire:model="winnerRegistrationId" value="{{ $selectedMatch->player_a_registration_id }}" class="text-emerald-600 focus:ring-emerald-500 mr-3">
                                <div class="text-xs">
                                    <span class="font-bold text-slate-100 block">Player A</span>
                                    <span class="text-slate-400">{{ $selectedMatch->playerARegistration?->user->username }}</span>
                                </div>
                            </label>
                            <label class="flex items-center bg-slate-900 border border-slate-800 rounded-xl p-3.5 cursor-pointer hover:border-emerald-600/50 hover:bg-emerald-900/10 transition-all has-[:checked]:border-emerald-500/50 has-[:checked]:bg-emerald-900/10">
                                <input type="radio" wire:model="winnerRegistrationId" value="{{ $selectedMatch->player_b_registration_id }}" class="text-emerald-600 focus:ring-emerald-500 mr-3">
                                <div class="text-xs">
                                    <span class="font-bold text-slate-100 block">Player B</span>
                                    <span class="text-slate-400">{{ $selectedMatch->playerBRegistration?->user->username }}</span>
                                </div>
                            </label>
                        </div>
                        @error('winnerRegistrationId') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end gap-3">
                        <button type="button" wire:click="$set('showOverrideModal', false)"
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled" wire:target="overrideResult"
                                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                            <span wire:loading.remove wire:target="overrideResult">Save Winner</span>
                            <span wire:loading wire:target="overrideResult">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ─── Dispute Resolution Modal ─────────────────────────────────────────── --}}
    @if($showDisputeModal && $this->selectedDispute)
        @php $resolveDispute = $this->selectedDispute; @endphp
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" wire:click="closeDisputeModal"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-2xl w-full overflow-hidden shadow-2xl relative z-10 max-h-[90vh] flex flex-col"
                 wire:key="dispute-modal-{{ $resolveDispute->id }}">

                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center shrink-0">
                    <div>
                        <h3 class="text-sm font-bold text-red-400 uppercase tracking-wider">Resolve Match Dispute</h3>
                        <p class="text-[10px] text-slate-500 mt-0.5">Review the player's note and evidence before ruling.</p>
                    </div>
                    <button wire:click="closeDisputeModal" class="text-slate-400 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="overflow-y-auto flex-grow">
                    <form wire:submit.prevent="resolveDispute" class="p-6 space-y-5">

                        {{-- Filed by + status --}}
                        <div class="flex items-center justify-between text-xs">
                            <div>
                                <span class="text-slate-500 block">Dispute filed by</span>
                                <span class="text-slate-200 font-bold text-sm">{{ $resolveDispute->openedBy->username }}</span>
                            </div>
                            <span class="inline-block px-2.5 py-1 rounded-lg border font-bold uppercase text-[9px] tracking-wider
                                  {{ match($resolveDispute->status->value) {
                                      'under_review' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                      default        => 'bg-red-500/10 text-red-400 border-red-500/20',
                                  } }}">
                                {{ str_replace('_', ' ', $resolveDispute->status->value) }}
                            </span>
                        </div>

                        {{-- Player's note --}}
                        @if($resolveDispute->reason)
                            <div class="bg-slate-900/70 border border-slate-800 rounded-lg px-4 py-3">
                                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block mb-1.5">
                                    <i data-lucide="message-square" class="w-3 h-3 inline mr-1 align-text-bottom"></i>
                                    Player's Note
                                </span>
                                <p class="text-slate-300 text-xs leading-relaxed">{{ $resolveDispute->reason }}</p>
                            </div>
                        @endif

                        {{-- Evidence --}}
                        @if($resolveDispute->evidence->count() > 0)
                            <div>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-2">
                                    <i data-lucide="image" class="w-3 h-3 inline mr-1 align-text-bottom"></i>
                                    Evidence Screenshots ({{ $resolveDispute->evidence->count() }})
                                </span>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach($resolveDispute->evidence as $ev)
                                        <a href="/storage/{{ $ev->file_path }}" target="_blank"
                                           class="group relative block rounded-xl overflow-hidden border border-slate-800 bg-slate-950 hover:border-indigo-500/60 transition-all">
                                            <img src="/storage/{{ $ev->file_path }}"
                                                 alt="Evidence screenshot"
                                                 class="w-full h-36 object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition-all duration-300"
                                                 onerror="this.closest('a').innerHTML='<div class=\'flex flex-col items-center justify-center h-36 text-slate-600 gap-2\'><svg xmlns=\'http://www.w3.org/2000/svg\' class=\'w-8 h-8\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\'/></svg><span class=\'text-[10px]\'>No preview</span></div>'">
                                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end justify-between p-2.5">
                                                <span class="text-[10px] text-white font-semibold truncate">by {{ $ev->uploadedBy->username }}</span>
                                                <i data-lucide="zoom-in" class="w-4 h-4 text-white shrink-0"></i>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="bg-slate-900/40 border border-slate-800/60 rounded-lg p-3 text-center">
                                <i data-lucide="image-off" class="w-5 h-5 mx-auto text-slate-600 mb-1"></i>
                                <p class="text-[10px] text-slate-600">No evidence screenshots uploaded yet.</p>
                            </div>
                        @endif

                        <div class="border-t border-slate-800"></div>

                        {{-- Admin ruling --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-3">
                                <i data-lucide="gavel" class="w-3.5 h-3.5 inline mr-1 align-text-bottom text-red-400"></i>
                                Admin Ruling
                            </label>
                            <div class="space-y-2">
                                <label class="flex items-center bg-slate-900 border border-slate-800 rounded-xl p-3.5 cursor-pointer hover:border-emerald-600/50 hover:bg-emerald-900/10 transition-all has-[:checked]:border-emerald-500/50 has-[:checked]:bg-emerald-900/10">
                                    <input type="radio" wire:model="resolution" value="player_a" class="text-emerald-600 focus:ring-emerald-500 mr-3 shrink-0">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-100 block">Award Win to Player A</span>
                                        <span class="text-slate-400">{{ $resolveDispute->match->playerARegistration?->user->username }}</span>
                                    </div>
                                </label>
                                <label class="flex items-center bg-slate-900 border border-slate-800 rounded-xl p-3.5 cursor-pointer hover:border-emerald-600/50 hover:bg-emerald-900/10 transition-all has-[:checked]:border-emerald-500/50 has-[:checked]:bg-emerald-900/10">
                                    <input type="radio" wire:model="resolution" value="player_b" class="text-emerald-600 focus:ring-emerald-500 mr-3 shrink-0">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-100 block">Award Win to Player B</span>
                                        <span class="text-slate-400">{{ $resolveDispute->match->playerBRegistration?->user->username }}</span>
                                    </div>
                                </label>
                                <label class="flex items-center bg-slate-900 border border-slate-800 rounded-xl p-3.5 cursor-pointer hover:border-amber-600/50 hover:bg-amber-900/10 transition-all has-[:checked]:border-amber-500/50 has-[:checked]:bg-amber-900/10">
                                    <input type="radio" wire:model="resolution" value="rematch" class="text-amber-600 focus:ring-amber-500 mr-3 shrink-0">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-100 block">Schedule Rematch</span>
                                        <span class="text-slate-400">Creates a fresh match slot between these two players</span>
                                    </div>
                                </label>
                            </div>
                            @error('resolution') <span class="text-red-400 text-xs mt-2 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="pt-2 border-t border-slate-800 flex justify-end gap-3">
                            <button type="button" wire:click="closeDisputeModal"
                                    class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                    wire:loading.attr="disabled" wire:target="resolveDispute"
                                    class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white font-bold text-xs uppercase px-5 py-2.5 rounded-lg transition-colors">
                                <i data-lucide="gavel" class="w-3.5 h-3.5"></i>
                                <span wire:loading.remove wire:target="resolveDispute">Submit Ruling</span>
                                <span wire:loading wire:target="resolveDispute">Submitting…</span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
