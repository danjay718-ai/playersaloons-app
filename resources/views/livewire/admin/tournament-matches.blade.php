<div>
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('admin.tournaments') }}" wire:navigate class="text-indigo-400 hover:text-indigo-300 text-xs font-bold uppercase tracking-widest flex items-center mb-2">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Back to Tournaments
            </a>
            <h1 class="text-2xl font-black text-white uppercase tracking-wider">{{ $tournament->name }} - Matches</h1>
        </div>
        
        <!-- Search -->
        <div class="w-full sm:w-64">
            <input type="text" wire:model.live="search" placeholder="Search players..." 
                   class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 w-full">
        </div>
    </div>

    <!-- Filters Card -->
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-4 mb-6">
        <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Filter Matches</h2>
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="statusFilter" 
                    class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="">All Statuses</option>
                @foreach(\App\Shared\Enums\MatchStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ strtoupper(str_replace('_', ' ', $status->name)) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Matches Fixtures List -->
    <div class="flex flex-col gap-4">
        @forelse($matches as $match)
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-lg hover:border-slate-700 transition-colors relative flex flex-col md:flex-row items-center py-4 px-6 gap-6" wire:key="match-{{ $match->id }}">
                
                <!-- Match Metadata (Left) -->
                <div class="flex flex-row md:flex-col items-center md:items-start justify-between w-full md:w-auto md:min-w-[120px] shrink-0 border-b md:border-b-0 md:border-r border-slate-800 pb-4 md:pb-0 md:pr-6">
                    <span class="text-xs text-slate-500 font-mono font-bold">MATCH #{{ $match->id }}</span>
                    @php
                        $statusValue = $match->status->value;
                        $s = match($statusValue) {
                            'READY' => ['text' => '#60a5fa', 'bg' => '#172554'],
                            'ONGOING' => ['text' => '#a78bfa', 'bg' => '#2e1065'],
                            'COMPLETED' => ['text' => '#34d399', 'bg' => '#022c22'],
                            'CANCELLED' => ['text' => '#f87171', 'bg' => '#450a0a'],
                            'FORFEITED' => ['text' => '#fbbf24', 'bg' => '#451a03'],
                            'WAITING_FOR_CONFIRMATION' => ['text' => '#e879f9', 'bg' => '#4a044e'],
                            default => ['text' => '#94a3b8', 'bg' => '#0f172a'],
                        };
                    @endphp
                    <span class="inline-flex mt-0 md:mt-2 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest" 
                          style="color: {{ $s['text'] }}; background-color: {{ $s['bg'] }};">
                        {{ str_replace('_', ' ', $statusValue) }}
                    </span>
                </div>

                <!-- Fixture Center (Teams/Players & VS) -->
                <div class="flex-grow flex items-center justify-center w-full max-w-3xl mx-auto gap-4 sm:gap-8">
                    
                    <!-- Player A (Home) -->
                    <div class="flex-1 flex items-center justify-end gap-4 text-right">
                        @if($match->playerARegistration)
                            @php
                                $isTeam = $match->playerARegistration->team_id !== null;
                                $nameA = $isTeam ? $match->playerARegistration->team->name : $match->playerARegistration->user->username;
                                $avatarA = $isTeam ? $match->playerARegistration->team->logo_url : $match->playerARegistration->user->profile?->avatar_url;
                                $initialA = strtoupper(substr($nameA, 0, 1));
                                $isWinnerA = $match->winner_registration_id == $match->player_a_registration_id;
                            @endphp
                            
                            <div class="flex flex-col items-end">
                                <span class="text-sm md:text-base font-bold text-slate-200 truncate max-w-[120px] sm:max-w-[180px] {{ $isWinnerA ? 'text-amber-400' : '' }}">{{ $nameA }}</span>
                                @if($isWinnerA)
                                    <span class="text-[10px] text-amber-500/80 font-bold uppercase tracking-widest mt-0.5"><i data-lucide="crown" class="w-3 h-3 inline"></i> Winner</span>
                                @endif
                            </div>
                            
                            <div class="w-10 h-10 md:w-14 md:h-14 rounded-full overflow-hidden border-2 shrink-0 {{ $isWinnerA ? 'border-amber-400 shadow-[0_0_15px_rgba(251,191,36,0.2)]' : 'border-slate-700' }} bg-slate-800 flex items-center justify-center text-slate-400 font-black text-lg">
                                @if($avatarA)
                                    <img src="{{ $avatarA }}" alt="{{ $nameA }}" class="w-full h-full object-cover">
                                @else
                                    {{ $initialA }}
                                @endif
                            </div>
                        @else
                            <span class="text-sm text-slate-500 italic">TBD</span>
                            <div class="w-10 h-10 md:w-14 md:h-14 rounded-full border-2 border-slate-800 border-dashed shrink-0 flex items-center justify-center text-slate-600">
                                <i data-lucide="user" class="w-5 h-5"></i>
                            </div>
                        @endif
                    </div>

                    <!-- VS Badge -->
                    <div class="shrink-0 flex flex-col items-center justify-center px-2">
                        <div class="bg-indigo-900/40 border border-indigo-500/30 text-indigo-300 px-3 py-1.5 rounded-lg text-xs font-black italic shadow-[0_0_15px_rgba(79,70,229,0.1)]">
                            VS
                        </div>
                    </div>

                    <!-- Player B (Away) -->
                    <div class="flex-1 flex items-center justify-start gap-4 text-left">
                        @if($match->playerBRegistration)
                            @php
                                $isTeam = $match->playerBRegistration->team_id !== null;
                                $nameB = $isTeam ? $match->playerBRegistration->team->name : $match->playerBRegistration->user->username;
                                $avatarB = $isTeam ? $match->playerBRegistration->team->logo_url : $match->playerBRegistration->user->profile?->avatar_url;
                                $initialB = strtoupper(substr($nameB, 0, 1));
                                $isWinnerB = $match->winner_registration_id == $match->player_b_registration_id;
                            @endphp
                            
                            <div class="w-10 h-10 md:w-14 md:h-14 rounded-full overflow-hidden border-2 shrink-0 {{ $isWinnerB ? 'border-amber-400 shadow-[0_0_15px_rgba(251,191,36,0.2)]' : 'border-slate-700' }} bg-slate-800 flex items-center justify-center text-slate-400 font-black text-lg">
                                @if($avatarB)
                                    <img src="{{ $avatarB }}" alt="{{ $nameB }}" class="w-full h-full object-cover">
                                @else
                                    {{ $initialB }}
                                @endif
                            </div>
                            
                            <div class="flex flex-col items-start">
                                <span class="text-sm md:text-base font-bold text-slate-200 truncate max-w-[120px] sm:max-w-[180px] {{ $isWinnerB ? 'text-amber-400' : '' }}">{{ $nameB }}</span>
                                @if($isWinnerB)
                                    <span class="text-[10px] text-amber-500/80 font-bold uppercase tracking-widest mt-0.5"><i data-lucide="crown" class="w-3 h-3 inline"></i> Winner</span>
                                @endif
                            </div>
                        @else
                            <div class="w-10 h-10 md:w-14 md:h-14 rounded-full border-2 border-slate-800 border-dashed shrink-0 flex items-center justify-center text-slate-600">
                                <i data-lucide="user" class="w-5 h-5"></i>
                            </div>
                            <span class="text-sm text-slate-500 italic">TBD</span>
                        @endif
                    </div>
                    
                </div>
            </div>
        @empty
            <div class="w-full bg-slate-900 border border-slate-800 rounded-xl p-8 text-center">
                <div class="w-16 h-16 rounded-full bg-slate-800 flex items-center justify-center mx-auto mb-4 text-slate-600">
                    <i data-lucide="swords" class="w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-300">No matches found</h3>
                <p class="text-sm text-slate-500 mt-2">Try adjusting your filters or search query.</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-8 flex items-center justify-between">
        <div class="text-xs text-slate-500">
            Showing {{ $matches->firstItem() }} to {{ $matches->lastItem() }} of {{ $matches->total() }} matches
        </div>
        <div class="flex gap-2">
            {{ $matches->links('vendor.livewire.custom-pagination') }}
        </div>
    </div>
</div>
