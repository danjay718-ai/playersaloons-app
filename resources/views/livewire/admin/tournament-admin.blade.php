<div x-data="{ 
    showDetail: @entangle('showDetailModal'), 
    showCancel: @entangle('showCancelModal') 
}">
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <!-- Search -->
        <div class="w-full sm:w-64">
            <input type="text" wire:model.live="search" placeholder="Search tournaments..." 
                   class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 w-full">
        </div>

        <a href="{{ route('admin.tournaments.create') }}" wire:navigate
                class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm px-4 py-2.5 rounded-lg flex items-center shadow-[0_4px_12px_rgba(79,70,229,0.2)] transition-colors w-full sm:w-auto justify-center">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
            <span>Create Tournament</span>
        </a>
    </div>

    <!-- Filters Card -->
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-4 mb-6">
        <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Filter Tournaments</h2>
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="statusFilter" 
                    class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="">All Statuses</option>
                @foreach(\App\Shared\Enums\TournamentStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ strtoupper(str_replace('_', ' ', $status->name)) }}</option>
                @endforeach
            </select>

            <select wire:model.live="gameFilter" 
                    class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="">All Games</option>
                @foreach($games as $game)
                    <option value="{{ $game->id }}">{{ $game->translations->first()?->name ?? $game->slug }}</option>
                @endforeach
            </select>

            <select wire:model.live="platformFilter" 
                    class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="">All Platforms</option>
                @foreach($platforms as $platform)
                    <option value="{{ $platform->id }}">{{ $platform->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="frequencyFilter" 
                    class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="">All Frequencies</option>
                @foreach($frequencies as $freq)
                    <option value="{{ $freq }}">{{ ucfirst($freq) }}</option>
                @endforeach
            </select>

            <div class="flex items-center gap-2">
                <input type="date" wire:model.live="startDateFilter" 
                       class="bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                <span class="text-slate-500 text-sm">to</span>
                <input type="date" wire:model.live="endDateFilter" 
                       class="bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
            </div>

            <select wire:model.live="perPage" 
                    class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="5">5 per page</option>
                <option value="10">10 per page</option>
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
            </select>
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

    <!-- Tournaments Table -->
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl shadow-sm mb-6">
        <div class="overflow-x-auto min-h-[350px]">
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
                                    $statusValue = $tournament->status->value;
                                    $s = match($statusValue) {
                                        'DRAFT' => ['text' => '#94a3b8', 'border' => '#334155', 'bg' => '#0f172a'],
                                        'PUBLISHED' => ['text' => '#60a5fa', 'border' => '#1e3a8a', 'bg' => '#172554'],
                                        'REGISTRATION_OPEN' => ['text' => '#34d399', 'border' => '#064e3b', 'bg' => '#022c22'],
                                        'REGISTRATION_CLOSED' => ['text' => '#fbbf24', 'border' => '#78350f', 'bg' => '#451a03'],
                                        'CHECKIN_OPEN' => ['text' => '#e879f9', 'border' => '#701a75', 'bg' => '#4a044e'],
                                        'CHECKIN_CLOSED' => ['text' => '#fb7185', 'border' => '#881337', 'bg' => '#4c0519'],
                                        'BRACKET_GENERATED' => ['text' => '#818cf8', 'border' => '#3730a3', 'bg' => '#1e1b4b'],
                                        'ONGOING' => ['text' => '#a78bfa', 'border' => '#5b21b6', 'bg' => '#2e1065'],
                                        'COMPLETED' => ['text' => '#a1a1aa', 'border' => '#3f3f46', 'bg' => '#18181b'],
                                        'CANCELLED' => ['text' => '#f87171', 'border' => '#991b1b', 'bg' => '#450a0a'],
                                        'REFUNDED' => ['text' => '#fb923c', 'border' => '#9a3412', 'bg' => '#431407'],
                                        default => ['text' => '#94a3b8', 'border' => '#334155', 'bg' => '#0f172a'],
                                    };
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded-full border text-[9px] font-black uppercase tracking-widest" 
                                      style="color: {{ $s['text'] }}; border-color: {{ $s['border'] }}; background-color: {{ $s['bg'] }};">
                                    {{ str_replace('_', ' ', $statusValue) }}
                                </span>
                            </td>
                            <td class="p-4 text-slate-400">
                                {{ $tournament->start_at ? $tournament->start_at->format('M d, H:i') : 'N/A' }}
                            </td>
                            <td class="p-4 text-right">
                                <x-admin.action-dropdown>
                                    <div class="py-1" role="menu">
                                        <!-- Player View -->
                                        <a href="/tournaments/{{ $tournament->uuid }}" target="_blank" rel="noopener" class="flex items-center px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white group">
                                            <i data-lucide="external-link" class="w-3.5 h-3.5 mr-2 text-slate-500 group-hover:text-indigo-400"></i>
                                            View Player Page
                                        </a>
                                        <!-- Admin View -->
                                        <button @click="open = false" wire:click="selectTournament({{ $tournament->id }})" class="w-full flex items-center px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white group text-left">
                                            <i data-lucide="eye" class="w-3.5 h-3.5 mr-2 text-slate-500 group-hover:text-indigo-400"></i>
                                            Admin Details
                                        </button>
                                        
                                        <!-- State Transition Quick Actions -->
                                        @if($tournament->status == \App\Shared\Enums\TournamentStatus::DRAFT)
                                            <button @click="open = false" wire:click="applyTransitionById({{ $tournament->id }}, 'publish')" class="w-full flex items-center px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white group text-left">
                                                <i data-lucide="send" class="w-3.5 h-3.5 mr-2 text-indigo-500"></i>
                                                Publish Config
                                            </button>
                                        @elseif($tournament->status == \App\Shared\Enums\TournamentStatus::PUBLISHED)
                                            <button @click="open = false" wire:click="applyTransitionById({{ $tournament->id }}, 'open_registration')" class="w-full flex items-center px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white group text-left">
                                                <i data-lucide="door-open" class="w-3.5 h-3.5 mr-2 text-indigo-500"></i>
                                                Open Registration
                                            </button>
                                        @elseif($tournament->status == \App\Shared\Enums\TournamentStatus::REGISTRATION_OPEN)
                                            <button @click="open = false" wire:click="applyTransitionById({{ $tournament->id }}, 'close_registration')" class="w-full flex items-center px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white group text-left">
                                                <i data-lucide="door-closed" class="w-3.5 h-3.5 mr-2 text-indigo-500"></i>
                                                Close Registration
                                            </button>
                                        @elseif($tournament->status == \App\Shared\Enums\TournamentStatus::REGISTRATION_CLOSED)
                                            <button @click="open = false" wire:click="applyTransitionById({{ $tournament->id }}, 'open_checkin')" class="w-full flex items-center px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white group text-left">
                                                <i data-lucide="user-check" class="w-3.5 h-3.5 mr-2 text-indigo-500"></i>
                                                Open Check-in
                                            </button>
                                        @elseif($tournament->status == \App\Shared\Enums\TournamentStatus::CHECKIN_OPEN)
                                            <button @click="open = false" wire:click="applyTransitionById({{ $tournament->id }}, 'close_checkin')" class="w-full flex items-center px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white group text-left">
                                                <i data-lucide="user-minus" class="w-3.5 h-3.5 mr-2 text-indigo-500"></i>
                                                Close Check-in
                                            </button>
                                        @elseif($tournament->status == \App\Shared\Enums\TournamentStatus::CHECKIN_CLOSED)
                                            <button @click="open = false" wire:click="applyTransitionById({{ $tournament->id }}, 'generate_bracket')" class="w-full flex items-center px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white group text-left">
                                                <i data-lucide="git-merge" class="w-3.5 h-3.5 mr-2 text-indigo-500"></i>
                                                Generate Bracket
                                            </button>
                                        @elseif($tournament->status == \App\Shared\Enums\TournamentStatus::BRACKET_GENERATED)
                                            <button @click="open = false" wire:click="applyTransitionById({{ $tournament->id }}, 'start')" class="w-full flex items-center px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white group text-left">
                                                <i data-lucide="play" class="w-3.5 h-3.5 mr-2 text-indigo-500"></i>
                                                Start Matches
                                            </button>
                                        @elseif($tournament->status == \App\Shared\Enums\TournamentStatus::ONGOING)
                                            <button @click="open = false" wire:click="applyTransitionById({{ $tournament->id }}, 'complete')" class="w-full flex items-center px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white group text-left">
                                                <i data-lucide="check-square" class="w-3.5 h-3.5 mr-2 text-indigo-500"></i>
                                                Complete Tournament
                                            </button>
                                        @elseif($tournament->status == \App\Shared\Enums\TournamentStatus::CANCELLED && $tournament->cancellation?->refund_required)
                                            <button @click="open = false" wire:click="applyTransitionById({{ $tournament->id }}, 'process_refund')" class="w-full flex items-center px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white group text-left">
                                                <i data-lucide="refresh-cw" class="w-3.5 h-3.5 mr-2 text-emerald-500"></i>
                                                Process Refunds
                                            </button>
                                        @endif
                                    </div>
                                    
                                    <div class="py-1">
                                        <!-- Edit -->
                                        @php
                                            $canEdit = !in_array($tournament->status, [
                                                \App\Shared\Enums\TournamentStatus::CANCELLED,
                                                \App\Shared\Enums\TournamentStatus::REFUNDED,
                                                \App\Shared\Enums\TournamentStatus::COMPLETED,
                                            ]);
                                        @endphp
                                        @if($canEdit)
                                            <a href="{{ route('admin.tournaments.edit', $tournament->id) }}" wire:navigate class="flex items-center px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white group">
                                                <i data-lucide="edit" class="w-3.5 h-3.5 mr-2 text-slate-500 group-hover:text-indigo-400"></i>
                                                {{ $tournament->status == \App\Shared\Enums\TournamentStatus::DRAFT ? 'Edit Configuration' : 'Update Details' }}
                                            </a>
                                        @endif
                                        
                                        <!-- Cancel -->
                                        @if($tournament->status != \App\Shared\Enums\TournamentStatus::CANCELLED && $tournament->status != \App\Shared\Enums\TournamentStatus::REFUNDED && $tournament->status != \App\Shared\Enums\TournamentStatus::COMPLETED)
                                            <button @click="open = false" wire:click="openCancelModal({{ $tournament->id }})" class="w-full flex items-center px-4 py-2 text-xs text-orange-400 hover:bg-slate-800 hover:text-orange-300 text-left">
                                                <i data-lucide="x-circle" class="w-3.5 h-3.5 mr-2"></i>
                                                Cancel Tournament
                                            </button>
                                        @endif
                                        
                                        <!-- Delete -->
                                        @if($tournament->status == \App\Shared\Enums\TournamentStatus::DRAFT && $tournament->registrations->count() == 0)
                                            <button @click="open = false" wire:click="deleteTournament({{ $tournament->id }})" wire:confirm="Are you sure you want to delete this tournament?" class="w-full flex items-center px-4 py-2 text-xs text-red-400 hover:bg-slate-800 hover:text-red-300 text-left">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5 mr-2"></i>
                                                Delete Permanently
                                            </button>
                                        @endif
                                    </div>
                                </x-admin.action-dropdown>
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

    <!-- Detail Modal -->
    <div x-show="showDetail" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="showDetail = false; $wire.closeDetailModal()"></div>
        
        <div x-show="showDetail"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4"
             class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-3xl w-full overflow-hidden shadow-2xl relative z-10 max-h-[90vh] flex flex-col">
            
            @if($selectedTournament)
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">{{ $selectedTournament->name }}</h3>
                        <p class="text-[9px] text-slate-500 font-mono mt-0.5">{{ $selectedTournament->uuid }}</p>
                    </div>
                    <button @click="showDetail = false; $wire.closeDetailModal()" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-6 flex-grow">
                    <!-- Loading Overlay for Internal Updates -->
                    <div wire:loading wire:target="applyTransition, openCancelModal" class="absolute inset-0 bg-slate-950/50 backdrop-blur-[1px] z-50 flex items-center justify-center">
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                            <span class="text-[10px] text-indigo-400 font-bold uppercase tracking-widest mt-3">Processing...</span>
                        </div>
                    </div>

                    <!-- Status Actions and Controls -->
                    <div class="bg-[#0b0f19] border border-slate-800 rounded-lg p-4">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div>
                                <span class="text-[10px] text-slate-500 font-bold uppercase block tracking-wider">CURRENT STATE</span>
                                <span class="inline-block mt-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border 
                                    @php
                                        $statusColors = [
                                            'draft' => 'text-slate-400 border-slate-700 bg-slate-900',
                                            'published' => 'text-blue-400 border-blue-900/50 bg-blue-950/85',
                                            'registration_open' => 'text-emerald-400 border-emerald-900/50 bg-emerald-950/85',
                                            'registration_closed' => 'text-amber-400 border-amber-900/50 bg-amber-950/85',
                                            'checkin_open' => 'text-fuchsia-400 border-fuchsia-900/50 bg-fuchsia-950/85',
                                            'checkin_closed' => 'text-rose-400 border-rose-900/50 bg-rose-950/85',
                                            'bracket_generated' => 'text-indigo-400 border-indigo-900/50 bg-indigo-950/85',
                                            'ongoing' => 'text-violet-400 border-violet-800/50 bg-violet-950/85',
                                            'completed' => 'text-zinc-400 border-zinc-800 bg-zinc-900',
                                            'cancelled' => 'text-red-400 border-red-900/50 bg-red-950/85',
                                            'refunded' => 'text-orange-400 border-orange-900/50 bg-orange-950/85',
                                        ];
                                        echo $statusColors[$selectedTournament->status->value] ?? 'text-slate-400 border-slate-700 bg-slate-900';
                                    @endphp">
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

                    @if($selectedTournament->banner_url)
                        <div class="rounded-lg overflow-hidden border border-slate-800">
                            <img src="{{ $selectedTournament->banner_url }}" alt="Banner" class="w-full h-48 object-cover">
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
                        <div class="bg-slate-900/40 border border-slate-800/60 p-3 rounded-lg">
                            <span class="text-slate-500 font-medium block">Platform</span>
                            <span class="text-slate-200 font-semibold mt-1 block uppercase">{{ $selectedTournament->platform ? $selectedTournament->platform->name : 'N/A' }}</span>
                        </div>
                        <div class="bg-slate-900/40 border border-slate-800/60 p-3 rounded-lg">
                            <span class="text-slate-500 font-medium block">Frequency</span>
                            <span class="text-slate-200 font-semibold mt-1 block uppercase">{{ $selectedTournament->frequency ?? 'N/A' }}</span>
                        </div>
                        <div class="bg-slate-900/40 border border-slate-800/60 p-3 rounded-lg">
                            <span class="text-slate-500 font-medium block">Team Size</span>
                            <span class="text-slate-200 font-semibold mt-1 block">{{ $selectedTournament->team_size ?? 1 }}</span>
                        </div>
                        <div class="bg-slate-900/40 border border-slate-800/60 p-3 rounded-lg">
                            <span class="text-slate-500 font-medium block">Winning Points</span>
                            <span class="text-slate-200 font-semibold mt-1 block">{{ $selectedTournament->winning_points ?? 'N/A' }}</span>
                        </div>
                        <div class="bg-slate-900/40 border border-slate-800/60 p-3 rounded-lg">
                            <span class="text-slate-500 font-medium block">1st Prize</span>
                            <span class="text-slate-200 font-semibold mt-1 block">{{ $selectedTournament->prize_1st ? '$'.number_format((float)$selectedTournament->prize_1st, 2) : 'N/A' }}</span>
                        </div>
                        <div class="bg-slate-900/40 border border-slate-800/60 p-3 rounded-lg">
                            <span class="text-slate-500 font-medium block">Wait Time</span>
                            <span class="text-slate-200 font-semibold mt-1 block">{{ $selectedTournament->waiting_time ? $selectedTournament->waiting_time . 'm' : 'N/A' }}</span>
                        </div>
                    </div>
                    
                    @if($selectedTournament->description)
                    <div class="text-xs">
                        <span class="text-slate-500 font-medium block mb-1">Description</span>
                        <div class="bg-slate-900/40 border border-slate-800/60 p-3 rounded-lg text-slate-300">
                            {{ $selectedTournament->description }}
                        </div>
                    </div>
                    @endif

                    @if($selectedTournament->rules)
                    <div class="text-xs">
                        <span class="text-slate-500 font-medium block mb-1">Rules</span>
                        <div class="bg-slate-900/40 border border-slate-800/60 p-3 rounded-lg text-slate-300">
                            {{ $selectedTournament->rules }}
                        </div>
                    </div>
                    @endif

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
                    <button @click="showDetail = false; $wire.closeDetailModal()" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                        Close Details
                    </button>
                </div>
            @else
                <!-- Loading Skeleton -->
                <div class="p-12 flex flex-col items-center justify-center space-y-4">
                    <div class="w-12 h-12 border-4 border-indigo-500/20 border-t-indigo-500 rounded-full animate-spin"></div>
                    <p class="text-xs text-slate-500 animate-pulse uppercase tracking-widest font-bold">Loading Details...</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Cancel Modal -->
    <div x-show="showCancel" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[60] flex items-center justify-center p-4" style="display: none;">
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click="showCancel = false; $wire.closeCancelModal()"></div>
        
        <div x-show="showCancel"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
            <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                <h3 class="text-sm font-bold text-red-400 uppercase tracking-wider">Cancel Tournament</h3>
                <button @click="showCancel = false; $wire.closeCancelModal()" class="text-slate-400 hover:text-white">
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
                    <button type="button" @click="showCancel = false; $wire.closeCancelModal()" 
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
</div>
