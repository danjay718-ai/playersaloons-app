<div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
    <x-ui.toasts />

    <x-player.panel class="lg:col-span-1">
        <div class="mb-5 flex items-center gap-2 border-b border-purple-500/10 pb-3">
            <i data-lucide="zap" class="h-5 w-5 text-fuchsia-400"></i>
            <h3 class="font-orbitron text-sm font-black uppercase tracking-wider text-zinc-200">Initiate Challenge</h3>
        </div>

        <form wire:submit="createChallenge" class="space-y-5">
            <div class="space-y-2">
                <label for="gameId" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Game</label>
                <select wire:model="gameId" id="gameId" class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 font-orbitron text-xs font-bold text-purple-300 focus:border-purple-500 focus:outline-none">
                    @foreach($games as $game)
                        <option value="{{ $game->id }}">{{ $game->translations->where('locale', 'en')->first()?->name ?? $game->slug }}</option>
                    @endforeach
                </select>
                @error('gameId') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-2">
                <label for="platformId" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Platform</label>
                <select wire:model="platformId" id="platformId" class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 font-orbitron text-xs font-bold text-purple-300 focus:border-purple-500 focus:outline-none">
                    <option value="">Any Platform</option>
                    @foreach($platforms as $platform)
                        <option value="{{ $platform->id }}">{{ $platform->name }}</option>
                    @endforeach
                </select>
                @error('platformId') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-2">
                <label for="gameHandle" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Game ID / Handle</label>
                <input wire:model="gameHandle" id="gameHandle" type="text" placeholder="Riot ID, Steam, PSN, lobby name..."
                       class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 text-xs font-bold text-zinc-200 placeholder-zinc-700 focus:border-purple-500 focus:outline-none">
                @error('gameHandle') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label for="stakeAmount" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Stake</label>
                    <input wire:model="stakeAmount" id="stakeAmount" type="number" min="1" max="1000" step="1"
                           class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 font-orbitron text-xs font-bold text-emerald-400 focus:border-purple-500 focus:outline-none">
                    @error('stakeAmount') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label for="matchTimerMinutes" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Timer</label>
                    <select wire:model="matchTimerMinutes" id="matchTimerMinutes" class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 font-orbitron text-xs font-bold text-purple-300 focus:border-purple-500 focus:outline-none">
                        <option value="">No strict timer</option>
                        <option value="15">15 min</option>
                        <option value="30">30 min</option>
                        <option value="60">60 min</option>
                    </select>
                    @error('matchTimerMinutes') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="space-y-2">
                <label for="region" class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Region</label>
                <input wire:model="region" id="region" type="text" placeholder="Optional"
                       class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 px-4 py-3 text-xs font-bold text-zinc-200 placeholder-zinc-700 focus:border-purple-500 focus:outline-none">
                @error('region') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-3 pt-2">
                <button type="button" wire:click="findDuel" class="w-full rounded-xl border border-fuchsia-400/20 bg-gradient-to-r from-purple-600 via-fuchsia-600 to-cyan-500 py-3 font-orbitron text-xs font-black uppercase tracking-widest text-white shadow-[0_0_15px_rgba(217,70,239,0.35)] transition-all hover:from-purple-500 hover:to-cyan-400">
                    Find Duel
                </button>
                <button type="submit" class="w-full rounded-xl border border-purple-500/20 bg-zinc-950 py-2.5 font-orbitron text-[9px] font-black uppercase tracking-widest text-purple-300 transition-colors hover:bg-zinc-900 hover:text-white">
                    Post Open Challenge
                </button>
            </div>
        </form>
    </x-player.panel>

    <div class="space-y-8 lg:col-span-2">
        <x-player.panel>
            <div class="mb-5 flex items-center justify-between border-b border-purple-500/10 pb-3">
                <div class="flex items-center gap-2">
                    <i data-lucide="swords" class="h-5 w-5 text-purple-400"></i>
                    <h3 class="font-orbitron text-sm font-black uppercase tracking-wider text-zinc-200">My Duels</h3>
                </div>
                <span class="rounded bg-purple-950 px-2 py-0.5 font-mono text-[9px] font-bold text-purple-400">{{ $myMatches->count() }} Active/Recent</span>
            </div>

            <div class="space-y-4">
                @forelse($myMatches as $match)
                    @php
                        $isCreator = $match->creator_user_id === auth()->id();
                        $opponent = $isCreator ? $match->opponent : $match->creator;
                        $myHandle = $isCreator ? $match->creator_game_handle : $match->opponent_game_handle;
                        $opponentHandle = $isCreator ? $match->opponent_game_handle : $match->creator_game_handle;
                        $gameName = $match->game->translations->where('locale', 'en')->first()?->name ?? $match->game->slug;
                    @endphp

                    <article class="rounded-2xl border border-zinc-800 bg-zinc-950/60 p-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="player-badge border-cyan-800/50 bg-cyan-950/30 text-cyan-400">{{ $gameName }}</span>
                                    <span class="player-badge border-zinc-800 bg-zinc-900 text-zinc-400">{{ str_replace('_', ' ', $match->status->value) }}</span>
                                </div>
                                <h4 class="mt-3 font-orbitron text-sm font-black uppercase tracking-wide text-white">
                                    vs {{ $opponent?->username ?? 'Unknown Player' }}
                                </h4>
                                <div class="mt-2 grid gap-1 text-xs text-zinc-400">
                                    <span>Your handle: <strong class="text-zinc-200">{{ $myHandle }}</strong></span>
                                    <span>Opponent handle: <strong class="text-cyan-300">{{ $opponentHandle }}</strong></span>
                                    <span>Stake: <strong class="text-emerald-400">${{ number_format((float) $match->stake_amount, 2) }}</strong> each</span>
                                    @if($match->match_timer_minutes)
                                        <span>Timer: {{ $match->match_timer_minutes }} minutes</span>
                                    @endif
                                </div>
                            </div>

                            @if($match->status === \App\Shared\Enums\HeadToHeadMatchStatus::IN_PROGRESS)
                                <div class="min-w-64 space-y-3">
                                    <select wire:model="resultWinnerUserId" class="w-full rounded-xl border border-zinc-800 bg-zinc-950 px-3 py-2 text-xs text-zinc-200">
                                        <option value="">Select winner</option>
                                        <option value="{{ $match->creator_user_id }}">{{ $match->creator?->username }}</option>
                                        <option value="{{ $match->opponent_user_id }}">{{ $match->opponent?->username }}</option>
                                    </select>
                                    <textarea wire:model="resultNotes" rows="2" placeholder="Optional notes / proof reference" class="w-full rounded-xl border border-zinc-800 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 placeholder-zinc-700"></textarea>
                                    <input wire:model="resultProof" type="file" accept="image/*" class="w-full rounded-xl border border-zinc-800 bg-zinc-950 px-3 py-2 text-[10px] text-zinc-400 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-800 file:px-3 file:py-1.5 file:text-[10px] file:font-bold file:text-zinc-200">
                                    @error('resultProof') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                                    <button wire:click="submitResult({{ $match->id }})" class="w-full rounded-xl bg-emerald-700 px-4 py-2 font-orbitron text-[10px] font-black uppercase tracking-widest text-white hover:bg-emerald-600">
                                        Submit Result
                                    </button>
                                </div>
                            @elseif($match->status === \App\Shared\Enums\HeadToHeadMatchStatus::WAITING_FOR_CONFIRMATION)
                                <div class="min-w-56 space-y-3 text-xs text-zinc-400">
                                    <p>
                                        Submitted winner:
                                        <strong class="text-white">
                                            {{ $match->winner_user_id === $match->creator_user_id ? $match->creator?->username : $match->opponent?->username }}
                                        </strong>
                                    </p>
                                    @if($match->result_proof_path)
                                        <a href="/storage/{{ $match->result_proof_path }}" target="_blank" class="inline-flex items-center gap-1 text-cyan-300 hover:text-cyan-200">
                                            <i data-lucide="image" class="h-3 w-3"></i>
                                            View submitted proof
                                        </a>
                                    @endif
                                    @if($match->result_submitted_by !== auth()->id())
                                        <button wire:click="confirmResult({{ $match->id }})" class="w-full rounded-xl bg-emerald-700 px-4 py-2 font-orbitron text-[10px] font-black uppercase tracking-widest text-white hover:bg-emerald-600">
                                            Confirm Result
                                        </button>
                                        <textarea wire:model="disputeNotes" rows="2" placeholder="Optional dispute notes" class="w-full rounded-xl border border-zinc-800 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 placeholder-zinc-700"></textarea>
                                        <input wire:model="disputeProof" type="file" accept="image/*" class="w-full rounded-xl border border-zinc-800 bg-zinc-950 px-3 py-2 text-[10px] text-zinc-400 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-800 file:px-3 file:py-1.5 file:text-[10px] file:font-bold file:text-zinc-200">
                                        @error('disputeProof') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                                        <button wire:click="disputeResult({{ $match->id }})" class="w-full rounded-xl border border-red-500/30 bg-red-950/40 px-4 py-2 font-orbitron text-[10px] font-black uppercase tracking-widest text-red-300 hover:bg-red-900">
                                            Dispute
                                        </button>
                                    @else
                                        <p>Waiting for opponent response.</p>
                                    @endif
                                </div>
                            @elseif($match->status === \App\Shared\Enums\HeadToHeadMatchStatus::DISPUTED)
                                <div class="min-w-56 space-y-2 text-xs text-zinc-400">
                                    <p class="font-bold text-red-300">Under admin review</p>
                                    @if($match->disputer)
                                        <p>Disputed by {{ $match->disputer->username }}</p>
                                    @endif
                                    @if($match->dispute_proof_path)
                                        <a href="/storage/{{ $match->dispute_proof_path }}" target="_blank" class="inline-flex items-center gap-1 text-cyan-300 hover:text-cyan-200">
                                            <i data-lucide="image" class="h-3 w-3"></i>
                                            View dispute proof
                                        </a>
                                    @endif
                                </div>
                            @elseif(in_array($match->status, [\App\Shared\Enums\HeadToHeadMatchStatus::COMPLETED, \App\Shared\Enums\HeadToHeadMatchStatus::CANCELLED], true))
                                <div class="min-w-56 space-y-2 text-xs text-zinc-400">
                                    @if($match->winner)
                                        <p>Winner: <strong class="text-emerald-300">{{ $match->winner->username }}</strong></p>
                                    @elseif($match->dispute_resolution?->value === 'refund')
                                        <p class="text-amber-300">Voided and refunded</p>
                                    @endif
                                    @if($match->dispute_resolution)
                                        <p>Resolution: {{ str_replace('_', ' ', $match->dispute_resolution->value) }}</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-800 bg-zinc-950/40 p-8 text-center text-sm text-zinc-500">
                        No H2H duels yet. Find a duel or post an open challenge.
                    </div>
                @endforelse
            </div>
        </x-player.panel>

        <x-player.panel>
            <div class="mb-5 flex items-center justify-between border-b border-purple-500/10 pb-3">
                <div class="flex items-center gap-2">
                    <i data-lucide="globe" class="h-5 w-5 text-purple-400"></i>
                    <h3 class="font-orbitron text-sm font-black uppercase tracking-wider text-zinc-200">Open Challenges</h3>
                </div>
                <span class="rounded bg-purple-950 px-2 py-0.5 font-mono text-[9px] font-bold text-purple-400">{{ $waitingChallenges->count() }} Open</span>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @forelse($waitingChallenges as $challenge)
                    @php
                        $gameName = $challenge->game->translations->where('locale', 'en')->first()?->name ?? $challenge->game->slug;
                    @endphp
                    <article class="rounded-xl border border-purple-500/10 bg-zinc-950/60 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-full border border-purple-500/20 bg-purple-900/30 font-orbitron text-[9px] font-bold text-purple-400">
                                        {{ strtoupper(substr($challenge->creator?->username ?? '??', 0, 2)) }}
                                    </div>
                                    <span class="font-orbitron text-xs font-bold text-zinc-300">{{ $challenge->creator?->username }}</span>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="rounded border border-fuchsia-900/40 bg-fuchsia-950/40 px-2 py-0.5 font-orbitron text-[8px] font-bold text-fuchsia-400">{{ $gameName }}</span>
                                    <span class="font-mono text-xs font-black text-emerald-400">${{ number_format((float) $challenge->stake_amount, 2) }}</span>
                                </div>
                                <p class="mt-2 text-[10px] text-zinc-500">
                                    {{ $challenge->platform?->name ?? 'Any platform' }}
                                    @if($challenge->region)
                                        / {{ $challenge->region }}
                                    @endif
                                </p>
                            </div>

                            @if($challenge->creator_user_id === auth()->id())
                                <button wire:click="cancelChallenge({{ $challenge->id }})" class="rounded-lg border border-red-500/30 bg-red-950/40 px-3 py-1.5 font-orbitron text-[8px] font-black uppercase tracking-widest text-red-300 hover:bg-red-900">
                                    Cancel
                                </button>
                            @else
                                <button wire:click="acceptChallenge({{ $challenge->id }})" class="rounded-lg border border-purple-500/30 bg-purple-950 px-3 py-1.5 font-orbitron text-[8px] font-black uppercase tracking-widest text-purple-300 hover:bg-purple-900 hover:text-white">
                                    Accept
                                </button>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-800 bg-zinc-950/40 p-8 text-center text-sm text-zinc-500 md:col-span-2">
                        No open challenges right now.
                    </div>
                @endforelse
            </div>
        </x-player.panel>
    </div>
</div>
