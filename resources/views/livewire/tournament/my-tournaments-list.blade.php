<div class="player-my-games space-y-8" wire:poll.10s.visible x-data="tournamentListRealtime(() => $wire.$refresh())">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div>
            <p class="gr-eyebrow !mb-2"><span class="gr-slash">//</span> Player Command Center</p>
            <h1 class="player-my-games-title text-3xl md:text-5xl font-black font-orbitron tracking-tighter bg-gradient-to-r from-lime-300 via-cyan-300 to-white bg-clip-text text-transparent">
                MY GAMES
            </h1>
            <p class="text-sm text-zinc-400 mt-2 font-medium">
                Track your tournaments, head-to-head matches, result submissions, and completed results.
            </p>
        </div>
    </div>

    <!-- Competition Type -->
    <div class="player-competition-tabs grid grid-cols-2 gap-2 rounded-2xl border border-cyan-500/20 bg-zinc-900/50 p-1.5 sm:max-w-lg">
        <button type="button" wire:click="selectCompetition('tournaments')" class="rounded-xl px-4 py-3 font-orbitron text-[10px] font-black uppercase tracking-[0.18em] transition {{ $competitionTab === 'tournaments' ? 'player-competition-tab-active bg-lime-400 text-zinc-950 shadow-[0_0_20px_rgba(198,255,61,.3)]' : 'text-zinc-400 hover:text-white' }}">
            <span class="inline-flex items-center justify-center gap-2"><i data-lucide="trophy" class="h-4 w-4"></i>Tournaments</span>
        </button>
        <button type="button" wire:click="selectCompetition('head_to_head')" class="rounded-xl px-4 py-3 font-orbitron text-[10px] font-black uppercase tracking-[0.18em] transition {{ $competitionTab === 'head_to_head' ? 'player-competition-tab-active bg-lime-400 text-zinc-950 shadow-[0_0_20px_rgba(198,255,61,.3)]' : 'text-zinc-400 hover:text-white' }}">
            <span class="inline-flex items-center justify-center gap-2"><i data-lucide="swords" class="h-4 w-4"></i>Head-to-Head</span>
        </button>
    </div>

    <!-- Stats Banner -->
    @if($competitionTab === 'tournaments')
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            <x-player.metric-card label="Total Tournaments" :value="$totalTournamentCount" icon="trophy" />
            <x-player.metric-card label="Active" :value="$activeCount" tone="text-cyan-400" icon="zap" />
            <x-player.metric-card label="Victories" :value="$matchWins" tone="text-emerald-400" icon="trophy" />
            <x-player.metric-card label="Defeats" :value="$matchLosses" tone="text-rose-400" icon="skull" />
            <x-player.metric-card label="Closed" :value="$closedTournamentCount" tone="text-zinc-400" icon="archive" />
        </div>
        <p class="text-xs text-zinc-400">{{ __('Each tournament counts once: Active, Victory, Defeat, or Closed. Victories mean tournament championships. Closed includes cancelled, refunded, withdrawn, or unresolved competitions.') }}</p>
    @else
        <div class="grid grid-cols-2 gap-6 lg:grid-cols-4">
            <x-player.metric-card label="Active Head-to-Head" :value="$activeCount" tone="text-cyan-400" icon="zap" />
            <x-player.metric-card label="Match History" :value="$historyCount" tone="text-violet-400" icon="calendar" />
            <x-player.metric-card label="Match Wins" :value="$matchWins" tone="text-emerald-400" icon="trophy" />
            <x-player.metric-card label="Match Losses" :value="$matchLosses" tone="text-rose-400" icon="skull" />
        </div>
    @endif

    <!-- Tabs -->
    <div class="player-status-tabs flex items-center space-x-2 bg-zinc-900/50 backdrop-blur-md border border-cyan-500/20 p-1.5 rounded-2xl max-w-fit">
        <button wire:click="$set('tSubTab', 'active')" 
                class="px-8 py-2.5 rounded-xl font-orbitron text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 {{ $tSubTab === 'active' ? 'player-status-tab-active bg-zinc-800 text-lime-300 shadow-lg' : 'text-zinc-400 hover:text-white' }}">{{ __('Active (:value1)', ['value1' => ($activeCount)]) }}</button>
        <button wire:click="$set('tSubTab', 'history')" 
                class="px-8 py-2.5 rounded-xl font-orbitron text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 {{ $tSubTab === 'history' ? 'player-status-tab-active bg-zinc-800 text-lime-300 shadow-lg' : 'text-zinc-400 hover:text-white' }}">{{ __('Match History (:value1)', ['value1' => ($historyCount)]) }}</button>
    </div>

    <!-- Active Tournaments Tab -->
    @if($tSubTab === 'active')
        @if($activeMatchRooms->isNotEmpty() || $activeHeadToHeadMatches->isNotEmpty())
            <section class="player-match-rooms rounded-2xl border border-cyan-500/25 bg-cyan-500/5 p-4 sm:p-5">
                <div class="mb-3 flex items-center gap-2"><i data-lucide="swords" class="h-4 w-4 text-cyan-300"></i><h2 class="text-xs font-black uppercase tracking-widest text-white">Your Matches</h2></div>
                <div class="flex flex-col gap-3">
                    @foreach($activeMatchRooms as $match)
                        <a href="{{ (int) $match->tournament->workflow_version === 2 ? '/tournaments/'.$match->tournament->uuid.'/view?activeTab=submit-results&match='.$match->uuid : '/matches/'.$match->uuid }}" wire:navigate wire:key="match-room-{{ $match->uuid }}" class="player-match-room-link flex items-center justify-between gap-3 rounded-xl border border-cyan-500/20 bg-zinc-950/80 p-3.5 transition hover:border-lime-400/50">
                            <div class="min-w-0"><p class="truncate text-sm font-bold text-zinc-100">{{ $match->tournament->name }}</p><p class="mt-1 text-[10px] font-black uppercase tracking-wider text-cyan-400">{{ (int) $match->tournament->workflow_version === 2 && $match->status->value === 'waiting_for_confirmation' ? 'Waiting for opponent result' : str_replace('_', ' ', $match->status->value) }}</p></div>
                            <span class="shrink-0 font-orbitron text-[10px] font-black uppercase text-lime-300">{{ (int) $match->tournament->workflow_version === 2 ? 'Submit Result' : 'Open' }} →</span>
                        </a>
                    @endforeach
                    @foreach($activeHeadToHeadMatches as $match)
                        @php
                            $opponent = (int) $match->creator_user_id === (int) auth()->id() ? $match->opponent : $match->creator;
                        @endphp
                        <a href="/head-to-head?activeTab=active#duel-{{ $match->uuid }}" wire:navigate wire:key="h2h-match-room-{{ $match->uuid }}" class="player-match-room-link flex items-center justify-between gap-3 rounded-xl border border-cyan-500/20 bg-zinc-950/80 p-3.5 transition hover:border-lime-400/50">
                            <div class="min-w-0"><p class="truncate text-sm font-bold text-zinc-100">{{ __(':value1 vs :value2', ['value1' => ($match->game?->localizedName()), 'value2' => ($opponent?->username ?? 'Opponent')]) }}</p><p class="mt-1 text-[10px] font-black uppercase tracking-wider text-cyan-400">{{ str_replace('_', ' ', $match->status->value) }}</p></div>
                            <span class="shrink-0 font-orbitron text-[10px] font-black uppercase text-lime-300">Open →</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
        @if($tournaments->count() > 0)
            <div class="mb-2.5 hidden rounded-xl border border-white/5 bg-zinc-950/50 px-5 py-2.5 text-[10px] font-extrabold uppercase tracking-widest text-zinc-500 md:grid md:grid-cols-[52px_minmax(0,1.8fr)_120px_130px_170px_44px] md:items-center md:gap-5" aria-hidden="true">
                <span></span>
                <span>{{ __('Competition') }}</span>
                <span>{{ __('Entry Fee') }}</span>
                <span>{{ __('Prize') }}</span>
                <span>{{ __('Full Capacity') }}</span>
                <span></span>
            </div>
            <div class="flex flex-col gap-2.5">
                @foreach($tournaments as $tournament)
                    <div wire:key="tournament-{{ $tournament->uuid }}">
                    <x-player.tournament-card :tournament="$tournament" action-label="View Tournament" />
                    </div>
                @endforeach
            </div>
        @elseif($activeMatchRooms->isEmpty() && $activeHeadToHeadMatches->isEmpty())
            <div class="player-empty-state">
                <div class="player-empty-icon">
                    <i data-lucide="ghost" class="w-10 h-10"></i>
                </div>
                <h3 class="text-xl font-black text-zinc-200 font-orbitron tracking-wider">{{ __('NO ACTIVE :value1', ['value1' => ($competitionTab === 'head_to_head' ? __('Head-to-Head Matches') : __('Tournaments'))]) }}</h3>
                <p class="mt-2 text-sm text-zinc-500 max-w-sm mx-auto font-medium">{{ __('You haven\'t joined any active :value1 yet.', ['value1' => ($competitionTab === 'head_to_head' ? __('Head-to-Head Matches') : __('Tournaments'))]) }}</p>
                <a href="{{ $competitionTab === 'head_to_head' ? route('platform-h2h') : route('tournaments.browse', ['frequency' => 'daily']) }}" wire:navigate class="mt-6 inline-flex items-center space-x-2 px-6 py-3 bg-lime-400 hover:bg-lime-300 text-xs font-black uppercase tracking-widest text-zinc-950 rounded-xl transition-all shadow-lg">
                    <span>{{ __('Browse :value1', ['value1' => ($competitionTab === 'head_to_head' ? __('Head-to-Head') : __('Tournaments'))]) }}</span>
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
            </div>
        @endif
    @else
        <!-- Unified Match History -->
        <p class="mb-4 text-xs text-zinc-400">{{ __('Match History counts individual matches, including each tournament round. It is separate from tournament totals.') }}</p>
        @if($historyMatches->count() > 0)
            <div class="flex flex-col gap-3.5">
                @foreach($historyMatches as $historyMatch)
                    @php
                        $isTournamentMatch = $historyMatch['type'] === 'tournament';
                        $resultClasses = match($historyMatch['result']) {
                            'won' => 'border-lime-400/40 bg-lime-400/10 text-lime-300',
                            'lost' => 'border-red-500/30 bg-red-500/10 text-red-400',
                            default => 'border-zinc-700 bg-zinc-800 text-zinc-400',
                        };
                    @endphp
                    <a href="{{ $historyMatch['href'] }}" wire:navigate wire:key="history-{{ $historyMatch['type'] }}-{{ $loop->index }}" class="player-history-card group flex flex-col gap-4 rounded-2xl border border-cyan-500/20 bg-zinc-900/65 p-4 transition hover:border-lime-400/50 hover:bg-zinc-900/90 sm:p-5 md:flex-row md:items-center md:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-widest {{ $isTournamentMatch ? 'border-cyan-500/40 bg-cyan-950/40 text-cyan-300' : 'border-fuchsia-500/40 bg-fuchsia-950/40 text-fuchsia-300' }}">
                                    {{ $historyMatch['label'] }}
                                </span>
                                <span class="rounded-full border {{ $resultClasses }} px-2.5 py-1 text-[9px] font-black uppercase tracking-widest">{{ $historyMatch['result'] }}</span>
                                <span class="text-[10px] font-black uppercase tracking-wider text-zinc-400">{{ $historyMatch['game'] }}{{ $historyMatch['round'] ? ' · Round '.$historyMatch['round'] : '' }}</span>
                            </div>
                            <h3 class="mt-2 truncate font-orbitron text-base font-black text-white group-hover:text-lime-300">{{ __('vs :value1', ['value1' => ($historyMatch['opponent'])]) }}</h3>
                            @if($historyMatch['tournament'])<p class="mt-1 truncate text-xs text-zinc-400">{{ $historyMatch['tournament'] }}</p>@endif
                        </div>
                        <div class="flex shrink-0 items-center justify-between gap-4 border-t border-zinc-800/70 pt-3 text-[11px] font-bold uppercase tracking-wider text-zinc-400 md:border-t-0 md:pt-0">
                            <span>{{ $historyMatch['date']?->copy()->setTimezone(app(\App\Modules\Tournament\Services\TournamentTimezone::class)->value())->format('M d, Y · g:i A T') }}</span>
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-lime-400/40 bg-lime-400/10 px-3.5 py-2 font-orbitron text-[10px] font-black text-lime-300 transition group-hover:bg-lime-400 group-hover:text-zinc-950">
                                View Match
                                <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="player-empty-state">
                <div class="player-empty-icon">
                    <i data-lucide="ghost" class="w-10 h-10"></i>
                </div>
                <h3 class="text-xl font-black text-zinc-200 font-orbitron tracking-wider">NO HISTORY FOUND</h3>
                <p class="mt-2 text-sm text-zinc-500 max-w-sm mx-auto font-medium">
                    Completed tournament matches and head-to-head duels will appear here.
                </p>
            </div>
        @endif
    @endif

    <!-- Pagination -->
    <div class="mt-12 py-6 border-t border-zinc-900/50">
        {{ $tSubTab === 'active' ? $tournaments->links() : $historyMatches->links() }}
    </div>
</div>
