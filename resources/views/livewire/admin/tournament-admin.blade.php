<div>
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <!-- Search and Filters -->
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <input type="text" wire:model.live="search" placeholder="Search tournaments..." 
                   class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 w-full sm:w-64">
            
            <select wire:model.live="statusFilter" 
                    class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="">All Statuses</option>
                @foreach(\App\Shared\Enums\TournamentStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ strtoupper($status->name) }}</option>
                @endforeach
            </select>

            <select wire:model.live="gameFilter" 
                    class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="">All Games</option>
                @foreach($games as $game)
                    <option value="{{ $game->id }}">{{ $game->translations->first()?->name ?? $game->slug }}</option>
                @endforeach
            </select>
        </div>

        <button wire:click="openCreateModal" 
                class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm px-4 py-2.5 rounded-lg flex items-center shadow-[0_4px_12px_rgba(79,70,229,0.2)] transition-colors w-full sm:w-auto justify-center">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
            <span>Create Tournament</span>
        </button>
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

    <!-- Tournaments Table -->
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                        <th class="p-4">Tournament</th>
                        <th class="p-4">Game</th>
                        <th class="p-4">Entry Fee</th>
                        <th class="p-4">Prize Pool</th>
                        <th class="p-4">Players</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Start At</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @forelse($tournaments as $tournament)
                        <tr class="hover:bg-slate-900/40" wire:key="tournament-{{ $tournament->id }}">
                            <td class="p-4 font-semibold text-slate-200">
                                <span class="block text-slate-200 hover:text-indigo-400 cursor-pointer" wire:click="selectTournament({{ $tournament->id }})">
                                    {{ $tournament->name }}
                                </span>
                                <span class="block text-[10px] text-slate-500 font-normal mt-0.5">{{ $tournament->uuid }}</span>
                            </td>
                            <td class="p-4 text-slate-300">
                                {{ $tournament->game->translations->first()?->name ?? $tournament->game->slug }}
                            </td>
                            <td class="p-4 text-slate-300">
                                ${{ number_format((float)$tournament->entry_fee, 2) }}
                            </td>
                            <td class="p-4 text-slate-300">
                                ${{ number_format((float)$tournament->prize_pool, 2) }}
                            </td>
                            <td class="p-4 text-slate-300">
                                {{ $tournament->registrations->count() }} / {{ $tournament->max_participants }}
                            </td>
                            <td class="p-4">
                                @php
                                    $statusColors = [
                                        'draft' => 'bg-slate-850 text-slate-400 border-slate-800',
                                        'published' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                        'registration_open' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                        'registration_closed' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
                                        'checkin_open' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                        'checkin_closed' => 'bg-orange-500/10 text-orange-400 border-orange-500/20',
                                        'bracket_generated' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                                        'ongoing' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                        'completed' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                                        'cancelled' => 'bg-slate-800 text-slate-500 border-slate-700',
                                        'refunded' => 'bg-slate-700 text-slate-400 border-slate-600',
                                    ];
                                    $col = $statusColors[$tournament->status->value] ?? 'bg-slate-800 text-slate-400 border-slate-750';
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold uppercase {{ $col }}">
                                    {{ str_replace('_', ' ', $tournament->status->value) }}
                                </span>
                            </td>
                            <td class="p-4 text-slate-400">
                                {{ $tournament->start_at ? $tournament->start_at->format('M d, H:i') : 'N/A' }}
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <button wire:click="selectTournament({{ $tournament->id }})" class="p-1.5 text-slate-400 hover:text-white bg-slate-800 rounded-lg" title="Details">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                                @if($tournament->status == \App\Shared\Enums\TournamentStatus::DRAFT)
                                    <button wire:click="openEditModal({{ $tournament->id }})" class="p-1.5 text-indigo-400 hover:text-white bg-indigo-950/40 border border-indigo-900/50 rounded-lg" title="Edit">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </button>
                                @endif
                                @if($tournament->status != \App\Shared\Enums\TournamentStatus::CANCELLED && $tournament->status != \App\Shared\Enums\TournamentStatus::REFUNDED && $tournament->status != \App\Shared\Enums\TournamentStatus::COMPLETED)
                                    <button wire:click="openCancelModal({{ $tournament->id }})" class="p-1.5 text-red-400 hover:text-white bg-red-950/40 border border-red-900/50 rounded-lg" title="Cancel">
                                        <i data-lucide="x" class="w-4 h-4"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-slate-500 italic">No tournaments found matching the filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $tournaments->links() }}
    </div>

    <!-- Create/Edit Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showCreateModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-lg w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">
                        {{ $isEditMode ? 'Edit Tournament' : 'Create New Tournament' }}
                    </h3>
                    <button wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form wire:submit.prevent="saveTournament" class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Tournament Name</label>
                        <input type="text" wire:model="name" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        @error('name') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Game</label>
                            <select wire:model="game_id" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                                <option value="">Select Game</option>
                                @foreach($games as $game)
                                    <option value="{{ $game->id }}">{{ $game->translations->first()?->name ?? $game->slug }}</option>
                                @endforeach
                            </select>
                            @error('game_id') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Entry Fee ($)</label>
                            <input type="text" wire:model="entry_fee" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('entry_fee') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Min Players</label>
                            <input type="number" wire:model="min_participants" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('min_participants') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Max Players</label>
                            <input type="number" wire:model="max_participants" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('max_participants') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Prize Pool ($)</label>
                            <input type="text" wire:model="prize_pool" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('prize_pool') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <hr class="border-slate-800 my-4">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Registration Opens</label>
                            <input type="datetime-local" wire:model="registration_open_at" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('registration_open_at') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Registration Closes</label>
                            <input type="datetime-local" wire:model="registration_close_at" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('registration_close_at') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Check-in Opens</label>
                            <input type="datetime-local" wire:model="checkin_open_at" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('checkin_open_at') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Check-in Closes</label>
                            <input type="datetime-local" wire:model="checkin_close_at" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('checkin_close_at') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Start Time</label>
                        <input type="datetime-local" wire:model="start_at" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        @error('start_at') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showCreateModal', false)" 
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            {{ $isEditMode ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Detail Modal -->
    @if($showDetailModal && $selectedTournament)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showDetailModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-3xl w-full overflow-hidden shadow-2xl relative z-10 max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">{{ $selectedTournament->name }}</h3>
                        <p class="text-[9px] text-slate-500 font-mono mt-0.5">{{ $selectedTournament->uuid }}</p>
                    </div>
                    <button wire:click="$set('showDetailModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-6 flex-grow">
                    <!-- Status Actions and Controls -->
                    <div class="bg-[#0b0f19] border border-slate-800 rounded-lg p-4">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div>
                                <span class="text-[10px] text-slate-500 font-bold uppercase block tracking-wider">CURRENT STATE</span>
                                <span class="inline-block mt-1 px-2.5 py-1 rounded bg-indigo-600/10 text-indigo-400 border border-indigo-500/20 text-xs font-black uppercase tracking-wider">
                                    {{ str_replace('_', ' ', $selectedTournament->status->value) }}
                                </span>
                            </div>
                            
                            <!-- Available State Transitions -->
                            <div class="flex flex-wrap gap-2">
                                @if($selectedTournament->status == \App\Shared\Enums\TournamentStatus::DRAFT)
                                    <button wire:click="applyTransition('publish')" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[10px] uppercase tracking-wider px-3 py-2 rounded-lg">
                                        Publish Config
                                    </button>
                                @elseif($selectedTournament->status == \App\Shared\Enums\TournamentStatus::PUBLISHED)
                                    <button wire:click="applyTransition('open_registration')" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[10px] uppercase tracking-wider px-3 py-2 rounded-lg">
                                        Open Registration
                                    </button>
                                @elseif($selectedTournament->status == \App\Shared\Enums\TournamentStatus::REGISTRATION_OPEN)
                                    <button wire:click="applyTransition('close_registration')" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[10px] uppercase tracking-wider px-3 py-2 rounded-lg">
                                        Close Registration
                                    </button>
                                @elseif($selectedTournament->status == \App\Shared\Enums\TournamentStatus::REGISTRATION_CLOSED)
                                    <button wire:click="applyTransition('open_checkin')" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[10px] uppercase tracking-wider px-3 py-2 rounded-lg">
                                        Open Check-in
                                    </button>
                                @elseif($selectedTournament->status == \App\Shared\Enums\TournamentStatus::CHECKIN_OPEN)
                                    <button wire:click="applyTransition('close_checkin')" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[10px] uppercase tracking-wider px-3 py-2 rounded-lg">
                                        Close Check-in
                                    </button>
                                @elseif($selectedTournament->status == \App\Shared\Enums\TournamentStatus::CHECKIN_CLOSED)
                                    <button wire:click="applyTransition('generate_bracket')" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[10px] uppercase tracking-wider px-3 py-2 rounded-lg">
                                        Generate Bracket
                                    </button>
                                @elseif($selectedTournament->status == \App\Shared\Enums\TournamentStatus::BRACKET_GENERATED)
                                    <button wire:click="applyTransition('start')" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[10px] uppercase tracking-wider px-3 py-2 rounded-lg">
                                        Start Matches
                                    </button>
                                @elseif($selectedTournament->status == \App\Shared\Enums\TournamentStatus::ONGOING)
                                    <button wire:click="applyTransition('complete')" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[10px] uppercase tracking-wider px-3 py-2 rounded-lg">
                                        Complete Tournament
                                    </button>
                                @elseif($selectedTournament->status == \App\Shared\Enums\TournamentStatus::CANCELLED && $selectedTournament->cancellation?->refund_required)
                                    <button wire:click="applyTransition('process_refund')" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[10px] uppercase tracking-wider px-3 py-2 rounded-lg">
                                        Trigger refunds
                                    </button>
                                @endif

                                @if($selectedTournament->status != \App\Shared\Enums\TournamentStatus::CANCELLED && $selectedTournament->status != \App\Shared\Enums\TournamentStatus::REFUNDED && $selectedTournament->status != \App\Shared\Enums\TournamentStatus::COMPLETED)
                                    <button wire:click="openCancelModal({{ $selectedTournament->id }})" class="bg-red-950 hover:bg-red-900 border border-red-900/50 text-red-400 font-bold text-[10px] uppercase tracking-wider px-3 py-2 rounded-lg">
                                        Cancel Tournament
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Cancellation details if cancelled -->
                    @if($selectedTournament->status == \App\Shared\Enums\TournamentStatus::CANCELLED && $selectedTournament->cancellation)
                        <div class="bg-red-500/5 border border-red-500/15 rounded-lg p-4">
                            <span class="text-[10px] text-red-400 font-bold uppercase tracking-wider">Cancellation Information</span>
                            <div class="grid grid-cols-2 gap-4 mt-2 text-xs">
                                <div>
                                    <span class="text-slate-500 font-medium block">Cancelled By</span>
                                    <span class="text-slate-300 font-semibold">{{ $selectedTournament->cancellation->cancelledBy?->username ?? 'System' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 font-medium block">Reason Given</span>
                                    <span class="text-slate-300">{{ $selectedTournament->cancellation->reason }}</span>
                                </div>
                            </div>
                            @if($selectedTournament->cancellation->notes)
                                <div class="mt-2 text-xs">
                                    <span class="text-slate-500 font-medium block">Cancellation Notes</span>
                                    <span class="text-slate-400 block bg-slate-900/60 p-2 rounded border border-slate-800/40 mt-1">{{ $selectedTournament->cancellation->notes }}</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Details Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div class="bg-slate-900/40 border border-slate-800/60 p-3 rounded-lg">
                            <span class="text-slate-500 font-medium block">Game type</span>
                            <span class="text-slate-200 font-semibold mt-1 block">{{ $selectedTournament->game->translations->first()?->name }}</span>
                        </div>
                        <div class="bg-slate-900/40 border border-slate-800/60 p-3 rounded-lg">
                            <span class="text-slate-500 font-medium block">Entry Fee</span>
                            <span class="text-slate-200 font-semibold mt-1 block">${{ number_format((float)$selectedTournament->entry_fee, 2) }}</span>
                        </div>
                        <div class="bg-slate-900/40 border border-slate-800/60 p-3 rounded-lg">
                            <span class="text-slate-500 font-medium block">Prize Pool</span>
                            <span class="text-slate-200 font-semibold mt-1 block">${{ number_format((float)$selectedTournament->prize_pool, 2) }}</span>
                        </div>
                        <div class="bg-slate-900/40 border border-slate-800/60 p-3 rounded-lg">
                            <span class="text-slate-500 font-medium block">Player Cap</span>
                            <span class="text-slate-200 font-semibold mt-1 block">{{ $selectedTournament->registrations->count() }} / {{ $selectedTournament->max_participants }}</span>
                        </div>
                    </div>

                    <!-- Lifecycle Milestones -->
                    <div>
                        <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block mb-2">Lifecycle Dates</span>
                        <div class="bg-slate-900/40 border border-slate-800/60 rounded-lg p-4 space-y-2.5 text-xs text-slate-300">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Registration Window</span>
                                <span>{{ $selectedTournament->registration_open_at?->format('Y-m-d H:i') ?? 'N/A' }} — {{ $selectedTournament->registration_close_at?->format('Y-m-d H:i') ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Check-in Window</span>
                                <span>{{ $selectedTournament->checkin_open_at?->format('Y-m-d H:i') ?? 'N/A' }} — {{ $selectedTournament->checkin_close_at?->format('Y-m-d H:i') ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Starts At</span>
                                <span>{{ $selectedTournament->start_at?->format('Y-m-d H:i') ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Registered Users list -->
                    <div>
                        <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block mb-2">Registered Competitors ({{ $selectedTournament->registrations->count() }})</span>
                        <div class="bg-slate-900/40 border border-slate-800/60 rounded-lg max-h-40 overflow-y-auto divide-y divide-slate-800/40">
                            @forelse($selectedTournament->registrations as $reg)
                                <div class="px-4 py-2 flex items-center justify-between text-xs">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-slate-300 font-semibold">{{ $reg->user->username }}</span>
                                        <span class="text-[9px] text-slate-500 font-mono">{{ $reg->user->uuid }}</span>
                                    </div>
                                    <div class="flex items-center space-x-2 text-[10px]">
                                        <span class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 font-semibold uppercase">{{ $reg->status->value }}</span>
                                        <span class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 font-semibold uppercase">{{ $reg->payment_status->value }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="p-4 text-center text-slate-500 italic text-xs">No registered participants yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-800 bg-[#0b0f19] flex justify-end">
                    <button wire:click="$set('showDetailModal', false)" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                        Close Details
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Cancel Modal -->
    @if($showCancelModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" wire:click="$set('showCancelModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-red-400 uppercase tracking-wider">Cancel Tournament</h3>
                    <button wire:click="$set('showCancelModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form wire:submit.prevent="cancelTournament" class="p-6 space-y-4">
                    <div class="bg-red-500/5 border border-red-500/10 text-red-400 p-3 rounded-lg text-xs leading-relaxed">
                        <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-1 text-red-400 align-text-bottom"></i>
                        <span><strong>Warning:</strong> Cancelling a tournament is irreversible. Any entry fees collected will be marked for refund.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Reason for Cancellation</label>
                        <input type="text" wire:model="cancelReason" placeholder="e.g. Insufficient players registered"
                               class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-red-500">
                        @error('cancelReason') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Additional Notes (Optional)</label>
                        <textarea wire:model="cancelNotes" placeholder="Additional details..." rows="3"
                                  class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-red-500"></textarea>
                        @error('cancelNotes') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showCancelModal', false)" 
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Keep Active
                        </button>
                        <button type="submit" 
                                class="bg-red-600 hover:bg-red-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
