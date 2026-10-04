@php
    $statusValue = $tournament->status->value ?? (string) $tournament->status;
    $joinLabel = $tournament->competition_type === \App\Shared\Enums\CompetitionType::HEAD_TO_HEAD
        ? __('Join Competition')
        : __('Join Tournament');
    $isPlayerView = ! (Auth::check() && Auth::user()->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER']));
    $statusColorClass = [
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
    ][$statusValue] ?? 'text-zinc-500 border-zinc-800 bg-zinc-900/50';
@endphp
<div class="player-tournament-detail space-y-10" wire:poll.10s.visible
     @tournament-content-opened.window="if ($event.detail.tournamentUuid === @js($tournament->uuid)) { activeTab = $event.detail.tab; $nextTick(() => scrollToTab($event.detail.tab, $event.detail.focus)); }"
     x-data="{ 
         activeTab: @entangle('activeTab').live,
         canViewRestricted: @json($canViewRestricted),
         showJoinModal: false,
         showUnderfilledNotice: false,
         bracketView: 'bracket',
         loadedSections: @js(array_keys($loadedSections ?? [])),
         loadingSection: null,
         scrollEnabled: @js($isPlayerView),
         realtimeChannel: null,
         realtimeRefreshTimer: null,
         initRealtime() {
             const echo = window.ensurePlayerSaloonsEcho?.();
             if (!echo) return;
             this.realtimeChannel = `tournament.{{ $tournament->uuid }}`;
             echo.channel(this.realtimeChannel).listen('.tournament.updated', () => {
                 clearTimeout(this.realtimeRefreshTimer);
                 this.realtimeRefreshTimer = setTimeout(() => this.$wire.$refresh(), 150);
             });
         },
         destroy() {
             clearTimeout(this.realtimeRefreshTimer);
             if (this.realtimeChannel) window.ensurePlayerSaloonsEcho?.()?.leave(this.realtimeChannel);
         },
         openJoinModal() {
             this.$wire.prepareRegistrationPrompt().then(() => {
                 this.showJoinModal = true;
                 this.$nextTick(() => this.$refs.joinGameId?.focus());
             });
         },
         sectionFor(tab) {
             if (['participants', 'team-lobby'].includes(tab)) return 'participants';
             if (tab === 'matches') return 'bracket';
             if (tab === 'activity') return 'activity';
             return null;
         },
         scrollToTab(tab, focus = null) {
             if (this.scrollEnabled && this.activeTab === tab) scrollToTournamentContent(this.$root, tab, this.bracketView, focus);
         },
         selectBracketView(view) {
             this.bracketView = view;
             this.$nextTick(() => this.scrollToTab('matches'));
         },
         async selectTab(tab, scroll = true) {
             this.activeTab = tab;
             if (tab === 'submit-results') this.$dispatch('tournament-result-selected', { tournamentUuid: @js($tournament->uuid) });
             const section = this.sectionFor(tab);
             if (section && !this.loadedSections.includes(section)) {
                 this.loadingSection = section;
                 try {
                     await this.$wire.loadSection(tab);
                     if (!this.loadedSections.includes(section)) this.loadedSections.push(section);
                 } finally {
                     this.loadingSection = null;
                 }
             }
             if (scroll) this.$nextTick(() => this.scrollToTab(tab));
         },
     }" 
     x-init="
         initRealtime();
         if (!canViewRestricted && !['overview', 'streams'].includes(activeTab)) {
             activeTab = 'overview';
         }
         if (canViewRestricted) selectTab(activeTab, activeTab === 'submit-results');
         const underfilledNoticeKey = 'v2_underfilled_notice_{{ $tournament->uuid }}_{{ optional($tournament->start_at)->timestamp }}';
         if (@js($shouldShowUnderfilledNotice) && !localStorage.getItem(underfilledNoticeKey)) {
             showUnderfilledNotice = true;
             localStorage.setItem(underfilledNoticeKey, 'shown');
         }
     ">
    <x-ui.toasts />

    <!-- Return Button -->
    <button onclick="history.back()" class="flex items-center space-x-2 text-zinc-500 hover:text-white transition-colors text-xs font-bold font-orbitron uppercase tracking-widest mb-6 group">
        <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-1 transition-transform"></i>
        <span>Return</span>
    </button>

    <div id="tournament-top-timer" wire:ignore></div>

    <!-- Auto-Cancel Notice -->
    @if($tournament->is_auto_cancel_underfilled)
        <div class="bg-amber-900/30 border border-amber-500/50 rounded-[1.5rem] p-4 md:px-6 mb-6 flex items-start md:items-center space-x-4 shadow-[0_0_20px_rgba(245,158,11,0.1)]">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-500 mt-1 md:mt-0">
                <i data-lucide="alert-circle" class="w-5 h-5"></i>
            </div>
            <div>
                <h3 class="text-xs font-black text-amber-400 uppercase tracking-widest">Auto-Cancel Active</h3>
                <p class="text-[11px] font-medium text-amber-200/70 mt-0.5 leading-relaxed">
                    @if((int) $tournament->workflow_version === 2)
                        The first round starts at the scheduled time once at least two teams have joined. With fewer than two, registration remains open until this occurrence ends; it is then cancelled and refunded.
                    @else
                        If this tournament does not reach the minimum required participants ({{ $tournament->min_participants }}) by the start time, it will be automatically cancelled and all entry fees will be refunded.
                    @endif
                </p>
            </div>
        </div>
    @endif

    <!-- Tournament Header Banner -->
    <div class="relative group bg-zinc-900/60 backdrop-blur-2xl border border-zinc-800/80 rounded-[2.5rem] {{ $isPlayerView ? 'p-5 md:p-7' : 'p-8 md:p-12' }} shadow-2xl overflow-hidden">
        <!-- Background dynamic glows -->
        <div class="absolute -top-40 -right-40 w-[30rem] h-[30rem] bg-cyan-500/10 rounded-full blur-[100px] pointer-events-none group-hover:bg-cyan-500/15 transition-colors duration-700"></div>
        <div class="absolute -bottom-40 -left-40 w-[30rem] h-[30rem] bg-violet-600/10 rounded-full blur-[100px] pointer-events-none group-hover:bg-violet-600/15 transition-colors duration-700"></div>
        
        <div class="relative z-10 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-10">
            <div class="space-y-6">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-[10px] font-black text-cyan-400 uppercase tracking-[0.2em] bg-cyan-950/40 border border-cyan-800/60 rounded-full px-4 py-1.5 shadow-[0_0_15px_rgba(34,211,238,0.15)]">
                        {{ $tournament->game->localizedName() }}
                    </span>
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] border rounded-full px-4 py-1.5 {{ $statusColorClass }}">
                        {{ str_replace('_', ' ', $statusValue) }}
                    </span>
                    <span class="text-[10px] font-black uppercase tracking-[0.15em] rounded-full border border-zinc-800 bg-zinc-950/60 px-4 py-1.5 text-zinc-400">
                        Europe/Amsterdam
                    </span>
                    @if(($tournament->team_size ?? 1) > 1)
                        <span class="text-[10px] font-black text-violet-400 uppercase tracking-[0.2em] bg-violet-950/30 border border-violet-800/60 rounded-full px-4 py-1.5">
                            {{ $tournament->team_size }}v{{ $tournament->team_size }} Teams
                        </span>
                    @endif
                </div>
                
                <h1 class="{{ $isPlayerView ? 'text-2xl md:text-4xl' : 'text-4xl md:text-7xl' }} font-black font-orbitron tracking-tighter text-white uppercase filter drop-shadow-[0_0_15px_rgba(255,255,255,0.1)] leading-tight">
                    {{ $tournament->name }}
                </h1>
                
                <div class="flex flex-wrap items-center gap-y-4 gap-x-10">
                    <div class="space-y-1">
                        <span class="block text-[10px] font-bold text-zinc-600 uppercase tracking-widest">{{ $isPlayerView ? __("Current Winner's Prize") : 'Current Prize Pool' }}</span>
                        <div class="flex items-center space-x-2">
                            <i data-lucide="crown" class="w-5 h-5 text-fuchsia-500"></i>
                            <span class="text-2xl font-black text-fuchsia-500 font-orbitron leading-none">${{ number_format((float) ($isPlayerView ? ($prizeCalculation['distributions'][1] ?? 0) : $prizeCalculation['prize_pool']), 2) }}</span>
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
                            <span class="text-xl font-black text-white font-orbitron leading-none">{{ $tournament->registrations_count }} <span class="text-sm text-zinc-600">/</span> {{ $tournament->max_participants }}</span>
                        </div>
                    </div>
                </div>
                @if((int) $tournament->workflow_version === 2)
                    <p class="-mt-3 text-xs text-zinc-500">{{ __('Estimated prize until entries close. Final prize amounts may be adjusted if the tournament starts below capacity. Prizes are set and administered by PlayerSaloons.') }}</p>
                @else
                    <p class="-mt-3 text-xs text-zinc-500">Based on {{ $prizeCalculation['confirmed_count'] }} confirmed of {{ $tournament->max_participants }} players. At minimum attendance, prizes are 50% of the advertised amount and increase up to 100% as slots fill.</p>
                @endif
            </div>

            <!-- Main Action Area -->
            <div class="flex-shrink-0 min-w-[280px] space-y-3">
                @guest
                    <a href="/login" wire:navigate class="w-full flex items-center justify-center space-x-3 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-black py-5 px-8 rounded-2xl transition-all duration-300 shadow-[0_15px_30px_-10px_rgba(124,77,255,0.4)] text-xs uppercase tracking-[0.2em] transform hover:scale-[1.02] active:scale-[0.98]">
                        <i data-lucide="log-in" class="w-5 h-5"></i>
                        <span>Sign in to Register</span>
                    </a>
                    <p class="text-center text-[10px] text-zinc-600 font-medium">Guests can view but not join</p>
                @else
                    @if($currentMatch)
                        @php
                            $currentMatchStatus = $currentMatch->status->value ?? (string) $currentMatch->status;
                            $currentMatchLabel = match($currentMatchStatus) {
                                'waiting_for_confirmation' => $currentMatchHasSubmittedResult ? 'View Result Status' : 'Submit Result',
                                'disputed' => 'Review Match Dispute',
                                default => 'Submit Result',
                            };
                            $currentMatchStatusLabel = (int) $tournament->workflow_version === 2 && $currentMatchStatus === 'waiting_for_confirmation'
                                ? ($currentMatchHasSubmittedResult ? 'Waiting for opponent result' : __('Waiting for your result'))
                                : str_replace('_', ' ', $currentMatchStatus);
                        @endphp
                        @if((int) $tournament->workflow_version === 2)
                            <button type="button" wire:click="openMatch('{{ $currentMatch->uuid }}')" class="w-full flex items-center justify-center space-x-3 bg-gradient-to-r from-cyan-600 to-violet-600 hover:from-cyan-500 hover:to-violet-500 text-white font-black py-5 px-8 rounded-2xl transition-all duration-300 shadow-[0_15px_30px_-10px_rgba(34,211,238,0.4)] text-xs uppercase tracking-[0.16em] transform hover:scale-[1.02] active:scale-[0.98]">
                                <i data-lucide="clipboard-check" class="w-5 h-5"></i>
                                <span>{{ $currentMatchLabel }}</span>
                            </button>
                        @else
                            <a href="/matches/{{ $currentMatch->uuid }}" wire:navigate class="w-full flex items-center justify-center space-x-3 bg-gradient-to-r from-cyan-600 to-violet-600 hover:from-cyan-500 hover:to-violet-500 text-white font-black py-5 px-8 rounded-2xl transition-all duration-300 shadow-[0_15px_30px_-10px_rgba(34,211,238,0.4)] text-xs uppercase tracking-[0.16em] transform hover:scale-[1.02] active:scale-[0.98]">
                                <i data-lucide="swords" class="w-5 h-5"></i>
                                <span>Open Match Room &amp; Report Result</span>
                            </a>
                        @endif
                        <p class="text-center text-[10px] font-bold uppercase tracking-wider text-cyan-400">Round {{ $currentMatch->round?->round_number ?? '—' }} · {{ $currentMatchStatusLabel }}</p>
                    @elseif($isChampion)
                        <div role="status" class="w-full rounded-2xl border border-amber-400/40 bg-amber-400/10 px-6 py-5 text-center shadow-[0_0_20px_rgba(251,191,36,0.12)]">
                            <div class="flex items-center justify-center gap-2 text-amber-300">
                                <i data-lucide="trophy" class="h-5 w-5"></i>
                                <span class="text-xs font-black uppercase tracking-[0.2em]">Champion</span>
                            </div>
                        </div>
                    @elseif($isSecondPlace)
                        <div role="status" class="w-full rounded-2xl border border-zinc-300/30 bg-zinc-100/5 px-6 py-5 text-center">
                            <div class="flex items-center justify-center gap-2 text-zinc-200">
                                <i data-lucide="medal" class="h-5 w-5"></i>
                                <span class="text-xs font-black uppercase tracking-[0.2em]">Second Place</span>
                            </div>
                            <p class="mt-2 text-xs font-bold text-amber-300">Second prize: ${{ number_format((float) $secondPlacePrize, 2) }}</p>
                        </div>
                    @elseif($awaitingNextMatch)
                        <div role="status" class="w-full rounded-2xl border border-cyan-500/30 bg-cyan-500/10 px-6 py-5 text-center">
                            <div class="flex items-center justify-center gap-2 text-cyan-300">
                                <i data-lucide="circle-check" class="h-5 w-5"></i>
                                <span class="text-xs font-black uppercase tracking-[0.15em]">Winner · Waiting for next match</span>
                            </div>
                        </div>
                    @elseif($hasLost)
                        <div role="alert" class="w-full rounded-2xl border border-rose-500/40 bg-rose-500/10 px-6 py-5 text-center shadow-[0_0_20px_rgba(244,63,94,0.12)]">
                            <div class="flex items-center justify-center gap-2 text-rose-300">
                                <i data-lucide="shield-x" class="h-5 w-5"></i>
                                <span class="text-xs font-black uppercase tracking-[0.2em]">Defeated</span>
                            </div>
                        </div>
                    @elseif((int) $tournament->workflow_version === 2 && $isRegistered)
                        <div class="w-full rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-8 py-5 text-center text-xs font-black uppercase tracking-[0.2em] text-emerald-400"><span>Reservation Confirmed</span></div>
                        @if($canCancelRegistration)
                            <button type="button" @click="$dispatch('open-registration-cancellation')" class="w-full rounded-xl border border-red-800/50 bg-red-950/30 px-6 py-3 text-[10px] font-bold uppercase tracking-[0.2em] text-red-400 transition hover:border-red-600/60 hover:text-red-300">{{ __('Cancel Registration') }}</button>
                        @elseif($pendingCancellationRequest)
                            <div role="status" class="w-full rounded-xl border border-amber-700/40 bg-amber-950/20 px-6 py-3 text-center text-[10px] font-bold uppercase tracking-[0.16em] text-amber-300">Cancellation request pending</div>
                        @else
                            <div role="status" class="w-full rounded-xl border border-zinc-800 bg-zinc-950/50 px-6 py-3 text-center text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-500">{{ __('Cancellation is available only before the tournament starts.') }}</div>
                        @endif
                        @if($pendingCancellationRequest && in_array((int) Auth::id(), array_map('intval', $pendingCancellationRequest->eligible_voter_ids ?? []), true) && !$pendingCancellationRequest->votes->contains('voter_id', Auth::id()))
                            <div class="rounded-xl border border-amber-700/40 bg-amber-950/20 p-4 text-left"><p class="text-xs font-bold text-amber-200">{{ $pendingCancellationRequest->requester?->username }} requested to cancel.</p><div class="mt-3 grid grid-cols-2 gap-2"><button wire:click="voteOnCancellation({{ $pendingCancellationRequest->id }}, true)" class="rounded-lg bg-emerald-700 px-3 py-2 text-[10px] font-black uppercase text-white">Approve</button><button wire:click="voteOnCancellation({{ $pendingCancellationRequest->id }}, false)" class="rounded-lg border border-zinc-700 px-3 py-2 text-[10px] font-black uppercase text-zinc-300">Reject</button></div></div>
                        @endif
                    @elseif($tournament->status->value === 'REGISTRATION_OPEN')
                        @if($isRegistered)
                            <div class="w-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-2xl py-5 px-8 text-center text-xs font-black uppercase tracking-[0.2em] flex items-center justify-center space-x-3 shadow-[0_0_20px_rgba(16,185,129,0.1)]">
                                <i data-lucide="shield-check" class="w-5 h-5"></i>
                                <span>Reservation Confirmed</span>
                            </div>
                            @if($canCancelRegistration)
                                <button type="button" @click="$dispatch('open-registration-cancellation')" class="w-full flex items-center justify-center space-x-2 bg-red-950/30 border border-red-800/50 hover:border-red-600/60 text-red-400 hover:text-red-300 font-bold py-3 px-6 rounded-xl transition-all duration-300 text-[10px] uppercase tracking-[0.2em]">
                                    <i data-lucide="x-circle" class="w-4 h-4"></i>
                                    <span>Cancel Registration</span>
                                </button>
                            @endif
                            @if($pendingCancellationRequest && in_array((int) Auth::id(), array_map('intval', $pendingCancellationRequest->eligible_voter_ids ?? []), true) && !$pendingCancellationRequest->votes->contains('voter_id', Auth::id()))
                                <div class="rounded-xl border border-amber-700/40 bg-amber-950/20 p-4 text-left">
                                    <p class="text-xs font-bold text-amber-200">{{ $pendingCancellationRequest->requester?->username }} requested to cancel their entry.</p>
                                    <p class="mt-1 text-[10px] text-zinc-400">{{ $pendingCancellationRequest->required_approvals }} approval(s) are required. Votes are final.</p>
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <button wire:click="voteOnCancellation({{ $pendingCancellationRequest->id }}, true)" class="rounded-lg bg-emerald-700 px-3 py-2 text-[10px] font-black uppercase text-white">Approve</button>
                                        <button wire:click="voteOnCancellation({{ $pendingCancellationRequest->id }}, false)" class="rounded-lg border border-zinc-700 px-3 py-2 text-[10px] font-black uppercase text-zinc-300">Reject</button>
                                    </div>
                                </div>
                            @endif
                        @else
                            @if(Auth::user()->hasRole('PLAYER'))
                                @if(($tournament->team_size ?? 1) > 1)
                                    <button type="button" @click="openJoinModal()" wire:loading.attr="disabled" wire:target="prepareRegistrationPrompt" class="w-full flex items-center justify-center space-x-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black py-5 px-8 rounded-2xl transition-all duration-300 shadow-[0_15px_30px_-10px_rgba(16,185,129,0.4)] text-xs uppercase tracking-[0.2em] transform hover:scale-[1.02] active:scale-[0.98]">
                                        <i data-lucide="{{ $userTournamentTeam || $userSquad ? 'users' : 'user-plus' }}" class="w-5 h-5"></i>
                                        <span>{{ $userTournamentTeam && (int) $userTournamentTeam->leader_user_id === (int) Auth::id() ? 'Register Team' : ($userSquad ? 'Create Team From Squad' : ($isSearchingForTeam ? 'Still Finding a Team' : 'Find a Team')) }}</span>
                                    </button>
                                    @if($userTournamentTeam)
                                        <p class="text-center text-[10px] text-zinc-500 font-medium">{{ $userTournamentTeam->name }} · {{ $userTournamentTeam->members->count() }}/{{ $tournament->team_size }} players · {{ (int) $userTournamentTeam->leader_user_id === (int) Auth::id() ? 'You are Team Leader' : 'Waiting for Team Leader' }}</p>
                                        @if((int) $userTournamentTeam->leader_user_id === (int) Auth::id())
                                            <div class="flex flex-wrap justify-center gap-1.5">@foreach($userTournamentTeam->members->where('user_id', '!=', Auth::id()) as $member)<button wire:click="transferTournamentTeamLeadership({{ $member->user_id }})" wire:confirm="Make {{ $member->user?->username }} the Team Leader?" class="rounded border border-zinc-800 bg-zinc-950 px-2 py-1 text-[9px] text-zinc-500 hover:text-cyan-300">Make {{ $member->user?->username }} Leader</button>@endforeach</div>
                                        @endif
                                    @elseif(!$userSquad)
                                        <p class="text-center text-[10px] text-zinc-500 font-medium">Players are grouped for this tournament only. No charge until the team is complete.</p>
                                    @endif
                                @else
                                    <button type="button" @click="openJoinModal()" wire:loading.attr="disabled" wire:target="prepareRegistrationPrompt" class="w-full flex items-center justify-center space-x-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black py-5 px-8 rounded-2xl transition-all duration-300 shadow-[0_15px_30px_-10px_rgba(16,185,129,0.4)] text-xs uppercase tracking-[0.2em] transform hover:scale-[1.02] active:scale-[0.98]">
                                        <i data-lucide="plus-circle" class="w-5 h-5"></i>
                                        <span>{{ $joinLabel }}</span>
                                    </button>
                                @endif
                            @else
                                <div class="w-full bg-zinc-950/60 border border-zinc-800 text-zinc-500 rounded-2xl py-5 px-8 text-center text-xs font-black uppercase tracking-[0.2em]">
                                    Only players can join tournaments
                                </div>
                            @endif
                        @endif
                    @elseif(in_array($tournament->status->value, ['REGISTRATION_CLOSED', 'CHECKIN_OPEN', 'CHECKIN_CLOSED']))
                        @if($isRegistered)
                            <div class="w-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 rounded-2xl py-5 px-8 text-center text-xs font-black uppercase tracking-[0.2em] flex items-center justify-center space-x-3 shadow-[0_0_20px_rgba(34,211,238,0.1)]">
                                <i data-lucide="user-check" class="w-5 h-5"></i>
                                <span>Entry Locked · Preparing Matches</span>
                            </div>
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

    <!-- ⏱️ Countdown Timer Section -->
    @php
        $now = now();
        $timerLabel = null;
        $timerTarget = null;

        $statusVal = $tournament->status->value ?? $tournament->status;
        if ((int) $tournament->workflow_version === 2 && $statusVal === 'REGISTRATION_OPEN' && $tournament->start_at) {
            $timerLabel = 'First matches begin in';
            $timerTarget = $tournament->start_at;
        } elseif ($statusVal === 'REGISTRATION_OPEN' && $tournament->registration_close_at) {
            $timerLabel = $tournament->extra_registration_started_at ? 'Extra Registration closes in' : 'Registration closes in';
            $timerTarget = $tournament->registration_close_at;
        } elseif (in_array($statusVal, ['REGISTRATION_CLOSED', 'CHECKIN_OPEN', 'CHECKIN_CLOSED']) && $tournament->start_at) {
            $timerLabel = 'First matches begin in';
            $timerTarget = $tournament->start_at;
        } elseif ($statusVal === 'PUBLISHED' && $tournament->registration_open_at) {
            $timerLabel = 'Registration opens in';
            $timerTarget = $tournament->registration_open_at;
        }
    @endphp

    @if($timerLabel && $timerTarget && $timerTarget > $now)
        <template x-teleport="#tournament-top-timer">
        <div class="bg-amber-950/35 backdrop-blur-xl border border-amber-500/50 rounded-2xl px-5 py-7 sm:p-7 flex flex-col sm:flex-row items-center gap-5 sm:gap-6 shadow-[0_12px_35px_-18px_rgba(245,158,11,0.45)]"
             x-data="countdownTimer('{{ $timerTarget->toIso8601String() }}')"
             x-init="start()">
            <div class="flex items-center space-x-3 text-amber-300">
                <i data-lucide="timer" class="w-7 h-7"></i>
                <span class="text-sm font-black uppercase tracking-widest text-amber-100 sm:text-base">{{ $timerLabel }}</span>
            </div>
            <div class="flex items-center gap-2 sm:gap-3 sm:ml-auto">
                <template x-if="!expired">
                    <div class="flex items-center gap-1.5 sm:gap-2">
                        <div class="text-center">
                            <div class="bg-amber-950/75 border border-amber-500/60 rounded-xl px-3 py-3 min-w-[3.5rem] sm:min-w-[4rem] text-center shadow-inner">
                                <span class="text-2xl sm:text-3xl font-black font-orbitron text-amber-200" x-text="String(days).padStart(2,'0')"></span>
                            </div>
                            <span class="text-[10px] text-amber-300 uppercase tracking-widest font-bold mt-1.5 block">Days</span>
                        </div>
                        <span class="text-amber-400 font-black text-xl sm:text-2xl">:</span>
                        <div class="text-center">
                            <div class="bg-amber-950/75 border border-amber-500/60 rounded-xl px-3 py-3 min-w-[3.5rem] sm:min-w-[4rem] text-center shadow-inner">
                                <span class="text-2xl sm:text-3xl font-black font-orbitron text-amber-200" x-text="String(hours).padStart(2,'0')"></span>
                            </div>
                            <span class="text-[10px] text-amber-300 uppercase tracking-widest font-bold mt-1.5 block">Hrs</span>
                        </div>
                        <span class="text-amber-400 font-black text-xl sm:text-2xl">:</span>
                        <div class="text-center">
                            <div class="bg-amber-950/75 border border-amber-500/60 rounded-xl px-3 py-3 min-w-[3.5rem] sm:min-w-[4rem] text-center shadow-inner">
                                <span class="text-2xl sm:text-3xl font-black font-orbitron text-amber-200" x-text="String(minutes).padStart(2,'0')"></span>
                            </div>
                            <span class="text-[10px] text-amber-300 uppercase tracking-widest font-bold mt-1.5 block">Min</span>
                        </div>
                        <span class="text-amber-400 font-black text-xl sm:text-2xl">:</span>
                        <div class="text-center">
                            <div class="bg-amber-950/75 border border-amber-500/60 rounded-xl px-3 py-3 min-w-[3.5rem] sm:min-w-[4rem] text-center shadow-inner">
                                <span class="text-2xl sm:text-3xl font-black font-orbitron text-amber-200" x-text="String(seconds).padStart(2,'0')"></span>
                            </div>
                            <span class="text-[10px] text-amber-300 uppercase tracking-widest font-bold mt-1.5 block">Sec</span>
                        </div>
                    </div>
                </template>
                <template x-if="expired">
                    <span class="text-xs font-black text-amber-200 uppercase tracking-widest">Time's up — refresh to update status</span>
                </template>
            </div>
        </div>
        </template>
    @endif

    <!-- Navigation Tabs -->
    <div class="flex overflow-x-auto no-scrollbar items-center gap-1.5 bg-zinc-900/40 backdrop-blur-md border border-zinc-800/60 p-1.5 rounded-2xl w-full">
        @php
            $tabs = [
                ['id' => 'matches', 'label' => 'Fixtures & Bracket', 'icon' => 'git-branch'],
            ];
            if ((int) $tournament->workflow_version === 2 && $isRegistered) {
                $tabs[] = ['id' => 'submit-results', 'label' => 'Submit Result', 'icon' => 'clipboard-check'];
            }
            $tabs[] = ['id' => 'participants', 'label' => 'Players (' . $tournament->registrations_count . ')', 'icon' => 'users'];
            // Add team lobby tab for team tournaments with open registration
            $isTeamTournament = ($tournament->team_size ?? 1) > 1;
            if ($isTeamTournament) {
                $tabs[] = ['id' => 'team-lobby', 'label' => 'Team Lobby', 'icon' => 'users-round'];
            }
            $streamItems = $streamService->streamsForTournament($tournament);
            if (count($streamItems) > 0) {
                $tabs[] = ['id' => 'streams', 'label' => 'Streams', 'icon' => 'radio'];
            }
            $tabs[] = ['id' => 'overview', 'label' => 'Overview', 'icon' => 'layout-dashboard'];
            $tabs[] = ['id' => 'activity', 'label' => 'Activity', 'icon' => 'activity'];
        @endphp

        @foreach($tabs as $tab)
            @php $isRestrictedTab = in_array($tab['id'], ['submit-results', 'participants', 'team-lobby', 'matches', 'activity'], true); @endphp
            <button
                @if(!$isRestrictedTab || $canViewRestricted)
                    @click="selectTab('{{ $tab['id'] }}')"
                    :class="activeTab === '{{ $tab['id'] }}' ? 'border-violet-400 bg-violet-600 text-white shadow-[0_0_22px_rgba(124,58,237,.35)]' : 'border-transparent text-zinc-500 hover:border-zinc-700 hover:bg-zinc-900 hover:text-zinc-200'"
                @else
                    disabled title="Only competition participants can view this section"
                @endif
                class="flex items-center space-x-1.5 border px-4 md:px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 whitespace-nowrap {{ $isRestrictedTab && !$canViewRestricted ? 'opacity-40 cursor-not-allowed text-zinc-600' : '' }}">
                <i data-lucide="{{ $tab['icon'] }}" class="w-3.5 h-3.5"></i>
                <span>{{ $tab['label'] }}</span>
            </button>
        @endforeach
    </div>

    <!-- Tabs Content -->
    <div class="relative min-h-[500px]">

        <div x-show="loadingSection !== null" x-cloak class="absolute inset-x-0 top-16 z-20 flex justify-center">
            <div class="rounded-xl border border-zinc-800 bg-zinc-950/90 px-5 py-3 text-[10px] font-black uppercase tracking-widest text-zinc-400 shadow-2xl">
                Loading tournament data…
            </div>
        </div>

        <!-- ===== OVERVIEW TAB ===== -->
        <div data-tournament-content="overview" x-show="activeTab === 'overview'" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            <div class="lg:col-span-2 space-y-10">

                <!-- Mission / Description -->
                <section class="space-y-6">
                    <h2 class="text-2xl font-black font-orbitron tracking-widest text-white flex items-center space-x-3">
                        <span class="w-1.5 h-8 bg-cyan-500 rounded-full"></span>
                        <span>ABOUT THIS TOURNAMENT</span>
                    </h2>
                    <div class="decorated-card tournament-overview-card bg-zinc-900/40 backdrop-blur-md border border-zinc-800/60 rounded-[2rem] p-8 space-y-6">
                        <i data-lucide="trophy" aria-hidden="true" class="ui-card-watermark"></i>
                        @if($tournament->description)
                            <x-ui.collapsible-rich-text :content="$tournament->description" :threshold="500" :preview-height="220" />
                        @else
                            <p class="text-zinc-500 italic">No description provided.</p>
                        @endif

                        <!-- Key stats grid -->
                        <div class="grid grid-cols-1 gap-3 pt-4 border-t border-zinc-800/60 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                            @php
                                $format = $tournament->template?->format ?? 'Single Elimination';
                            @endphp
                            <div class="min-w-0 bg-zinc-950/60 border border-zinc-800/60 rounded-2xl p-3 space-y-2 sm:p-4">
                                <div class="flex items-center space-x-2 text-cyan-400">
                                    <i data-lucide="target" class="w-4 h-4"></i>
                                    <span class="text-[10px] font-black uppercase tracking-widest">Format</span>
                                </div>
                                <span class="block break-words text-sm font-bold text-white uppercase font-orbitron sm:text-base">{{ $format }}</span>
                            </div>

                            <div class="min-w-0 bg-zinc-950/60 border border-zinc-800/60 rounded-2xl p-3 space-y-2 sm:p-4">
                                <div class="flex items-center space-x-2 text-fuchsia-500">
                                    <i data-lucide="award" class="w-4 h-4"></i>
                                    <span class="text-[10px] font-black uppercase tracking-widest">1st Prize</span>
                                </div>
                                <span class="block break-words text-sm font-bold text-white uppercase font-orbitron sm:text-base">
                                    @php
                                        $firstPrize = $displayPrizeCalculation['first'] ?? $prizeCalculation['distributions'][1] ?? '0.00';
                                    @endphp
                                    ${{ number_format((float) $firstPrize, 2) }}
                                    @if($isV2Registration)
                                        <span class="text-[9px] text-zinc-600 tracking-normal font-sans">(full est.)</span>
                                    @endif
                                </span>
                            </div>

                            @php
                                $secondPrize = $displayPrizeCalculation['second'] ?? $prizeCalculation['distributions'][2] ?? '0.00';
                            @endphp
                            @if((float) $secondPrize > 0)
                                <div class="min-w-0 bg-zinc-950/60 border border-zinc-800/60 rounded-2xl p-3 space-y-2 sm:p-4">
                                    <div class="flex items-center space-x-2 text-zinc-400">
                                        <i data-lucide="medal" class="w-4 h-4"></i>
                                        <span class="text-[10px] font-black uppercase tracking-widest">2nd Prize</span>
                                    </div>
                                    <span class="block break-words text-sm font-bold text-white uppercase font-orbitron sm:text-base">${{ number_format((float) $secondPrize, 2) }}</span>
                                </div>
                            @endif

                            @if(isset($prizeCalculation['distributions'][3]))
                                <div class="min-w-0 bg-zinc-950/60 border border-zinc-800/60 rounded-2xl p-3 space-y-2 sm:p-4">
                                    <div class="flex items-center space-x-2 text-orange-400">
                                        <i data-lucide="trophy" class="w-4 h-4"></i>
                                        <span class="text-[10px] font-black uppercase tracking-widest">3rd Prize</span>
                                    </div>
                                    <span class="block break-words text-sm font-bold text-white uppercase font-orbitron sm:text-base">${{ number_format((float)$prizeCalculation['distributions'][3], 2) }}</span>
                                </div>
                            @endif

                            @if($tournament->team_size > 1)
                                <div class="min-w-0 bg-zinc-950/60 border border-zinc-800/60 rounded-2xl p-3 space-y-2 sm:p-4">
                                    <div class="flex items-center space-x-2 text-violet-400">
                                        <i data-lucide="users" class="w-4 h-4"></i>
                                        <span class="text-[10px] font-black uppercase tracking-widest">Team Size</span>
                                    </div>
                                    <span class="block break-words text-sm font-bold text-white uppercase font-orbitron sm:text-base">{{ $tournament->team_size }} Players</span>
                                </div>
                            @endif

                            @if($tournament->platform)
                                <div class="min-w-0 bg-zinc-950/60 border border-zinc-800/60 rounded-2xl p-3 space-y-2 sm:p-4">
                                    <div class="flex items-center space-x-2 text-emerald-400">
                                        <i data-lucide="monitor" class="w-4 h-4"></i>
                                        <span class="text-[10px] font-black uppercase tracking-widest">Platform</span>
                                    </div>
                                    <span class="block break-words text-sm font-bold text-white uppercase font-orbitron sm:text-base">{{ $tournament->platform_names ?: '—' }}</span>
                                </div>
                            @endif

                            @if($tournament->winning_points)
                                <div class="min-w-0 bg-zinc-950/60 border border-zinc-800/60 rounded-2xl p-3 space-y-2 sm:p-4">
                                    <div class="flex items-center space-x-2 text-amber-400">
                                        <i data-lucide="star" class="w-4 h-4"></i>
                                        <span class="text-[10px] font-black uppercase tracking-widest">Win Points</span>
                                    </div>
                                    <span class="block break-words text-sm font-bold text-white uppercase font-orbitron sm:text-base">+{{ $tournament->winning_points }} pts</span>
                                </div>
                            @endif

                            @if($tournament->waiting_time)
                                <div class="min-w-0 bg-zinc-950/60 border border-zinc-800/60 rounded-2xl p-3 space-y-2 sm:p-4">
                                    <div class="flex items-center space-x-2 text-rose-400">
                                        <i data-lucide="clock" class="w-4 h-4"></i>
                                        <span class="text-[10px] font-black uppercase tracking-widest">Match Timeout</span>
                                    </div>
                                    <span class="block break-words text-sm font-bold text-white uppercase font-orbitron sm:text-base">{{ $tournament->waiting_time }} min</span>
                                </div>
                            @endif

                            @if($tournament->frequency && $tournament->frequency !== 'one-time')
                                <div class="min-w-0 bg-zinc-950/60 border border-zinc-800/60 rounded-2xl p-3 space-y-2 sm:p-4">
                                    <div class="flex items-center space-x-2 text-indigo-400">
                                        <i data-lucide="repeat" class="w-4 h-4"></i>
                                        <span class="text-[10px] font-black uppercase tracking-widest">Frequency</span>
                                    </div>
                                    <span class="block break-words text-sm font-bold text-white uppercase font-orbitron sm:text-base">{{ ucfirst($tournament->frequency) }}</span>
                                </div>
                            @endif
                        </div>

                        @if($isV2Registration)
                            <div class="mt-4 flex items-start gap-3 rounded-2xl border border-cyan-800/50 bg-cyan-950/20 p-4 text-xs leading-relaxed text-cyan-100/75">
                                <i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0 text-cyan-400"></i>
                                <p>
                                    This is the estimated payout if all {{ $tournament->max_participants }} teams join. The final prize is based on the actual entry pool when the tournament starts. If the tournament starts below capacity, the bracket uses automatic BYEs and the final prize is adjusted based on confirmed entries.
                                </p>
                            </div>
                        @endif
                    </div>
                </section>

                <!-- Rules Section -->
                <section class="space-y-6">
                    <h2 class="text-2xl font-black font-orbitron tracking-widest text-white flex items-center space-x-3">
                        <span class="w-1.5 h-8 bg-fuchsia-500 rounded-full"></span>
                        <span>TOURNAMENT RULES</span>
                    </h2>
                    <div class="decorated-card tournament-overview-card bg-zinc-900/40 backdrop-blur-md border border-zinc-800/60 rounded-[2rem] p-8">
                        <i data-lucide="scroll-text" aria-hidden="true" class="ui-card-watermark"></i>
                        @if($tournament->rules)
                            <x-ui.collapsible-rich-text :content="$tournament->rules" :preview-height="420" :scrollable="true" scroll-label="Tournament rules" />
                        @else
                            <!-- Default rules when none specified -->
                            <ul class="space-y-4">
                                @foreach([
                                    'Tournament entries lock when registration closes.',
                                    'Standard competitive server parameters only.',
                                    'Instant result reporting required post-engagement.',
                                    'Disputes necessitate high-definition screenshot evidence.',
                                    'Zero tolerance for unsportsmanlike conduct.',
                                ] as $rule)
                                    <li class="flex items-start space-x-4 group">
                                        <div class="w-6 h-6 rounded-full bg-zinc-950 border border-zinc-800 flex items-center justify-center mt-0.5 group-hover:border-fuchsia-500 transition-colors duration-300">
                                            <i data-lucide="check" class="w-3.5 h-3.5 text-fuchsia-500 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </div>
                                        <span class="text-zinc-400 group-hover:text-zinc-200 transition-colors font-medium">{{ $rule }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="text-[10px] text-zinc-700 mt-4 font-medium italic">* No specific rules set by organizer — standard platform rules apply.</p>
                        @endif
                    </div>
                </section>
            </div>

            <!-- Timeline Sidebar -->
            <div class="space-y-10">
                <!-- Chronology -->
                <section class="space-y-6">
                    <h2 class="text-xl font-black font-orbitron tracking-widest text-white">CHRONOLOGY</h2>
                    <div class="decorated-card tournament-overview-card relative bg-zinc-900/40 backdrop-blur-md border border-zinc-800/60 rounded-[2rem] p-8 space-y-8 overflow-hidden">
                        <i data-lucide="clock-3" aria-hidden="true" class="ui-card-watermark"></i>
                        <div class="absolute top-12 bottom-12 left-11 w-px bg-gradient-to-b from-cyan-500 via-violet-500 to-fuchsia-500 opacity-20"></div>
                        
                        @foreach([
                            ['icon' => 'calendar', 'color' => 'text-cyan-400 border-cyan-800/50', 'label' => 'Registration Opens', 'time' => $tournament->registration_open_at],
                            ['icon' => 'calendar-x', 'color' => 'text-rose-400 border-rose-800/50', 'label' => 'Registration Ends', 'time' => (int) $tournament->workflow_version === 2 ? $tournament->start_at : $tournament->registration_close_at],
                            ['icon' => 'shield-check', 'color' => 'text-fuchsia-400 border-fuchsia-800/50', 'label' => 'Entries Lock', 'time' => (int) $tournament->workflow_version === 2 ? $tournament->start_at : $tournament->registration_close_at],
                            ['icon' => 'zap', 'color' => 'text-emerald-400 border-emerald-800/50', 'label' => 'First Matches', 'time' => $tournament->start_at],
                            ['icon' => 'flag', 'color' => 'text-amber-400 border-amber-800/50', 'label' => 'Estimated End', 'time' => $tournament->end_at],
                        ] as $item)
                            @php
                                $isPast = $item['time'] && \Illuminate\Support\Carbon::parse($item['time'])->isPast();
                            @endphp
                            <div class="flex items-start space-x-5 relative z-10 group">
                                <div class="w-7 h-7 rounded-full {{ $isPast ? 'bg-zinc-900 border-zinc-700' : 'bg-zinc-950 border-2 border-zinc-800' }} flex items-center justify-center shrink-0 group-hover:border-zinc-400 transition-colors {{ $isPast ? 'opacity-50' : '' }}">
                                    <i data-lucide="{{ $item['icon'] }}" class="w-3.5 h-3.5 {{ $item['color'] }} {{ $isPast ? 'opacity-50' : '' }}" style="border-color: unset;"></i>
                                </div>
                                <div class="space-y-1 min-w-0">
                                    <span class="block text-[10px] font-black {{ $isPast ? 'text-zinc-700' : 'text-zinc-500' }} uppercase tracking-widest">{{ $item['label'] }}</span>
                                    <span class="text-sm font-bold {{ $isPast ? 'text-zinc-600 line-through' : 'text-zinc-300' }}">
                                        {{ $item['time'] ? \Illuminate\Support\Carbon::parse($item['time'])->setTimezone('Europe/Amsterdam')->format('M d, Y') : 'TBD' }}
                                        <span class="text-xs text-zinc-500 ml-1 opacity-60">{{ $item['time'] ? \Illuminate\Support\Carbon::parse($item['time'])->setTimezone('Europe/Amsterdam')->format('h:i A T') : '' }}</span>
                                    </span>
                                    @if($item['time'] && !$isPast)
                                        <span class="text-[10px] text-zinc-600 font-medium">{{ \Illuminate\Support\Carbon::parse($item['time'])->diffForHumans() }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <!-- Quick Info -->
                <section class="space-y-4">
                    <h2 class="text-xl font-black font-orbitron tracking-widest text-white">QUICK INFO</h2>
                    <div class="decorated-card tournament-overview-card bg-zinc-900/40 backdrop-blur-md border border-zinc-800/60 rounded-[2rem] p-6 space-y-4">
                        <i data-lucide="info" aria-hidden="true" class="ui-card-watermark"></i>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-600 font-medium">Minimum Participants</span>
                            <span class="text-zinc-300 font-bold">{{ $tournament->min_participants }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-600 font-medium">{{ (int) $tournament->workflow_version === 2 ? 'Maximum Teams' : 'Max Players' }}</span>
                                <span class="text-zinc-300 font-bold">{{ $tournament->max_participants }} {{ (int) $tournament->workflow_version === 2 ? 'teams' : 'players' }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-600 font-medium">Registered</span>
                            <span class="text-emerald-400 font-bold">{{ $tournament->registrations_count }}</span>
                        </div>
                        @if($tournament->entry_fee > 0)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-zinc-600 font-medium">Entry Fee</span>
                                <span class="text-cyan-400 font-bold">${{ number_format((float)$tournament->entry_fee, 2) }}</span>
                            </div>
                        @endif
                        @if($tournament->waiting_result_time)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-zinc-600 font-medium">Result Timeout</span>
                                <span class="text-zinc-300 font-bold">{{ $tournament->waiting_result_time }} min</span>
                            </div>
                        @endif
                        @if((int) $tournament->workflow_version === 2)
                            <div class="flex items-center justify-between text-sm"><span class="text-zinc-600 font-medium">Round Duration</span><span class="text-zinc-300 font-bold">{{ $tournament->round_duration_seconds ? \Carbon\CarbonInterval::seconds($tournament->round_duration_seconds)->cascade()->forHumans(['short' => true]) : 'No timer' }}</span></div>
                            <div class="flex items-center justify-between text-sm"><span class="text-zinc-600 font-medium">Participation XP</span><span class="text-zinc-300 font-bold">4 XP after elimination</span></div>
                        @endif
                    </div>
                </section>
            </div>
        </div>

        @if((int) $tournament->workflow_version === 2 && $isRegistered)
            <!-- ===== SUBMIT RESULT TAB ===== -->
            <div data-tournament-content="submit-results" x-show="activeTab === 'submit-results'" x-cloak style="display: none;" class="space-y-6">
                @if($displayMatch)
                    <livewire:match.match-detail
                        :uuid="$displayMatch->uuid"
                        :embedded="true"
                        :key="'tournament-match-'.$displayMatch->uuid"
                    />
                @else
                    <div class="rounded-[2rem] border border-zinc-800 bg-zinc-900/40 p-10 text-center">
                        <i data-lucide="hourglass" class="mx-auto h-10 w-10 text-zinc-700"></i>
                        <h2 class="mt-4 font-orbitron text-lg font-black uppercase tracking-widest text-white">Waiting for your match</h2>
                        <p class="mt-2 text-sm text-zinc-500">Your opponent and result form will appear here as soon as your fixture is ready.</p>
                    </div>
                @endif
            </div>
        @endif

        @if($canViewRestricted)
        <!-- ===== PLAYERS TAB ===== -->
        <div data-tournament-content="participants" x-show="activeTab === 'participants'" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
            @if($tournament->registrations->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($tournament->registrations as $reg)
                        @php
                            $memberUsername = $reg->user?->username ?? '';
                            $memberName = $reg->team?->name ?: ($reg->user?->profile?->display_name ?: $memberUsername ?: 'Player');
                            $memberAvatar = $reg->team?->logo_url ?: $reg->user?->profile?->avatar_url;
                            $memberInitials = strtoupper(substr($memberUsername ?: $memberName, 0, 2));
                        @endphp
                        <div class="group bg-zinc-900/40 backdrop-blur-md border border-zinc-800/60 rounded-2xl p-5 flex items-center space-x-4 hover:border-violet-500/50 hover:bg-violet-950/10 transition-all duration-300">
                            <div class="w-12 h-12 bg-zinc-950 rounded-xl border border-zinc-800 flex items-center justify-center text-zinc-300 font-black text-xs overflow-hidden shrink-0">
                                @if($memberAvatar)
                                    <img src="{{ $memberAvatar }}" alt="{{ $memberName }}" class="w-full h-full object-cover" loading="lazy">
                                @else
                                    {{ $memberInitials }}
                                @endif
                            </div>
                            <div class="truncate">
                                <span class="block text-sm font-black text-white truncate font-orbitron tracking-tight">
                                    {{ $memberName }}
                                </span>
                                <span class="block text-[10px] text-zinc-600 font-black uppercase tracking-widest truncate">
                                    @if($reg->team)
                                        {{ $tournament->team_size }} players • Captain @ {{ $reg->user->username }}
                                    @else
                                        #{{ $reg->user->id }} • @ {{ $reg->user->username }}
                                        @if(($tournament->team_size ?? 1) > 1)
                                            <span class="text-amber-500/70 ml-1">· Seeking Team</span>
                                        @endif
                                    @endif
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

        <!-- ===== TEAM LOBBY TAB ===== -->
        @if($isTeamTournament)
        <div data-tournament-content="team-lobby" x-show="activeTab === 'team-lobby'" x-cloak style="display:none;" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-8">
            <div class="bg-zinc-900/40 backdrop-blur-md border border-zinc-800/60 rounded-[2rem] p-8">
                <div class="flex items-center space-x-3 mb-6">
                    <span class="w-1.5 h-8 bg-amber-500 rounded-full"></span>
                    <h2 class="text-2xl font-black font-orbitron tracking-widest text-white">TEAM LOBBY</h2>
                    <span class="text-[10px] font-black text-amber-400 bg-amber-950/30 border border-amber-800/50 rounded-full px-3 py-1 uppercase tracking-widest">{{ $tournament->team_size }}v{{ $tournament->team_size }}</span>
                </div>
                <p class="text-zinc-400 text-sm font-medium mb-8 leading-relaxed">
                    Players waiting to be placed on a random team. A team is formed when enough players ({{ $tournament->team_size }}) are grouped together. Join the lobby and the system will match you with other solo players.
                </p>

                @php
                    $soloPlayers = $tournament->registrations->filter(fn($r) => !$r->team_id);
                    $formedTeams = $tournament->registrations->filter(fn($r) => $r->team_id);
                @endphp

                @if($soloPlayers->count() > 0)
                    <h3 class="text-sm font-black uppercase tracking-widest text-zinc-500 mb-4">Solo Players Waiting ({{ $soloPlayers->count() }})</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-8">
                        @foreach($soloPlayers as $reg)
                            <div class="bg-zinc-950/60 border border-amber-800/30 rounded-2xl p-4 flex items-center space-x-3 hover:border-amber-600/50 transition-colors">
                                <div class="w-10 h-10 bg-amber-950/30 rounded-xl border border-amber-800/30 flex items-center justify-center text-amber-200 font-black text-[10px] overflow-hidden shrink-0">
                                    @if($reg->user?->profile?->avatar_url)
                                        <img src="{{ $reg->user->profile->avatar_url }}" alt="{{ $reg->user->username }}" class="w-full h-full object-cover" loading="lazy">
                                    @else
                                        {{ strtoupper(substr($reg->user?->username ?? '??', 0, 2)) }}
                                    @endif
                                </div>
                                <div class="truncate">
                                    <span class="block text-sm font-bold text-white truncate">{{ $reg->user->profile?->display_name ?: $reg->user->username }}</span>
                                    <span class="block text-[10px] text-amber-600 font-bold uppercase tracking-widest">Waiting for team</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12 text-zinc-600">
                        <i data-lucide="users-round" class="w-12 h-12 mx-auto mb-4 opacity-30"></i>
                        <p class="text-sm font-medium">No solo players in lobby yet.</p>
                    </div>
                @endif

                @if($formedTeams->count() > 0)
                    <h3 class="text-sm font-black uppercase tracking-widest text-zinc-500 mb-4 border-t border-zinc-800/60 pt-6">Registered Teams ({{ $formedTeams->count() }})</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($formedTeams as $reg)
                            <div class="bg-zinc-950/60 border border-violet-800/30 rounded-2xl p-4 flex items-center space-x-3 hover:border-violet-600/50 transition-colors">
                                <div class="w-10 h-10 bg-violet-950/30 rounded-xl border border-violet-800/30 flex items-center justify-center text-violet-200 font-black text-[10px] overflow-hidden shrink-0">
                                    @if($reg->team?->logo_url)
                                        <img src="{{ $reg->team->logo_url }}" alt="{{ $reg->team->name }}" class="w-full h-full object-cover" loading="lazy">
                                    @elseif($reg->user?->profile?->avatar_url)
                                        <img src="{{ $reg->user->profile->avatar_url }}" alt="{{ $reg->user->username }}" class="w-full h-full object-cover" loading="lazy">
                                    @else
                                        {{ strtoupper(substr($reg->user?->username ?? $reg->team?->name ?? '??', 0, 2)) }}
                                    @endif
                                </div>
                                <div class="truncate">
                                    <span class="block text-sm font-bold text-white truncate">{{ $reg->team->name }}</span>
                                    <span class="block text-[10px] text-violet-500 font-bold uppercase tracking-widest">Captain: {{ $reg->user->username }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        @endif

        <!-- ===== FIXTURES & BRACKET TAB ===== -->
        <div x-show="activeTab === 'matches'" x-cloak style="display:none;" class="rounded-2xl border border-zinc-800/70 bg-zinc-900/30 p-2">
            <div class="grid grid-cols-2 gap-2">
                <button type="button" @click="selectBracketView('bracket')" :class="bracketView === 'bracket' ? 'bg-violet-600 text-white shadow-lg' : 'text-zinc-500 hover:bg-zinc-900 hover:text-white'" class="rounded-xl px-4 py-3 text-[10px] font-black uppercase tracking-widest transition">
                    Bracket
                </button>
                <button type="button" @click="selectBracketView('fixtures')" :class="bracketView === 'fixtures' ? 'bg-indigo-600 text-white shadow-lg' : 'text-zinc-500 hover:bg-zinc-900 hover:text-white'" class="rounded-xl px-4 py-3 text-[10px] font-black uppercase tracking-widest transition">
                    Fixtures
                </button>
            </div>
        </div>

        <div data-tournament-content="matches-fixtures" x-show="activeTab === 'matches' && bracketView === 'fixtures'" x-cloak style="display:none;" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-black font-orbitron tracking-widest text-white flex items-center space-x-3">
                    <span class="w-1.5 h-8 bg-indigo-500 rounded-full"></span>
                    <span>MATCH FIXTURES</span>
                </h2>
                <span class="text-[10px] text-zinc-500 font-black uppercase tracking-widest">{{ $allMatches->count() }} matches total</span>
            </div>

            @if($allMatches->count() > 0)
                @php
                    $matchesByRound = $allMatches->groupBy(fn($m) => $m->round?->round_number ?? 0);
                    $totalRounds = $rounds->count();
                    $roundNames = [];
                    for ($i = 1; $i <= $totalRounds; $i++) {
                        if ($i === $totalRounds) $roundNames[$i] = 'Grand Final';
                        elseif ($i === $totalRounds - 1) $roundNames[$i] = 'Semi Final';
                        elseif ($i === $totalRounds - 2) $roundNames[$i] = 'Quarter Final';
                        else $roundNames[$i] = 'Round ' . $i;
                    }
                @endphp

                <div class="space-y-8">
                    @foreach($matchesByRound->sortKeys() as $roundNum => $matches)
                        <div class="space-y-3">
                            <div class="flex items-center space-x-3">
                                <h3 class="text-xs font-black text-zinc-400 uppercase tracking-widest">{{ $roundNames[$roundNum] ?? 'Round ' . $roundNum }}</h3>
                                <div class="h-px flex-1 bg-zinc-800/60"></div>
                                <span class="text-[10px] text-zinc-600 font-bold">{{ $matches->count() }} {{ Str::plural('match', $matches->count()) }}</span>
                            </div>

                            <div class="space-y-3">
                                @foreach($matches as $match)
                                    @php
                                        $mStatus = $match->status->value ?? $match->status;
                                        $playerA = $match->playerARegistration?->user;
                                        $playerB = $match->playerBRegistration?->user;
                                        $playerAAvatar = $playerA?->profile?->avatar_url;
                                        $playerBAvatar = $playerB?->profile?->avatar_url;
                                        $winnerA = $match->winner_registration_id && $match->winner_registration_id === $match->player_a_registration_id;
                                        $winnerB = $match->winner_registration_id && $match->winner_registration_id === $match->player_b_registration_id;
                                        $isOngoing = in_array($mStatus, ['in_progress', 'ready', 'result_submitted', 'waiting_for_confirmation', 'disputed']);
                                        $isCompleted = in_array($mStatus, ['completed', 'forfeited']);
                                        $viewerIsA = $userRegistration && (int) $match->player_a_registration_id === (int) $userRegistration->id;
                                        $viewerIsB = $userRegistration && (int) $match->player_b_registration_id === (int) $userRegistration->id;
                                        $isViewerMatch = $viewerIsA || $viewerIsB;
                                        $viewerLostThisMatch = $isCompleted && $isViewerMatch && $match->winner_registration_id && (int) $match->winner_registration_id !== (int) $userRegistration->id;
                                    @endphp
                                    <div class="bg-zinc-900/40 backdrop-blur-md border {{ $viewerLostThisMatch ? 'border-rose-500/50 shadow-[0_0_22px_rgba(244,63,94,.12)]' : ($isOngoing ? 'border-cyan-500/40 shadow-[0_0_20px_rgba(34,211,238,0.08)]' : ($isCompleted ? 'border-zinc-800/40' : 'border-zinc-800/60')) }} rounded-2xl p-5 transition-all duration-300 hover:border-zinc-700">
                                        <div class="flex items-center gap-4">
                                            <!-- Match ID & Status -->
                                            <div class="hidden sm:flex flex-col items-center w-16 shrink-0 text-center">
                                                <span class="text-[9px] font-black text-zinc-700 uppercase tracking-widest">Match</span>
                                                <span class="text-lg font-black font-orbitron text-zinc-600">#{{ $match->id }}</span>
                                                <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full mt-1 {{ $isOngoing ? 'text-cyan-400 bg-cyan-950/40' : ($isCompleted ? 'text-zinc-600 bg-zinc-900' : 'text-fuchsia-400 bg-fuchsia-950/30') }}">
                                                    {{ $isOngoing ? 'Live' : ($isCompleted ? 'Done' : 'Soon') }}
                                                </span>
                                            </div>

                                            <!-- Player A -->
                                            <div class="flex-1 flex items-center gap-3 {{ $winnerA ? '' : ($isCompleted && !$winnerA && $playerA ? 'opacity-40' : '') }}">
                                                <div class="w-9 h-9 bg-zinc-950 rounded-xl border {{ $winnerA ? 'border-emerald-500/40' : 'border-zinc-800' }} flex items-center justify-center shrink-0 overflow-hidden text-[10px] font-black text-zinc-300">
                                                    @if($playerAAvatar)<img src="{{ $playerAAvatar }}" alt="{{ $playerA->username }}" class="w-full h-full object-cover" loading="lazy">@else{{ strtoupper(substr($playerA?->username ?? '??', 0, 2)) }}@endif
                                                </div>
                                                <div class="min-w-0">
                                                    <span class="block text-sm font-bold {{ $winnerA ? 'text-emerald-400' : ($playerA ? 'text-white' : 'text-zinc-700 italic') }} truncate">
                                                        {{ $playerA?->username ?? 'TBD' }}
                                                    </span>
                                                    @if($winnerA)
                                                        <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Winner</span>
                                                    @endif
                                                    @if($viewerIsA)<span class="ml-1 text-[9px] font-black uppercase tracking-widest text-cyan-400">You</span>@endif
                                                    @if($viewerLostThisMatch && $viewerIsA)<span class="ml-1 text-[9px] font-black uppercase tracking-widest text-rose-400">Lost</span>@endif
                                                </div>
                                                @if($winnerA)
                                                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400 ml-auto shrink-0"></i>
                                                @endif
                                            </div>

                                            <!-- VS Divider -->
                                            <div class="shrink-0 flex flex-col items-center">
                                                <span class="text-[10px] font-black text-zinc-700 bg-zinc-900 border border-zinc-800 rounded-lg px-2 py-1">VS</span>
                                            </div>

                                            <!-- Player B -->
                                            <div class="flex-1 flex items-center gap-3 justify-end text-right {{ $winnerB ? '' : ($isCompleted && !$winnerB && $playerB ? 'opacity-40' : '') }}">
                                                @if($winnerB)
                                                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400 mr-auto shrink-0"></i>
                                                @endif
                                                <div class="min-w-0">
                                                    <span class="block text-sm font-bold {{ $winnerB ? 'text-emerald-400' : ($playerB ? 'text-white' : 'text-zinc-700 italic') }} truncate">
                                                        {{ $playerB?->username ?? 'TBD' }}
                                                    </span>
                                                    @if($winnerB)
                                                        <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Winner</span>
                                                    @endif
                                                    @if($viewerIsB)<span class="ml-1 text-[9px] font-black uppercase tracking-widest text-cyan-400">You</span>@endif
                                                    @if($viewerLostThisMatch && $viewerIsB)<span class="ml-1 text-[9px] font-black uppercase tracking-widest text-rose-400">Lost</span>@endif
                                                </div>
                                                <div class="w-9 h-9 bg-zinc-950 rounded-xl border {{ $winnerB ? 'border-emerald-500/40' : 'border-zinc-800' }} flex items-center justify-center shrink-0 overflow-hidden text-[10px] font-black text-zinc-300">
                                                    @if($playerBAvatar)<img src="{{ $playerBAvatar }}" alt="{{ $playerB->username }}" class="w-full h-full object-cover" loading="lazy">@else{{ strtoupper(substr($playerB?->username ?? '??', 0, 2)) }}@endif
                                                </div>
                                            </div>

                                            <!-- Match Link -->
                                            @if((int) $tournament->workflow_version === 2 && $isViewerMatch)
                                                <button type="button" wire:click="openMatch('{{ $match->uuid }}')" aria-label="Open Submit Result" title="Open Submit Result" class="shrink-0 w-9 h-9 flex items-center justify-center bg-zinc-950 border border-zinc-800 hover:border-cyan-500/40 rounded-xl text-zinc-600 hover:text-cyan-400 transition-all duration-300">
                                                    <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                                                </button>
                                            @elseif((int) $tournament->workflow_version !== 2 && ($match->player_a_registration_id || $match->player_b_registration_id))
                                                <a href="/matches/{{ $match->uuid }}" wire:navigate aria-label="Open Match Room" title="Open Match Room" class="shrink-0 w-9 h-9 flex items-center justify-center bg-zinc-950 border border-zinc-800 hover:border-cyan-500/40 rounded-xl text-zinc-600 hover:text-cyan-400 transition-all duration-300">
                                                    <i data-lucide="external-link" class="w-4 h-4"></i>
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
                <div class="bg-zinc-900/40 backdrop-blur-md border border-zinc-800 rounded-[2rem] p-20 text-center relative overflow-hidden">
                    <i data-lucide="list-ordered" class="w-12 h-12 mx-auto text-zinc-700 mb-4"></i>
                    <h3 class="text-lg font-black text-zinc-300 font-orbitron tracking-widest">NO FIXTURES YET</h3>
                    <p class="text-xs font-medium text-zinc-500 mt-2">Fixtures will appear once the bracket is generated.</p>
                </div>
            @endif
        </div>

        <div data-tournament-content="matches-bracket" x-show="activeTab === 'matches' && bracketView === 'bracket'" x-cloak style="display: none;" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="w-full space-y-6">
            @if($rounds->isNotEmpty())
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-black font-orbitron tracking-widest text-white flex items-center space-x-3">
                        <span class="w-1.5 h-8 bg-violet-500 rounded-full"></span>
                        <span>TOURNAMENT BRACKET</span>
                    </h2>
                    <span class="text-[10px] text-zinc-500 font-black uppercase tracking-widest">{{ $rounds->count() }} rounds</span>
                </div>

                <!-- Bracket Scroll Container -->
                <div class="overflow-x-auto pb-6 pt-2 relative" style="scrollbar-width: thin; scrollbar-color: #3f3f46 #09090b;">
                    <div class="flex items-stretch gap-0 min-w-max">
                        @foreach($rounds as $roundIndex => $round)
                            @php
                                $roundCount = $rounds->count();
                                $isLast = $roundIndex === $roundCount - 1;
                                $roundLabel = match(true) {
                                    $isLast => 'Grand Final',
                                    $roundIndex === $roundCount - 2 => 'Semi Final',
                                    $roundIndex === $roundCount - 3 => 'Quarter Final',
                                    default => 'Round ' . $round->round_number,
                                };
                            @endphp

                            <!-- Round column -->
                            <div class="flex flex-col" style="min-width: 220px; width: 220px;">
                                <!-- Round header -->
                                <div class="relative mb-6 mx-3">
                                    <div class="absolute -inset-1 bg-gradient-to-r from-cyan-500/20 to-violet-500/20 rounded-xl blur opacity-60"></div>
                                    <div class="relative text-center bg-zinc-950 border border-zinc-800/80 rounded-xl py-2.5 px-3 shadow-lg">
                                        <span class="font-orbitron font-black text-[9px] uppercase tracking-[0.25em] text-white">{{ $roundLabel }}</span>
                                    </div>
                                </div>

                                <!-- Matches in round with bracket layout -->
                                <div class="flex-1 flex flex-col relative">
                                    @php $matchCount = $round->matches->count(); @endphp
                                    @foreach($round->matches as $mIdx => $match)
                                        @php
                                            $matchStatus = $match->status->value ?? $match->status;
                                            $isMatchOngoing = in_array($matchStatus, ['ready', 'in_progress', 'result_submitted', 'waiting_for_confirmation']);
                                            $isMatchCompleted = in_array($matchStatus, ['completed', 'forfeited']);
                                            $isMatchDisputed = $matchStatus === 'disputed';
                                            $playerAUser = $match->playerARegistration?->user;
                                            $playerBUser = $match->playerBRegistration?->user;
                                            $playerAAvatar = $playerAUser?->profile?->avatar_url;
                                            $playerBAvatar = $playerBUser?->profile?->avatar_url;
                                            $isPlayerAWinner = $match->winner_registration_id && $match->winner_registration_id === $match->player_a_registration_id;
                                            $isPlayerBWinner = $match->winner_registration_id && $match->winner_registration_id === $match->player_b_registration_id;
                                            $viewerIsA = $userRegistration && (int) $match->player_a_registration_id === (int) $userRegistration->id;
                                            $viewerIsB = $userRegistration && (int) $match->player_b_registration_id === (int) $userRegistration->id;
                                            $isViewerMatch = $viewerIsA || $viewerIsB;
                                            $viewerLostThisMatch = $isMatchCompleted && $isViewerMatch && $match->winner_registration_id && (int) $match->winner_registration_id !== (int) $userRegistration->id;
                                            $advancedMatch = (int) $tournament->workflow_version === 2 && $match->winner_registration_id
                                                ? $rounds->get($roundIndex + 1)?->matches->first(fn ($candidate) => (int) $candidate->player_a_registration_id === (int) $match->winner_registration_id || (int) $candidate->player_b_registration_id === (int) $match->winner_registration_id)
                                                : null;
                                        @endphp

                                        <!-- Match card wrapper for vertical spacing -->
                                        <div class="relative flex items-center" style="flex: 1; min-height: {{ max(100, 600 / max(1, $matchCount)) }}px;">
                                            <!-- Bracket connectors (right side) - vertical bar for grouping -->
                                            @if(!$isLast && (int) $tournament->workflow_version !== 2)
                                                @if($mIdx % 2 === 0)
                                                    <!-- Top of pair: draw right connector going down -->
                                                    <div class="absolute right-0 top-1/2 bottom-0 w-3 border-t border-r border-zinc-700/60 rounded-tr-lg" style="right: -12px; top: 50%; height: 50%;"></div>
                                                @else
                                                    <!-- Bottom of pair: draw right connector going up -->
                                                    <div class="absolute right-0 top-0 w-3 border-b border-r border-zinc-700/60 rounded-br-lg" style="right: -12px; height: 50%;"></div>
                                                    <!-- Plain line to the next matchup -->
                                                    <div class="absolute h-px bg-zinc-700/60" style="right: -36px; top: 50%; width: 24px;"></div>
                                                @endif
                                            @endif

                                            <!-- Match Card -->
                                            <div class="mx-3 w-full">
                                                <div class="bg-zinc-900/80 backdrop-blur-md border {{ $viewerLostThisMatch ? 'border-rose-500/60 shadow-[0_0_24px_rgba(244,63,94,.16)]' : ($isMatchOngoing ? 'border-cyan-500/50 shadow-[0_0_20px_rgba(34,211,238,0.12)]' : ($isMatchCompleted ? 'border-zinc-800/50' : 'border-zinc-800')) }} rounded-2xl overflow-hidden transition-all duration-300 hover:border-zinc-600 hover:shadow-lg group">
                                                    <!-- Match header -->
                                                    <div class="flex items-center justify-between px-3 py-2 border-b border-zinc-800/50 bg-zinc-950/50">
                                                        <span class="text-[8px] font-black text-zinc-700 uppercase tracking-widest">#{{ $match->id }}</span>
                                                        <span class="text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full {{ $isMatchOngoing ? 'text-cyan-400 bg-cyan-950/50' : ($isMatchCompleted ? 'text-emerald-400 bg-emerald-950/30' : ($isMatchDisputed ? 'text-red-400 bg-red-950/30' : 'text-fuchsia-500 bg-fuchsia-950/30')) }}">
                                                            {{ $isMatchOngoing ? '● Live' : ($isMatchCompleted ? 'Done' : ($isMatchDisputed ? 'Disputed' : 'Pending')) }}
                                                        </span>
                                                    </div>

                                                    <!-- Player A -->
                                                    <div class="flex items-center justify-between px-3 py-2.5 {{ $isPlayerAWinner ? 'bg-emerald-950/20' : '' }} transition-colors">
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <div class="w-6 h-6 rounded-md bg-zinc-950 border {{ $isPlayerAWinner ? 'border-emerald-500/40' : 'border-zinc-800' }} flex items-center justify-center shrink-0 overflow-hidden text-[8px] font-black text-zinc-300">
                                                                @if($playerAAvatar)<img src="{{ $playerAAvatar }}" alt="{{ $playerAUser->username }}" class="w-full h-full object-cover" loading="lazy">@else{{ strtoupper(substr($playerAUser?->username ?? '??', 0, 2)) }}@endif
                                                            </div>
                                                            <span class="text-xs font-bold truncate {{ $isPlayerAWinner ? 'text-emerald-400' : ($playerAUser ? 'text-zinc-200' : 'text-zinc-600 italic') }}" style="max-width: 120px;">
                                                                {{ $playerAUser?->username ?? 'Waiting...' }}
                                                            </span>
                                                            @if($viewerIsA)<span class="text-[8px] font-black uppercase text-cyan-400">You</span>@endif
                                                            @if($viewerLostThisMatch && $viewerIsA)<span class="text-[8px] font-black uppercase text-rose-400">Lost</span>@endif
                                                        </div>
                                                        @if($isPlayerAWinner)
                                                            <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-400 shrink-0"></i>
                                                        @endif
                                                    </div>

                                                    <!-- Divider -->
                                                    <div class="flex justify-center py-1 border-y border-zinc-800/30 bg-zinc-950/30">
                                                        <span class="text-[8px] font-black text-zinc-700 uppercase tracking-widest">vs</span>
                                                    </div>

                                                    <!-- Player B -->
                                                    <div class="flex items-center justify-between px-3 py-2.5 {{ $isPlayerBWinner ? 'bg-emerald-950/20' : '' }} transition-colors">
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <div class="w-6 h-6 rounded-md bg-zinc-950 border {{ $isPlayerBWinner ? 'border-emerald-500/40' : 'border-zinc-800' }} flex items-center justify-center shrink-0 overflow-hidden text-[8px] font-black text-zinc-300">
                                                                @if($playerBAvatar)<img src="{{ $playerBAvatar }}" alt="{{ $playerBUser->username }}" class="w-full h-full object-cover" loading="lazy">@else{{ strtoupper(substr($playerBUser?->username ?? '??', 0, 2)) }}@endif
                                                            </div>
                                                            <span class="text-xs font-bold truncate {{ $isPlayerBWinner ? 'text-emerald-400' : ($playerBUser ? 'text-zinc-200' : 'text-zinc-600 italic') }}" style="max-width: 120px;">
                                                                {{ $playerBUser?->username ?? 'Waiting...' }}
                                                            </span>
                                                            @if($viewerIsB)<span class="text-[8px] font-black uppercase text-cyan-400">You</span>@endif
                                                            @if($viewerLostThisMatch && $viewerIsB)<span class="text-[8px] font-black uppercase text-rose-400">Lost</span>@endif
                                                        </div>
                                                        @if($isPlayerBWinner)
                                                            <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-400 shrink-0"></i>
                                                        @endif
                                                    </div>

                                                    @if($advancedMatch)
                                                        <p class="border-t border-zinc-800/50 px-3 py-2 text-center text-[9px] font-bold text-cyan-400">{{ __('Advances to match #:match', ['match' => $advancedMatch->id]) }}</p>
                                                    @endif

                                                    <!-- Match link -->
                                                    @if((int) $tournament->workflow_version === 2 && $isViewerMatch)
                                                        <button type="button" wire:click="openMatch('{{ $match->uuid }}')" class="flex w-full items-center justify-center gap-1.5 border-t border-zinc-800/50 bg-zinc-950/40 px-3 py-2 text-[8px] font-black uppercase tracking-widest text-cyan-500 transition hover:bg-zinc-900 hover:text-cyan-300">
                                                            <span>Submit Result</span>
                                                            <i data-lucide="clipboard-check" class="w-2.5 h-2.5"></i>
                                                        </button>
                                                    @elseif((int) $tournament->workflow_version !== 2 && ($match->player_a_registration_id || $match->player_b_registration_id))
                                                        <a href="/matches/{{ $match->uuid }}" wire:navigate class="flex items-center justify-center gap-1.5 py-2 px-3 bg-zinc-950/40 hover:bg-zinc-900 border-t border-zinc-800/50 text-[8px] font-black text-zinc-600 hover:text-cyan-400 uppercase tracking-widest transition-all duration-200">
                                                            <span>Match Room</span>
                                                            <i data-lucide="external-link" class="w-2.5 h-2.5"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Connector line between rounds -->
                            @if(!$isLast)
                                <div class="flex items-center justify-center shrink-0" style="width: 36px; align-self: center;">
                                    @if((int) $tournament->workflow_version !== 2)<div class="h-px w-6 bg-zinc-700/70"></div>@endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                <p class="text-[10px] text-zinc-700 font-medium text-center">Scroll horizontally to view all rounds → </p>
            @else
                <div class="bg-zinc-900/40 backdrop-blur-md border border-zinc-800 rounded-[2.5rem] p-24 text-center relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-tr from-transparent to-indigo-950/10 pointer-events-none"></div>
                    <i data-lucide="git-branch" class="w-16 h-16 mx-auto text-zinc-800 mb-6"></i>
                    <h3 class="text-xl font-black text-zinc-400 font-orbitron tracking-widest uppercase">Bracket Not Generated</h3>
                    <p class="text-sm font-medium text-zinc-600 mt-4 max-w-sm mx-auto leading-relaxed">Brackets and result submission become available automatically after tournament entries lock.</p>
                </div>
            @endif
        </div>

        <!-- ===== ACTIVITY TAB ===== -->
        <div data-tournament-content="activity" x-show="activeTab === 'activity'" x-cloak style="display: none;" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
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

        @endif

        <!-- ===== STREAMS TAB ===== -->
        @php $streamItems = $streamService->streamsForTournament($tournament); $streamStatus = $streamService->statusLabel($tournament); @endphp
        @if(count($streamItems) > 0)
        <div data-tournament-content="streams" x-show="activeTab === 'streams'" x-cloak style="display: none;" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-black font-orbitron tracking-widest text-white flex items-center space-x-3">
                    <span class="w-1.5 h-8 bg-rose-500 rounded-full"></span>
                    <span>LIVE BROADCASTS</span>
                </h2>
                <span class="w-fit rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-widest {{ $streamStatus['class'] }}">
                    {{ $streamStatus['label'] }}
                </span>
            </div>

            <div class="grid grid-cols-1 gap-6">
                @foreach($streamItems as $stream)
                    <div class="overflow-hidden rounded-[2rem] border border-zinc-800/80 bg-zinc-950/70 shadow-2xl">
                        <div class="aspect-video bg-black">
                            <iframe
                                class="h-full w-full"
                                src="{{ $stream['embed_url'] }}"
                                title="{{ $tournament->name }} {{ $stream['label'] }} stream"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share; fullscreen"
                                allowfullscreen
                                loading="lazy"
                                referrerpolicy="strict-origin-when-cross-origin"></iframe>
                        </div>
                        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-zinc-800 bg-zinc-900 text-cyan-300">
                                    <i data-lucide="{{ $stream['icon'] }}" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-zinc-500">Streaming on</p>
                                    <p class="text-sm font-black uppercase tracking-widest text-white">{{ $stream['label'] }}</p>
                                </div>
                            </div>
                            <a href="{{ $stream['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-zinc-300 transition hover:border-zinc-600 hover:text-white">
                                <span>Open on {{ $stream['label'] }}</span>
                                <i data-lucide="external-link" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

    </div><!-- end tabs content -->

    <!-- V2 Underfilled Tournament Notice -->
    <div x-show="showUnderfilledNotice"
         x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center bg-zinc-950/85 p-4 backdrop-blur-md"
         @keydown.escape.window="showUnderfilledNotice = false">
        <div class="w-full max-w-md space-y-6 rounded-3xl border border-amber-500/30 bg-zinc-900 p-8 shadow-[0_0_50px_rgba(245,158,11,0.16)]">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-amber-500/30 bg-amber-500/10 text-amber-400">
                <i data-lucide="git-branch" class="h-8 w-8"></i>
            </div>
            <div class="space-y-2 text-center">
                <h3 class="text-xl font-black uppercase tracking-widest text-white font-orbitron">Tournament Started Under Capacity</h3>
                <p class="text-sm leading-relaxed text-zinc-400">
                    {{ $prizeCalculation['confirmed_count'] }} of {{ $tournament->max_participants }} teams joined. Automatic BYEs have been applied where needed, and the final prize has been adjusted based on confirmed paid entries.
                </p>
            </div>
            <button type="button" @click="showUnderfilledNotice = false" class="w-full rounded-xl bg-amber-500 px-5 py-3 text-[10px] font-black uppercase tracking-widest text-zinc-950 transition hover:bg-amber-400">
                Understood
            </button>
        </div>
    </div>

    @if(Auth::check() && Auth::user()->hasRole('PLAYER') && ! $isRegistered && $tournament->status->value === 'REGISTRATION_OPEN')
        <template x-teleport="body">
            <div x-show="showJoinModal"
                 @tournament-registration-completed.window="showJoinModal = false"
                 @keydown.escape.window="showJoinModal = false"
                 @click.self="showJoinModal = false"
                 role="dialog" aria-modal="true" aria-labelledby="join-tournament-title"
                 class="theme-player fixed inset-0 z-[100] flex items-center justify-center bg-zinc-950/85 p-4 backdrop-blur-md"
                 x-transition.opacity x-cloak>
                <div class="max-h-[90dvh] w-full max-w-md overflow-y-auto rounded-3xl border border-cyan-500/30 bg-zinc-900 p-6 shadow-[0_0_50px_rgba(34,211,238,0.12)] sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-cyan-400">Registration details</p>
                            <h2 id="join-tournament-title" class="mt-1 font-orbitron text-xl font-black text-white">Join {{ $tournament->name }}</h2>
                        </div>
                        <button type="button" @click="showJoinModal = false" aria-label="Close registration" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-800 hover:text-white">✕</button>
                    </div>
                    <p class="mt-3 text-xs leading-relaxed text-zinc-400">Enter the Game ID you want to use for this entry. You will enter it again for each new tournament or H2H join.</p>

                    @if(session('error'))
                        <p role="alert" class="mt-4 rounded-xl border border-red-500/40 bg-red-950/30 px-3 py-2 text-xs text-red-300">{{ session('error') }}</p>
                    @endif

                    <form wire:submit.prevent="register" class="mt-6 space-y-5">
                        @if($competitionPlatforms->count() > 1)
                            <div>
                                <label for="join-platform" class="mb-2 block text-xs font-bold text-zinc-300">Your platform</label>
                                <select id="join-platform" wire:model.live="selectedPlatformId" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-3 text-sm text-white focus:border-cyan-500 focus:outline-none">
                                    @foreach($competitionPlatforms as $platform)<option value="{{ $platform->id }}">{{ $platform->name }}</option>@endforeach
                                </select>
                                @error('selectedPlatformId')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>
                        @elseif($competitionPlatforms->isNotEmpty())
                            <p class="rounded-xl border border-zinc-800 bg-zinc-950/70 px-3 py-2.5 text-xs text-zinc-300">Platform: <strong class="text-white">{{ $competitionPlatforms->first()->name }}</strong></p>
                        @endif
                        <div>
                            <label for="join-game-id" class="mb-2 block text-xs font-bold text-zinc-300">{{ $gameIdSettings['label'] ?? 'Game ID / In-Game Name' }}</label>
                            <input id="join-game-id" x-ref="joinGameId" wire:model="gameIdValue" type="text" maxlength="191" autocomplete="off" placeholder="{{ $gameIdSettings['example'] ?? 'Enter the ID opponents can find' }}" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-3 text-sm text-white focus:border-cyan-500 focus:outline-none" required>
                            @error('gameIdValue')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            @if(!empty($gameIdSettings['instructions']))<p class="mt-2 text-xs leading-relaxed text-zinc-500">{{ $gameIdSettings['instructions'] }}</p>@endif
                        </div>
                        <p class="text-xs leading-relaxed text-zinc-500">Your match starts automatically when the tournament begins. {{ (int) $tournament->workflow_version === 2 ? 'Open Submit Result to play and report your result.' : 'Open the Match Room to play and submit your result.' }}</p>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showJoinModal = false" class="flex-1 rounded-xl border border-zinc-700 px-4 py-3 text-xs font-bold uppercase tracking-wider text-zinc-300 hover:bg-zinc-800">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="register" class="flex-1 rounded-xl bg-emerald-600 px-4 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-emerald-500 disabled:opacity-60">{{ ($tournament->team_size ?? 1) > 1 ? ($userTournamentTeam || $userSquad ? 'Register Team' : 'Find a Team') : $joinLabel }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    @endif

    <!-- Cancel Registration Modal -->
    <template x-teleport="body">
        <div x-data="{ open: false }"
             x-show="open"
             @open-registration-cancellation.window="open = true"
             @registration-cancellation-completed.window="open = false"
             @keydown.escape.window="open = false"
             role="dialog" aria-modal="true" aria-labelledby="cancel-registration-title"
             class="theme-player fixed inset-0 z-[100] flex items-center justify-center p-4 bg-zinc-950/85 backdrop-blur-md"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             x-cloak>
            <div class="relative max-h-[90dvh] overflow-y-auto bg-zinc-900 border border-red-500/30 rounded-3xl p-8 max-w-md w-full shadow-[0_0_50px_rgba(239,68,68,0.15)] space-y-6"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="scale-95 translate-y-4"
                 x-transition:enter-end="scale-100 translate-y-0">

                <div class="mx-auto w-16 h-16 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center text-red-500">
                    <i data-lucide="x-circle" class="w-8 h-8"></i>
                </div>

                <div class="text-center space-y-2">
                    <h3 id="cancel-registration-title" class="text-2xl font-black font-orbitron tracking-widest text-white uppercase">Cancel Registration?</h3>
                    <p class="text-zinc-400 text-sm font-medium leading-relaxed">
                        Are you sure you want to cancel your registration for <strong class="text-white">{{ $tournament->name }}</strong>?
                        @if((int) $tournament->workflow_version === 2 && $canCancelRegistration)
                            {{ __('Confirming cancels your registration immediately and credits the refund to your wallet.') }}
                            {{ __('Requests made less than 30 minutes before start incur a cancellation fee of 10% of the entry fee. At least 30 minutes before start, there is no cancellation fee.') }}
                            @if($tournament->entry_fee > 0)
                                <span role="status" class="mt-2 block rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-amber-200">{{ __('Cancellation fee: $:fee. Refund: $:refund.', $cancellationAmounts) }}</span>
                            @endif
                        @elseif((int) $tournament->workflow_version === 2)
                            {{ __('Cancellation is unavailable because the tournament has started or another request is pending.') }}
                        @elseif($tournament->entry_fee > 0)
                            Your entry fee of <strong class="text-cyan-400">${{ number_format((float)$tournament->entry_fee, 2) }}</strong> will be refunded to your wallet.
                        @endif
                    </p>
                </div>

                @if($cancellationError !== '')
                    <p role="alert" class="text-sm leading-6 text-red-400">{{ $cancellationError }}</p>
                @endif

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button type="button" @click="open = false"
                            class="flex-1 py-3 rounded-xl border border-zinc-800 hover:border-zinc-700 text-[10px] font-black text-zinc-500 hover:text-white uppercase tracking-widest transition-all duration-300">
                        Keep Registration
                    </button>
                    @if((int) $tournament->workflow_version !== 2 || $canCancelRegistration)
                        <button type="button" wire:click="cancelRegistration" wire:loading.attr="disabled" wire:target="cancelRegistration"
                                class="flex-1 py-3 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-[10px] font-black text-white uppercase tracking-widest shadow-[0_10px_20px_-5px_rgba(239,68,68,0.3)] transition-all duration-300">
                            <span wire:loading.remove wire:target="cancelRegistration">{{ __('Yes, Cancel & Refund') }}</span>
                            <span wire:loading wire:target="cancelRegistration">Processing...</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>

    </template>

</div>

<script>
function countdownTimer(targetDate) {
    return {
        days: 0,
        hours: 0,
        minutes: 0,
        seconds: 0,
        expired: false,
        interval: null,
        start() {
            this.tick();
            this.interval = setInterval(() => this.tick(), 1000);
        },
        tick() {
            const now = new Date().getTime();
            const target = new Date(targetDate).getTime();
            const diff = target - now;
            if (diff <= 0) {
                this.expired = true;
                clearInterval(this.interval);
                return;
            }
            this.days = Math.floor(diff / (1000 * 60 * 60 * 24));
            this.hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            this.minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            this.seconds = Math.floor((diff % (1000 * 60)) / 1000);
        }
    }
}
</script>
