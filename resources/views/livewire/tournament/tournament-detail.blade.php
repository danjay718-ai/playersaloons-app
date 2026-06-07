<div class="space-y-8" x-data="{ activeTab: 'overview' }">
    <!-- Messages / Error Flash -->
    @if(session()->has('message'))
        <div class="bg-emerald-950/30 border border-emerald-900/50 text-emerald-400 rounded-xl p-4 flex items-center space-x-3 shadow-md shadow-emerald-950/10">
            <i data-lucide="circle-check" class="w-5 h-5 flex-shrink-0"></i>
            <span class="text-sm font-medium">{{ session('message') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-950/30 border border-red-900/50 text-red-400 rounded-xl p-4 flex items-center space-x-3 shadow-md shadow-red-950/10">
            <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
            <span class="text-sm font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Tournament Header Banner Card -->
    <div class="bg-zinc-900 border border-zinc-850 rounded-2xl p-6 md:p-8 shadow-xl relative overflow-hidden">
        <!-- background accent glows -->
        <div class="absolute -top-20 -right-20 w-80 h-80 bg-violet-600/10 rounded-full blur-3xl pointer-events-none"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="space-y-3">
                <div class="flex items-center space-x-3">
                    <span class="text-[10px] font-bold text-violet-400 uppercase tracking-wider bg-violet-950/40 border border-violet-900/60 rounded px-2.5 py-0.5">
                        {{ $tournament->game->translations->where('locale', 'en')->first()?->name ?? $tournament->game->slug }}
                    </span>
                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest bg-zinc-800 border border-zinc-700 rounded px-2 py-0.5">
                        {{ str_replace('_', ' ', $tournament->status->value ?? $tournament->status) }}
                    </span>
                </div>
                
                <h1 class="text-3xl md:text-5xl font-black font-orbitron tracking-wider text-white">
                    {{ $tournament->name }}
                </h1>
                
                <div class="flex flex-wrap items-center gap-y-2 gap-x-6 text-sm text-zinc-400">
                    <div class="flex items-center space-x-1.5">
                        <i data-lucide="trophy" class="w-4 h-4 text-fuchsia-400"></i>
                        <span>Prize Pool: <strong class="text-zinc-200 font-orbitron">${{ number_format((float)$tournament->prize_pool, 2) }}</strong></span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <i data-lucide="ticket" class="w-4 h-4 text-violet-400"></i>
                        <span>Entry: <strong class="text-zinc-200 font-orbitron">{{ (float)$tournament->entry_fee > 0 ? '$'.number_format((float)$tournament->entry_fee, 2) : 'FREE' }}</strong></span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <i data-lucide="users" class="w-4 h-4 text-indigo-400"></i>
                        <span>Players: <strong class="text-zinc-200">{{ $tournament->registrations->count() }} / {{ $tournament->max_participants }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- Action Button Column -->
            <div class="flex-shrink-0 flex flex-col sm:flex-row md:flex-col gap-3 min-w-[200px]">
                @guest
                    <a href="/login" wire:navigate class="w-full text-center bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-bold py-3 px-6 rounded-lg transition-all duration-200 shadow-lg shadow-violet-900/20 text-sm">
                        Login to Register
                    </a>
                @else
                    @if($tournament->status->value === 'REGISTRATION_OPEN')
                        @if($isRegistered)
                            <div class="w-full bg-zinc-950/60 border border-zinc-800 text-zinc-400 rounded-lg p-3 text-center text-sm font-semibold flex items-center justify-center space-x-2">
                                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400"></i>
                                <span>Registered</span>
                            </div>
                        @else
                            <button wire:click="register" class="w-full bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-bold py-3 px-6 rounded-lg transition-all duration-200 shadow-lg shadow-violet-900/20 text-sm">
                                Register Now
                            </button>
                        @endif
                    @elseif($tournament->status->value === 'CHECKIN_OPEN')
                        @if($isCheckedIn)
                            <div class="w-full bg-emerald-950/20 border border-emerald-900/40 text-emerald-400 rounded-lg p-3 text-center text-sm font-semibold flex items-center justify-center space-x-2">
                                <i data-lucide="user-check" class="w-4 h-4"></i>
                                <span>Checked In</span>
                            </div>
                        @elseif($isRegistered)
                            <button wire:click="checkin" class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold py-3 px-6 rounded-lg transition-all duration-200 shadow-lg shadow-emerald-900/20 text-sm">
                                Check-in Here
                            </button>
                        @else
                            <div class="w-full bg-zinc-950/60 border border-zinc-800 text-zinc-500 rounded-lg p-3 text-center text-xs font-semibold">
                                Registration Closed
                            </div>
                        @endif
                    @else
                        <div class="w-full bg-zinc-950/60 border border-zinc-800 text-zinc-500 rounded-lg p-3 text-center text-xs font-semibold uppercase tracking-wider">
                            {{ str_replace('_', ' ', $tournament->status->value ?? $tournament->status) }}
                        </div>
                    @endif
                @endauth
            </div>
        </div>
    </div>

    <!-- Tabs Nav -->
    <div class="border-b border-zinc-800 flex space-x-8 text-sm font-semibold">
        <button @click="activeTab = 'overview'" :class="activeTab === 'overview' ? 'text-violet-400 border-b-2 border-violet-500 pb-3' : 'text-zinc-400 hover:text-zinc-200 pb-3'" class="transition-colors">
            Overview
        </button>
        <button @click="activeTab = 'participants'" :class="activeTab === 'participants' ? 'text-violet-400 border-b-2 border-violet-500 pb-3' : 'text-zinc-400 hover:text-zinc-200 pb-3'" class="transition-colors">
            Participants ({{ $tournament->registrations->count() }})
        </button>
        <button @click="activeTab = 'bracket'" :class="activeTab === 'bracket' ? 'text-violet-400 border-b-2 border-violet-500 pb-3' : 'text-zinc-400 hover:text-zinc-200 pb-3'" class="transition-colors">
            Brackets & Matches
        </button>
    </div>

    <!-- Tabs Content -->
    <div>
        <!-- Overview Tab -->
        <div x-show="activeTab === 'overview'" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Info column -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 space-y-4">
                    <h2 class="text-xl font-bold font-orbitron tracking-wide text-zinc-100">TOURNAMENT DETAILS</h2>
                    <p class="text-sm text-zinc-400 leading-relaxed">
                        Welcome to the official tournament page for <strong class="text-zinc-300">{{ $tournament->name }}</strong>. 
                        Bring your team or enter solo (where supported) to battle against the best players and secure your share of the prize pool.
                    </p>
                    <p class="text-sm text-zinc-400 leading-relaxed">
                        Make sure to review all rules and schedules. Ensure you are ready for check-in before the check-in window closes, otherwise your reserved slot might be forfeited.
                    </p>
                </div>

                <div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 space-y-4">
                    <h2 class="text-xl font-bold font-orbitron tracking-wide text-zinc-100">RULES & REGULATIONS</h2>
                    <ul class="list-disc list-inside text-sm text-zinc-400 space-y-2">
                        <li>Participants must check in during the check-in window.</li>
                        <li>All matches must be played on standard server settings.</li>
                        <li>Submit your match results immediately after completion.</li>
                        <li>Disputes require uploading valid screenshot evidence.</li>
                        <li>Toxicity or unsportsmanlike behavior will result in disqualification.</li>
                    </ul>
                </div>
            </div>

            <!-- Right Schedule column -->
            <div class="space-y-6">
                <div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 space-y-4">
                    <h2 class="text-xl font-bold font-orbitron tracking-wide text-zinc-100">SCHEDULE</h2>
                    
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3 text-xs">
                            <i data-lucide="calendar" class="w-4 h-4 text-violet-400 mt-0.5"></i>
                            <div>
                                <span class="block font-semibold text-zinc-400 uppercase tracking-wider">Registration Opens</span>
                                <span class="text-zinc-300 mt-1 block">{{ $tournament->registration_open_at ? \Illuminate\Support\Carbon::parse($tournament->registration_open_at)->format('M d, Y h:i A') : 'N/A' }}</span>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 text-xs">
                            <i data-lucide="calendar-x" class="w-4 h-4 text-rose-400 mt-0.5"></i>
                            <div>
                                <span class="block font-semibold text-zinc-400 uppercase tracking-wider">Registration Closes</span>
                                <span class="text-zinc-300 mt-1 block">{{ $tournament->registration_close_at ? \Illuminate\Support\Carbon::parse($tournament->registration_close_at)->format('M d, Y h:i A') : 'N/A' }}</span>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 text-xs">
                            <i data-lucide="clock" class="w-4 h-4 text-fuchsia-400 mt-0.5"></i>
                            <div>
                                <span class="block font-semibold text-zinc-400 uppercase tracking-wider">Check-in Window</span>
                                <span class="text-zinc-300 mt-1 block">
                                    {{ $tournament->checkin_open_at ? \Illuminate\Support\Carbon::parse($tournament->checkin_open_at)->format('M d, Y h:i A') : 'N/A' }} — 
                                    {{ $tournament->checkin_close_at ? \Illuminate\Support\Carbon::parse($tournament->checkin_close_at)->format('h:i A') : 'N/A' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 text-xs">
                            <i data-lucide="play" class="w-4 h-4 text-emerald-400 mt-0.5"></i>
                            <div>
                                <span class="block font-semibold text-zinc-400 uppercase tracking-wider">Tournament Starts</span>
                                <span class="text-zinc-300 mt-1 block">{{ $tournament->start_at ? \Illuminate\Support\Carbon::parse($tournament->start_at)->format('M d, Y h:i A') : 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Participants Tab -->
        <div x-show="activeTab === 'participants'">
            @if($tournament->registrations->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($tournament->registrations as $reg)
                        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 flex items-center space-x-3">
                            <div class="bg-zinc-950 p-2 rounded-full border border-zinc-800 text-zinc-400">
                                <i data-lucide="user" class="w-5 h-5"></i>
                            </div>
                            <div class="truncate">
                                <span class="block text-sm font-bold text-zinc-200 truncate">
                                    {{ $reg->user->profile?->display_name ?: $reg->user->username }}
                                </span>
                                <span class="block text-[10px] text-zinc-500 font-semibold truncate">
                                    @ @if($reg->user->username){{ $reg->user->username }}@endif
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-8 text-center text-zinc-500">
                    <i data-lucide="users" class="w-8 h-8 mx-auto text-zinc-600"></i>
                    <p class="text-xs font-semibold mt-3">No participants registered yet.</p>
                </div>
            @endif
        </div>

        <!-- Bracket Tab -->
        <div x-show="activeTab === 'bracket'">
            @if($rounds->isNotEmpty())
                <!-- Horizontal scrolling brackets container. Snap scrollable and responsive for mobile. -->
                <div class="flex flex-nowrap overflow-x-auto gap-8 pb-6 pt-4 snap-x scrollbar-thin scrollbar-thumb-zinc-800 scrollbar-track-zinc-950">
                    @foreach($rounds as $round)
                        <div class="flex-shrink-0 w-72 md:w-80 snap-start flex flex-col justify-around min-h-[400px]">
                            <!-- Round Header -->
                            <div class="text-center bg-zinc-900 border border-zinc-850 rounded-lg py-2 mb-4 font-orbitron font-bold text-xs uppercase tracking-wider text-zinc-400 shadow-sm shadow-black/10">
                                Round {{ $round->round_number }}
                            </div>
                            
                            <!-- Match List in Round -->
                            <div class="flex-grow flex flex-col justify-around space-y-6">
                                @foreach($round->matches as $match)
                                    @php
                                        $isMatchOngoing = ($match->status->value ?? $match->status) === 'ONGOING';
                                    @endphp
                                    <div class="bg-zinc-900 border {{ $isMatchOngoing ? 'border-violet-500 shadow-lg shadow-violet-500/10' : 'border-zinc-850' }} rounded-xl p-4 space-y-3 relative group">
                                        
                                        <!-- Match Status header -->
                                        <div class="flex items-center justify-between text-[9px] font-bold uppercase tracking-widest text-zinc-500 border-b border-zinc-850 pb-2">
                                            <span>Match #{{ $match->id }}</span>
                                            <span class="{{ $isMatchOngoing ? 'text-violet-400' : '' }}">
                                                {{ str_replace('_', ' ', $match->status->value ?? $match->status) }}
                                            </span>
                                        </div>
                                        
                                        <!-- Players details -->
                                        <div class="space-y-2">
                                            <!-- Player A -->
                                            @php
                                                $playerAUser = $match->playerARegistration?->user;
                                                $isPlayerAWinner = $match->winner_registration_id === $match->player_a_registration_id && $match->winner_registration_id;
                                            @endphp
                                            <div class="flex items-center justify-between text-xs py-1 px-1.5 rounded {{ $isPlayerAWinner ? 'bg-emerald-950/20 text-emerald-300 font-bold' : 'text-zinc-400' }}">
                                                <span class="truncate pr-2 max-w-[170px]">
                                                    {{ $playerAUser?->username ?? 'TBD (To Be Determined)' }}
                                                </span>
                                                @if($isPlayerAWinner)
                                                    <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-400 flex-shrink-0"></i>
                                                @endif
                                            </div>
                                            
                                            <!-- Divider -->
                                            <div class="h-[1px] bg-zinc-800/40"></div>
                                            
                                            <!-- Player B -->
                                            @php
                                                $playerBUser = $match->playerBRegistration?->user;
                                                $isPlayerBWinner = $match->winner_registration_id === $match->player_b_registration_id && $match->winner_registration_id;
                                            @endphp
                                            <div class="flex items-center justify-between text-xs py-1 px-1.5 rounded {{ $isPlayerBWinner ? 'bg-emerald-950/20 text-emerald-300 font-bold' : 'text-zinc-400' }}">
                                                <span class="truncate pr-2 max-w-[170px]">
                                                    {{ $playerBUser?->username ?? 'TBD (To Be Determined)' }}
                                                </span>
                                                @if($isPlayerBWinner)
                                                    <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-400 flex-shrink-0"></i>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        <!-- View match hub link -->
                                        @if($match->player_a_registration_id || $match->player_b_registration_id)
                                            <a href="/matches/{{ $match->uuid }}" wire:navigate class="block text-center text-[10px] font-bold text-violet-400 hover:text-violet-300 pt-2 border-t border-zinc-800/40 transition-colors">
                                                Go to Match Hub
                                            </a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-8 text-center text-zinc-500">
                    <i data-lucide="git-branch" class="w-8 h-8 mx-auto text-zinc-600"></i>
                    <p class="text-xs font-semibold mt-3">Bracket has not been generated yet. It will generate when the tournament organizer starts the tournament.</p>
                </div>
            @endif
        </div>
    </div>
</div>
