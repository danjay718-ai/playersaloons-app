<div class="space-y-10" 
     x-data="{ 
         activeTab: localStorage.getItem('tournament_tab_{{ $tournament->id }}') || 'overview', 
         canViewRestricted: @json(\Illuminate\Support\Facades\Gate::allows('viewRestrictedDetails', $tournament)),
         hasLost: @json($hasLost ?? false),
         acknowledgedElimination: false,
         showEliminationModal: false
     }" 
     x-init="
         if (!canViewRestricted) { 
             activeTab = 'overview'; 
         } else if (activeTab === 'bracket' && hasLost && !acknowledgedElimination) {
             showEliminationModal = true;
         } 
         $watch('activeTab', value => { 
             if (canViewRestricted) { 
                 localStorage.setItem('tournament_tab_{{ $tournament->id }}', value); 
                 if (value === 'bracket' && hasLost && !acknowledgedElimination) {
                     showEliminationModal = true;
                 }
             } 
         })
     ">
    <x-ui.toasts />

    <!-- Return Button -->
    <button onclick="history.back()" class="flex items-center space-x-2 text-zinc-500 hover:text-white transition-colors text-xs font-bold font-orbitron uppercase tracking-widest mb-6 group">
        <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-1 transition-transform"></i>
        <span>Return</span>
    </button>

    <!-- Tournament Header Banner (Ultra Neon) -->
    <div class="relative group bg-zinc-900/60 backdrop-blur-2xl border border-zinc-800/80 rounded-[2.5rem] p-8 md:p-12 shadow-2xl overflow-hidden">
        <!-- background dynamic glows -->
        <div class="absolute -top-40 -right-40 w-[30rem] h-[30rem] bg-cyan-500/10 rounded-full blur-[100px] pointer-events-none group-hover:bg-cyan-500/15 transition-colors duration-700"></div>
        <div class="absolute -bottom-40 -left-40 w-[30rem] h-[30rem] bg-violet-600/10 rounded-full blur-[100px] pointer-events-none group-hover:bg-violet-600/15 transition-colors duration-700"></div>
        
        <div class="relative z-10 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-10">
            <div class="space-y-6">
                <div class="flex items-center space-x-4">
                    <span class="text-[10px] font-black text-cyan-400 uppercase tracking-[0.2em] bg-cyan-950/40 border border-cyan-800/60 rounded-full px-4 py-1.5 shadow-[0_0_15px_rgba(34,211,238,0.15)]">
                        {{ $tournament->game->localizedName() }}
                    </span>
                    @php
                        $statusColors = [
                            'DRAFT' => 'text-zinc-500 border-zinc-800 bg-zinc-900/50',
                            'PUBLISHED' => 'text-blue-400 border-blue-900/50 bg-blue-950/20',
                            'REGISTRATION_OPEN' => 'text-emerald-400 border-emerald-900/50 bg-emerald-950/20 shadow-[0_0_15px_rgba(52,211,153,0.2)]',
                            'REGISTRATION_CLOSED' => 'text-amber-400 border-amber-900/50 bg-amber-950/20',
                            'CHECKIN_OPEN' => 'text-fuchsia-400 border-fuchsia-900/50 bg-fuchsia-950/20',
                            'CHECKIN_CLOSED' => 'text-rose-400 border-rose-900/50 bg-rose-950/20',
                            'BRACKET_GENERATED' => 'text-indigo-400 border-indigo-900/50 bg-indigo-950/20',
                            'ONGOING' => 'text-violet-400 border-violet-800/50 bg-violet-950/30 animate-pulse shadow-[0_0_20px_rgba(124,77,255,0.25)]',
                            'COMPLETED' => 'text-zinc-400 border-zinc-800 bg-zinc-900/80',
                            'CANCELLED' => 'text-red-400 border-red-900/50 bg-red-950/20',
                            'REFUNDED' => 'text-orange-400 border-orange-900/50 bg-orange-950/20',
                        ];
                        $colorClass = $statusColors[$tournament->status->value ?? $tournament->status] ?? 'text-zinc-500 border-zinc-800 bg-zinc-900/50';
                    @endphp
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] border rounded-full px-4 py-1.5 {{ $colorClass }}">
                        {{ str_replace('_', ' ', $tournament->status->value ?? $tournament->status) }}
                    </span>
                </div>
                
                <h1 class="text-4xl md:text-7xl font-black font-orbitron tracking-tighter text-white uppercase filter drop-shadow-[0_0_15px_rgba(255,255,255,0.1)] leading-none">
                    {{ $tournament->name }}
                </h1>
                
                <div class="flex flex-wrap items-center gap-y-4 gap-x-10">
                    <div class="space-y-1">
                        <span class="block text-[10px] font-bold text-zinc-600 uppercase tracking-widest">Prize Pool</span>
                        <div class="flex items-center space-x-2">
                            <i data-lucide="crown" class="w-5 h-5 text-fuchsia-500"></i>
                            <span class="text-2xl font-black text-fuchsia-500 font-orbitron leading-none">${{ number_format((float)$tournament->prize_pool, 2) }}</span>
                        </div>
                    </div>
                    <div class="h-10 w-[1px] bg-zinc-800/60 hidden sm:block"></div>
                    <div class="space-y-1">
                        <span class="block text-[10px] font-bold text-zinc-600 uppercase tracking-widest">Entry Fee</span>
                        <div class="flex items-center space-x-2">
                            <i data-lucide="zap" class="w-5 h-5 text-cyan-400"></i>
                            <span class="text-xl font-black text-white font-orbitron leading-none">{{ (float)$tournament->entry_fee > 0 ? '$'.number_format((float)$tournament->entry_fee, 2) : 'FREE' }}</span>
                        </div>
                    </div>
                    <div class="h-10 w-[1px] bg-zinc-800/60 hidden sm:block"></div>
                    <div class="space-y-1">
                        <span class="block text-[10px] font-bold text-zinc-600 uppercase tracking-widest">Confirmed Slots</span>
                        <div class="flex items-center space-x-2">
                            <i data-lucide="users" class="w-5 h-5 text-violet-500"></i>
                            <span class="text-xl font-black text-white font-orbitron leading-none">{{ $tournament->registrations->count() }} <span class="text-sm text-zinc-600">/</span> {{ $tournament->max_participants }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Action Area -->
            <div class="flex-shrink-0 min-w-[280px]">
                @guest
                    <a href="/login" wire:navigate class="w-full flex items-center justify-center space-x-3 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-black py-5 px-8 rounded-2xl transition-all duration-300 shadow-[0_15px_30px_-10px_rgba(124,77,255,0.4)] text-xs uppercase tracking-[0.2em] transform hover:scale-[1.02] active:scale-[0.98]">
                        <i data-lucide="log-in" class="w-5 h-5"></i>
                        <span>Sign in to Register</span>
                    </a>
                @else
                    @if($tournament->status->value === 'REGISTRATION_OPEN')
                        @if($isRegistered)
                            <div class="w-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-2xl py-5 px-8 text-center text-xs font-black uppercase tracking-[0.2em] flex items-center justify-center space-x-3 shadow-[0_0_20px_rgba(16,185,129,0.1)]">
                                <i data-lucide="shield-check" class="w-5 h-5"></i>
                                <span>Reservation Confirmed</span>
                            </div>
                        @else
                            @if(Auth::user()->hasRole('PLAYER'))
                                <button wire:click="register" class="w-full flex items-center justify-center space-x-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black py-5 px-8 rounded-2xl transition-all duration-300 shadow-[0_15px_30px_-10px_rgba(16,185,129,0.4)] text-xs uppercase tracking-[0.2em] transform hover:scale-[1.02] active:scale-[0.98]">
                                    <i data-lucide="plus-circle" class="w-5 h-5"></i>
                                    <span>Join Tournament</span>
                                </button>
                            @else
                                <div class="w-full bg-zinc-950/60 border border-zinc-800 text-zinc-500 rounded-2xl py-5 px-8 text-center text-xs font-black uppercase tracking-[0.2em]">
                                    Only players can join tournaments
                                </div>
                            @endif
                        @endif
                    @elseif($tournament->status->value === 'CHECKIN_OPEN')
                        @if($isCheckedIn)
                            <div class="w-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 rounded-2xl py-5 px-8 text-center text-xs font-black uppercase tracking-[0.2em] flex items-center justify-center space-x-3 shadow-[0_0_20px_rgba(34,211,238,0.1)]">
                                <i data-lucide="user-check" class="w-5 h-5"></i>
                                <span>Ready for Combat</span>
                            </div>
                        @elseif($isRegistered)
                            <button wire:click="checkin" class="w-full flex items-center justify-center space-x-3 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white font-black py-5 px-8 rounded-2xl transition-all duration-300 shadow-[0_15px_30px_-10px_rgba(34,211,238,0.4)] text-xs uppercase tracking-[0.2em] transform hover:scale-[1.02] active:scale-[0.98]">
                                <i data-lucide="crosshair" class="w-5 h-5"></i>
                                <span>Check-in Now</span>
                            </button>
                        @else
                            <div class="w-full bg-zinc-950/60 border border-zinc-800 text-zinc-600 rounded-2xl py-5 px-8 text-center text-xs font-black uppercase tracking-[0.2em]">
                                Recruitment Closed
                            </div>
                        @endif
                    @else
                        <div class="w-full bg-zinc-950/60 border border-zinc-800 text-zinc-500 rounded-2xl py-5 px-8 text-center text-xs font-black uppercase tracking-[0.2em]">
                            {{ str_replace('_', ' ', $tournament->status->value ?? $tournament->status) }}
                        </div>
                    @endif
                @endauth
            </div>
        </div>
    </div>

    <!-- Enhanced Navigation Tabs -->
    <div class="flex overflow-x-auto no-scrollbar items-center gap-2 bg-zinc-900/40 backdrop-blur-md border border-zinc-800/60 p-1.5 rounded-2xl w-full lg:w-auto mx-auto lg:mx-0">
        <button @click="activeTab = 'overview'" 
            :class="activeTab === 'overview' ? 'bg-zinc-800 text-white shadow-lg' : 'text-zinc-500 hover:text-zinc-300'"
            class="px-6 md:px-8 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 whitespace-nowrap">
            Overview
        </button>

        <button 
            @can('viewRestrictedDetails', $tournament)
                @click="activeTab = 'participants'" 
                :class="activeTab === 'participants' ? 'bg-zinc-800 text-white shadow-lg' : 'text-zinc-500 hover:text-zinc-300'"
                class="px-6 md:px-8 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 whitespace-nowrap"
            @else
                disabled
                class="px-6 md:px-8 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 whitespace-nowrap opacity-40 cursor-not-allowed text-zinc-600"
                title="Only tournament participants can view players"
            @endcan>
            Players ({{ $tournament->registrations->count() }})
        </button>

        <button 
            @can('viewRestrictedDetails', $tournament)
                @click="activeTab = 'bracket'" 
                :class="activeTab === 'bracket' ? 'bg-zinc-800 text-white shadow-lg' : 'text-zinc-500 hover:text-zinc-300'"
                class="px-6 md:px-8 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 whitespace-nowrap"
            @else
                disabled
                class="px-6 md:px-8 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 whitespace-nowrap opacity-40 cursor-not-allowed text-zinc-600"
                title="Only tournament participants can view matches"
            @endcan>
            Matches
        </button>

        <button 
            @can('viewRestrictedDetails', $tournament)
                @click="activeTab = 'activity'" 
                :class="activeTab === 'activity' ? 'bg-zinc-800 text-white shadow-lg' : 'text-zinc-500 hover:text-zinc-300'"
                class="px-6 md:px-8 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 whitespace-nowrap"
            @else
                disabled
                class="px-6 md:px-8 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 whitespace-nowrap opacity-40 cursor-not-allowed text-zinc-600"
                title="Only tournament participants can view activity"
            @endcan>
            Activity
        </button>
    </div>

    <!-- Tabs Content -->
    <div class="relative min-h-[500px]">
        <!-- Overview Tab -->
        <div x-show="activeTab === 'overview'" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            <div class="lg:col-span-2 space-y-10">
                <section class="space-y-6">
                    <h2 class="text-2xl font-black font-orbitron tracking-widest text-white flex items-center space-x-3">
                        <span class="w-1.5 h-8 bg-cyan-500 rounded-full"></span>
                        <span>MISSION DIRECTIVE</span>
                    </h2>
                    <div class="bg-zinc-900/40 backdrop-blur-md border border-zinc-800/60 rounded-[2rem] p-8 space-y-6">
                        <p class="text-lg text-zinc-300 font-medium leading-relaxed">
                            Operation <strong class="text-white underline decoration-cyan-500/50 underline-offset-4">{{ $tournament->name }}</strong> is now active. 
                            Assemble your loadout and prepare to engage the platform's top-tier competitors.
                        </p>
                        <p class="text-zinc-500 leading-relaxed font-medium">
                            Execute your matches according to the battle grid. Ensure all protocols are followed, including mandatory check-in and result verification. Disputed outcomes will undergo high-priority review by command moderators.
                        </p>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4">
                            <div class="bg-zinc-950/60 border border-zinc-800/60 rounded-2xl p-5 space-y-3">
                                <div class="flex items-center space-x-2 text-cyan-400">
                                    <i data-lucide="target" class="w-5 h-5"></i>
                                    <span class="text-xs font-black uppercase tracking-widest">Format</span>
                                </div>
                                <span class="block text-xl font-bold text-white uppercase font-orbitron">Single Elimination</span>
                            </div>
                            <div class="bg-zinc-950/60 border border-zinc-800/60 rounded-2xl p-5 space-y-3">
                                <div class="flex items-center space-x-2 text-fuchsia-500">
                                    <i data-lucide="award" class="w-5 h-5"></i>
                                    <span class="text-xs font-black uppercase tracking-widest">Victory Prize</span>
                                </div>
                                <span class="block text-xl font-bold text-white uppercase font-orbitron">${{ number_format((float)$tournament->prize_pool * 0.6, 2) }} <span class="text-[10px] text-zinc-600 tracking-normal">(Est.)</span></span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-6">
                    <h2 class="text-2xl font-black font-orbitron tracking-widest text-white flex items-center space-x-3">
                        <span class="w-1.5 h-8 bg-fuchsia-500 rounded-full"></span>
                        <span>ENGAGEMENT PROTOCOLS</span>
                    </h2>
                    <div class="bg-zinc-900/40 backdrop-blur-md border border-zinc-800/60 rounded-[2rem] p-8">
                        <ul class="space-y-4">
                            @foreach([
                                'Check-in is mandatory during the designated window.',
                                'Standard competitive server parameters only.',
                                'Instant result reporting required post-engagement.',
                                'Disputes necessitate high-definition screenshot intelligence.',
                                'Zero tolerance for unsportsmanlike conduct or hacking.'
                            ] as $rule)
                                <li class="flex items-start space-x-4 group">
                                    <div class="w-6 h-6 rounded-full bg-zinc-950 border border-zinc-800 flex items-center justify-center mt-0.5 group-hover:border-fuchsia-500 transition-colors duration-300">
                                        <i data-lucide="check" class="w-3.5 h-3.5 text-fuchsia-500 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    </div>
                                    <span class="text-zinc-400 group-hover:text-zinc-200 transition-colors font-medium">{{ $rule }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            </div>

            <!-- Enhanced Timeline Sidebar -->
            <div class="space-y-10">
                <section class="space-y-6">
                    <h2 class="text-xl font-black font-orbitron tracking-widest text-white">CHRONOLOGY</h2>
                    <div class="relative bg-zinc-900/40 backdrop-blur-md border border-zinc-800/60 rounded-[2rem] p-8 space-y-10 overflow-hidden">
                        <div class="absolute top-12 bottom-12 left-11 w-px bg-gradient-to-b from-cyan-500 via-violet-500 to-fuchsia-500 opacity-20"></div>
                        
                        @foreach([
                            ['icon' => 'calendar', 'color' => 'text-cyan-400', 'label' => 'Recruitment Opens', 'time' => $tournament->registration_open_at],
                            ['icon' => 'calendar-x', 'color' => 'text-rose-400', 'label' => 'Recruitment Ends', 'time' => $tournament->registration_close_at],
                            ['icon' => 'clock', 'color' => 'text-fuchsia-400', 'label' => 'Protocol Check-in', 'time' => $tournament->checkin_open_at],
                            ['icon' => 'zap', 'color' => 'text-emerald-400', 'label' => 'Combat Initiation', 'time' => $tournament->start_at],
                        ] as $item)
                            <div class="flex items-start space-x-6 relative z-10 group">
                                <div class="w-7 h-7 rounded-full bg-zinc-950 border-2 border-zinc-800 flex items-center justify-center shrink-0 group-hover:border-zinc-400 transition-colors">
                                    <i data-lucide="{{ $item['icon'] }}" class="w-3.5 h-3.5 {{ $item['color'] }}"></i>
                                </div>
                                <div class="space-y-1">
                                    <span class="block text-[10px] font-black text-zinc-600 uppercase tracking-widest">{{ $item['label'] }}</span>
                                    <span class="text-sm font-bold text-zinc-300">
                                        {{ $item['time'] ? \Illuminate\Support\Carbon::parse($item['time'])->format('M d, Y') : 'TBD' }}
                                        <span class="text-xs text-zinc-500 ml-1 opacity-60">{{ $item['time'] ? \Illuminate\Support\Carbon::parse($item['time'])->format('h:i A') : '' }}</span>
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>

        <!-- Warriors Tab (Participant Grid) -->
        @can('viewRestrictedDetails', $tournament)
        <div x-show="activeTab === 'participants'" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
            @if($tournament->registrations->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($tournament->registrations as $reg)
                        <div class="group bg-zinc-900/40 backdrop-blur-md border border-zinc-800/60 rounded-2xl p-5 flex items-center space-x-4 hover:border-violet-500/50 hover:bg-violet-950/10 transition-all duration-300">
                            <div class="w-12 h-12 bg-zinc-950 rounded-xl border border-zinc-800 flex items-center justify-center text-zinc-600 group-hover:text-violet-400 transition-colors">
                                <i data-lucide="user" class="w-6 h-6"></i>
                            </div>
                            <div class="truncate">
                                <span class="block text-sm font-black text-white truncate font-orbitron tracking-tight">
                                    {{ $reg->user->profile?->display_name ?: $reg->user->username }}
                                </span>
                                <span class="block text-[10px] text-zinc-600 font-black uppercase tracking-widest truncate">
                                    #{{ $reg->user->id }} • @ {{ $reg->user->username }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-zinc-900/40 backdrop-blur-md border border-zinc-800 rounded-[2rem] p-20 text-center relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-b from-transparent to-rose-950/5 pointer-events-none"></div>
                    <i data-lucide="users" class="w-12 h-12 mx-auto text-zinc-700 mb-4"></i>
                    <h3 class="text-lg font-black text-zinc-300 font-orbitron tracking-widest">NO WARRIORS DETECTED</h3>
                    <p class="text-xs font-medium text-zinc-500 mt-2">The recruitment roster is currently empty. Be the first to join.</p>
                </div>
            @endif
        </div>
        @endcan

        <!-- Activity Tab -->
        @can('viewRestrictedDetails', $tournament)
        <div x-show="activeTab === 'activity'" x-cloak style="display: none;" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="bg-zinc-900/40 backdrop-blur-md border border-zinc-800/60 rounded-[2rem] p-8">
                <h2 class="text-2xl font-black font-orbitron tracking-widest text-white mb-8 flex items-center space-x-3">
                    <span class="w-1.5 h-8 bg-amber-500 rounded-full"></span>
                    <span>TOURNAMENT ACTIVITY</span>
                </h2>
                
                @if(isset($activityLogs) && $activityLogs->count() > 0)
                    <div class="space-y-6 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-zinc-800 before:to-transparent">
                        @foreach($activityLogs as $activity)
                            <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full border border-zinc-800 bg-zinc-900 text-zinc-500 group-[.is-active]:text-amber-500 group-[.is-active]:border-amber-500/30 group-[.is-active]:bg-amber-500/10 shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 shadow-[0_0_15px_rgba(245,158,11,0.1)] transition-colors z-10">
                                    <i data-lucide="activity" class="w-4 h-4"></i>
                                </div>
                                <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-2xl bg-zinc-950/50 border border-zinc-800/50 shadow-sm relative">
                                    <div class="flex flex-col space-y-1">
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                            <span class="text-sm font-bold text-zinc-300">{{ $activity->description }}</span>
                                            <span class="text-[10px] font-medium text-zinc-500 whitespace-nowrap">{{ $activity->created_at->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-10">
                        <i data-lucide="activity" class="w-12 h-12 mx-auto text-zinc-700 mb-4"></i>
                        <h3 class="text-lg font-black text-zinc-400 font-orbitron tracking-widest uppercase">No Activity Yet</h3>
                        <p class="text-sm font-medium text-zinc-600 mt-2">Activity feed will populate as the tournament progresses.</p>
                    </div>
                @endif
            </div>
        </div>
        @endcan

        <!-- Battle Grid (Dynamic Brackets) -->
        @can('viewRestrictedDetails', $tournament)
        <div x-show="activeTab === 'bracket'" x-cloak style="display: none;" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="w-full">
            @if($rounds->isNotEmpty())
                <div class="flex flex-nowrap overflow-x-auto gap-12 pb-10 pt-6 snap-x scrollbar-thin scrollbar-thumb-zinc-800 scrollbar-track-zinc-950 relative">
                    <!-- Dynamic Bracket Connectors Container (SVG Background) -->
                    @foreach($rounds as $round)
                        <div class="flex-shrink-0 w-80 snap-start flex flex-col justify-around min-h-[600px] space-y-12">
                            <!-- Round Neon Header -->
                            <div class="relative group">
                                <div class="absolute -inset-1 bg-gradient-to-r from-cyan-500 to-violet-500 rounded-2xl blur opacity-10 group-hover:opacity-25 transition duration-1000"></div>
                                <div class="relative text-center bg-zinc-950 border border-zinc-800 rounded-xl py-3 font-orbitron font-black text-[10px] uppercase tracking-[0.3em] text-white shadow-xl">
                                    Round {{ $round->round_number }}
                                </div>
                            </div>
                            
                            <!-- Match List in Round -->
                            <div class="flex-grow flex flex-col justify-around space-y-16">
                                @foreach($round->matches as $match)
                                    @php
                                        $matchStatus = $match->status->value ?? $match->status;
                                        $isMatchOngoing = $matchStatus === 'ONGOING' || $matchStatus === 'READY';
                                        $isMatchCompleted = $matchStatus === 'COMPLETED' || $matchStatus === 'FORFEITED';
                                    @endphp
                                    <div class="relative">
                                        <!-- Vertical line connectors logic (simplified) -->
                                        <div class="bg-zinc-900/60 backdrop-blur-md border {{ $isMatchOngoing ? 'border-cyan-500/50 shadow-[0_0_25px_rgba(34,211,238,0.15)] ring-1 ring-cyan-500/30' : 'border-zinc-800' }} rounded-2xl p-5 space-y-4 relative z-10 transition-all duration-300 hover:scale-[1.03]">
                                            
                                            <div class="flex items-center justify-between text-[9px] font-black uppercase tracking-widest border-b border-zinc-800/50 pb-3">
                                                <span class="text-zinc-600">ID: #{{ $match->id }}</span>
                                                <span class="{{ $isMatchOngoing ? 'text-cyan-400' : ($isMatchCompleted ? 'text-zinc-500' : 'text-fuchsia-400') }}">
                                                    {{ str_replace('_', ' ', $matchStatus) }}
                                                </span>
                                            </div>
                                            
                                            <div class="space-y-3">
                                                <!-- Player A -->
                                                @php
                                                    $playerAUser = $match->playerARegistration?->user;
                                                    $isPlayerAWinner = $match->winner_registration_id === $match->player_a_registration_id && $match->winner_registration_id;
                                                @endphp
                                                <div class="flex items-center justify-between py-2 px-3 rounded-xl transition-colors {{ $isPlayerAWinner ? 'bg-emerald-500/10 border border-emerald-500/30' : ($playerAUser ? 'bg-zinc-950/40 border border-zinc-900/50' : 'bg-transparent border border-dashed border-zinc-850') }}">
                                                    <div class="flex items-center space-x-3 truncate">
                                                        <div class="w-6 h-6 rounded-lg bg-zinc-950 flex items-center justify-center text-[10px] font-bold {{ $isPlayerAWinner ? 'text-emerald-400' : 'text-zinc-700' }}">A</div>
                                                        <span class="text-xs font-bold truncate {{ $isPlayerAWinner ? 'text-emerald-400' : ($playerAUser ? 'text-zinc-200' : 'text-zinc-600 italic') }}">
                                                            {{ $playerAUser?->username ?? 'Waiting...' }}
                                                        </span>
                                                    </div>
                                                    @if($isPlayerAWinner)
                                                        <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-400"></i>
                                                    @endif
                                                </div>
                                                
                                                <div class="flex justify-center -my-2 relative z-20">
                                                    <span class="text-[9px] font-black text-zinc-700 bg-zinc-900 px-2 py-0.5 rounded border border-zinc-800 uppercase tracking-widest">VS</span>
                                                </div>
                                                
                                                <!-- Player B -->
                                                @php
                                                    $playerBUser = $match->playerBRegistration?->user;
                                                    $isPlayerBWinner = $match->winner_registration_id === $match->player_b_registration_id && $match->winner_registration_id;
                                                @endphp
                                                <div class="flex items-center justify-between py-2 px-3 rounded-xl transition-colors {{ $isPlayerBWinner ? 'bg-emerald-500/10 border border-emerald-500/30' : ($playerBUser ? 'bg-zinc-950/40 border border-zinc-900/50' : 'bg-transparent border border-dashed border-zinc-850') }}">
                                                    <div class="flex items-center space-x-3 truncate">
                                                        <div class="w-6 h-6 rounded-lg bg-zinc-950 flex items-center justify-center text-[10px] font-bold {{ $isPlayerBWinner ? 'text-emerald-400' : 'text-zinc-700' }}">B</div>
                                                        <span class="text-xs font-bold truncate {{ $isPlayerBWinner ? 'text-emerald-400' : ($playerBUser ? 'text-zinc-200' : 'text-zinc-600 italic') }}">
                                                            {{ $playerBUser?->username ?? 'Waiting...' }}
                                                        </span>
                                                    </div>
                                                    @if($isPlayerBWinner)
                                                        <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-400"></i>
                                                    @endif
                                                </div>
                                            </div>
                                            
                                            @if($match->player_a_registration_id || $match->player_b_registration_id)
                                                <a href="/matches/{{ $match->uuid }}" wire:navigate class="w-full flex items-center justify-center space-x-2 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 hover:border-cyan-500/40 text-[9px] font-black text-zinc-500 hover:text-cyan-400 uppercase tracking-[0.2em] transition-all duration-300">
                                                    <span>Enter Hub</span>
                                                    <i data-lucide="external-link" class="w-3 h-3"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-zinc-900/40 backdrop-blur-md border border-zinc-800 rounded-[2.5rem] p-24 text-center relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-tr from-transparent to-indigo-950/10 pointer-events-none"></div>
                    <i data-lucide="git-branch" class="w-16 h-16 mx-auto text-zinc-800 mb-6"></i>
                    <h3 class="text-xl font-black text-zinc-400 font-orbitron tracking-widest uppercase">Grid Synchronization Pending</h3>
                    <p class="text-sm font-medium text-zinc-600 mt-4 max-w-sm mx-auto leading-relaxed">The battle grid is currently offline. Brackets will materialize once command authorizes the tournament start protocol.</p>
                </div>
            @endif
        </div>
        @endcan

    <!-- Elimination Modal -->
    <div x-show="showEliminationModal" 
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-zinc-950/85 backdrop-blur-md"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak>
        <div class="relative bg-zinc-900 border border-red-500/30 rounded-3xl p-8 max-w-md w-full shadow-[0_0_50px_rgba(239,68,68,0.2)] space-y-6"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="scale-95 translate-y-4"
             x-transition:enter-end="scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="scale-100 translate-y-0"
             x-transition:leave-end="scale-95 translate-y-4">
            
            <!-- Glow indicator -->
            <div class="mx-auto w-16 h-16 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center text-red-500 animate-pulse">
                <i data-lucide="skull" class="w-8 h-8"></i>
            </div>

            <div class="text-center space-y-2">
                <h3 class="text-2xl font-black font-orbitron tracking-widest text-white uppercase">Eliminated</h3>
                <p class="text-zinc-400 text-sm font-medium leading-relaxed">
                    You have been knocked out of this tournament. Would you still like to proceed to view the matches and bracket?
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 pt-2">
                <button @click="activeTab = 'overview'; showEliminationModal = false;" 
                        class="flex-1 py-3 rounded-xl border border-zinc-800 hover:border-zinc-700 text-[10px] font-black text-zinc-500 hover:text-white uppercase tracking-widest transition-all duration-300">
                    Go Back
                </button>
                <button @click="acknowledgedElimination = true; showEliminationModal = false;" 
                        class="flex-1 py-3 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-[10px] font-black text-white uppercase tracking-widest shadow-[0_10px_20px_-5px_rgba(239,68,68,0.3)] transition-all duration-300">
                    Continue
                </button>
            </div>
        </div>
    </div>
</div>
