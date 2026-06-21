@php
    $isCreator = $match->creator_user_id === auth()->id();
    $opponent = $isCreator ? $match->opponent : $match->creator;
    $myHandle = $isCreator ? $match->creator_game_handle : $match->opponent_game_handle;
    $opponentHandle = $isCreator ? $match->opponent_game_handle : $match->creator_game_handle;
    $gameName = $match->game->translations->where('locale', 'en')->first()?->name ?? $match->game->slug;
@endphp

<article class="group relative overflow-hidden rounded-2xl border border-purple-500/20 bg-zinc-950/80 p-1 shadow-[0_0_15px_rgba(168,85,247,0.05)] backdrop-blur-md transition-all hover:border-purple-500/40 hover:shadow-[0_0_25px_rgba(168,85,247,0.15)]">
    <!-- Decorative background glow -->
    <div class="absolute -inset-24 bg-gradient-to-r from-purple-900/20 via-transparent to-cyan-900/20 opacity-0 blur-2xl transition-opacity duration-500 group-hover:opacity-100"></div>

    <div class="relative rounded-xl border border-white/5 bg-zinc-900/50 p-5">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            
            <!-- VS Section (Left side on desktop) -->
            <div class="flex-1">
                <div class="flex flex-wrap items-center justify-center gap-2 lg:justify-start">
                    <span class="rounded bg-purple-950/60 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-purple-400 ring-1 ring-inset ring-purple-500/30">{{ $gameName }}</span>
                    <span class="rounded bg-zinc-900 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-zinc-400 ring-1 ring-inset ring-zinc-700">{{ str_replace('_', ' ', $match->status->value) }}</span>
                    <span class="rounded bg-emerald-950/60 px-2.5 py-1 font-mono text-[10px] font-bold tracking-wider text-emerald-400 ring-1 ring-inset ring-emerald-500/30">
                        ${{ number_format((float) $match->stake_amount, 2) }} Stake
                    </span>
                    @if($match->match_timer_minutes)
                        <span class="rounded bg-orange-950/60 px-2.5 py-1 text-[10px] font-bold text-orange-400 ring-1 ring-inset ring-orange-500/30">
                            <i data-lucide="timer" class="inline-block h-3 w-3 mr-1"></i>{{ $match->match_timer_minutes }}m
                        </span>
                    @endif
                </div>

                <div class="mt-6 flex items-center justify-between gap-4 rounded-xl border border-zinc-800/50 bg-black/40 p-4">
                    <!-- Player 1 (You) -->
                    <div class="flex-1 text-center lg:text-right">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">You</p>
                        <p class="font-orbitron text-lg font-black text-white">{{ $myHandle }}</p>
                        <p class="text-[10px] text-zinc-400">{{ auth()->user()->username }}</p>
                    </div>

                    <!-- VS Badge -->
                    <div class="relative flex h-12 w-12 shrink-0 items-center justify-center">
                        <div class="absolute inset-0 animate-pulse rounded-full bg-fuchsia-600/20 blur-md"></div>
                        <span class="relative z-10 font-orbitron text-2xl font-black italic text-fuchsia-500 drop-shadow-[0_0_8px_rgba(217,70,239,0.8)]">VS</span>
                    </div>

                    <!-- Player 2 (Opponent) -->
                    <div class="flex-1 text-center lg:text-left">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Opponent</p>
                        <p class="font-orbitron text-lg font-black text-cyan-400">{{ $opponentHandle ?? 'Waiting...' }}</p>
                        <p class="text-[10px] text-zinc-400">{{ $opponent?->username ?? 'TBD' }}</p>
                    </div>
                </div>
            </div>

            <!-- Action Section (Right side on desktop) -->
            <div class="w-full lg:w-72 lg:shrink-0 border-t border-zinc-800 pt-5 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-6">
                @if($match->status === \App\Shared\Enums\HeadToHeadMatchStatus::IN_PROGRESS)
                    <div class="space-y-3">
                        <label class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Report Winner</label>
                        <select wire:model="resultWinnerUserId" class="w-full rounded-xl border border-zinc-700/50 bg-zinc-900/80 px-3 py-2 text-xs font-bold text-white focus:border-purple-500 focus:outline-none">
                            <option value="">Select winner...</option>
                            <option value="{{ $match->creator_user_id }}">{{ $match->creator?->username }} ({{ $match->creator_game_handle }})</option>
                            <option value="{{ $match->opponent_user_id }}">{{ $match->opponent?->username }} ({{ $match->opponent_game_handle }})</option>
                        </select>
                        <textarea wire:model="resultNotes" rows="2" placeholder="Optional notes / proof reference" class="w-full rounded-xl border border-zinc-700/50 bg-zinc-900/80 px-3 py-2 text-xs text-zinc-200 placeholder-zinc-600 focus:border-purple-500 focus:outline-none"></textarea>
                        <input wire:model="resultProof" type="file" accept="image/*" class="w-full rounded-xl border border-zinc-700/50 bg-zinc-900/80 px-3 py-2 text-[10px] text-zinc-400 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-800 file:px-3 file:py-1.5 file:text-[10px] file:font-bold file:text-zinc-200">
                        @error('resultProof') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                        
                        <button wire:click="submitResult({{ $match->id }})" wire:loading.attr="disabled" class="w-full rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 px-4 py-3 font-orbitron text-[10px] font-black uppercase tracking-widest text-white shadow-[0_0_10px_rgba(16,185,129,0.3)] transition-all hover:from-emerald-500 hover:to-emerald-400 disabled:opacity-50">
                            <span wire:loading.remove wire:target="submitResult({{ $match->id }})">Submit Result</span>
                            <span wire:loading wire:target="submitResult({{ $match->id }})">Submitting...</span>
                        </button>
                    </div>
                @elseif($match->status === \App\Shared\Enums\HeadToHeadMatchStatus::WAITING_FOR_CONFIRMATION)
                    <div class="space-y-4 text-xs text-zinc-400">
                        <div class="rounded-xl border border-zinc-800 bg-black/40 p-3 text-center">
                            <p class="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Reported Winner</p>
                            <p class="font-orbitron mt-1 text-sm font-black text-white">
                                {{ $match->winner_user_id === $match->creator_user_id ? $match->creator?->username : $match->opponent?->username }}
                            </p>
                        </div>
                        
                        @if($match->result_proof_path)
                            <a href="/storage/{{ $match->result_proof_path }}" target="_blank" class="inline-flex w-full items-center justify-center gap-1 rounded-lg border border-cyan-900/50 bg-cyan-950/30 px-3 py-2 text-cyan-300 hover:bg-cyan-900/50 hover:text-cyan-200 transition-colors">
                                <i data-lucide="image" class="h-3 w-3"></i>
                                View Proof
                            </a>
                        @endif
                        
                        @if($match->result_submitted_by !== auth()->id())
                            <button wire:click="confirmResult({{ $match->id }})" wire:loading.attr="disabled" class="w-full rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 px-4 py-3 font-orbitron text-[10px] font-black uppercase tracking-widest text-white shadow-[0_0_10px_rgba(16,185,129,0.3)] hover:scale-[1.02] transition-transform disabled:opacity-50">
                                <span wire:loading.remove wire:target="confirmResult({{ $match->id }})">Confirm Result</span>
                                <span wire:loading wire:target="confirmResult({{ $match->id }})">Confirming...</span>
                            </button>
                            
                            <div class="mt-4 space-y-3 rounded-xl border border-red-900/30 bg-red-950/10 p-3">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-red-400">Dispute this result</p>
                                <textarea wire:model="disputeNotes" rows="2" placeholder="Why is this incorrect?" class="w-full rounded-lg border border-red-900/50 bg-black/50 px-3 py-2 text-xs text-zinc-200 placeholder-zinc-600 focus:border-red-500 focus:outline-none"></textarea>
                                <input wire:model="disputeProof" type="file" accept="image/*" class="w-full text-[10px] text-zinc-400 file:mr-2 file:rounded-lg file:border-0 file:bg-red-900/30 file:px-2 file:py-1 file:text-[10px] file:font-bold file:text-red-300">
                                @error('disputeProof') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                                <button wire:click="disputeResult({{ $match->id }})" wire:loading.attr="disabled" class="w-full rounded-lg border border-red-500/30 bg-red-900/40 px-4 py-2 font-orbitron text-[10px] font-black uppercase tracking-widest text-red-200 hover:bg-red-800 disabled:opacity-50">
                                    <span wire:loading.remove wire:target="disputeResult({{ $match->id }})">Dispute</span>
                                    <span wire:loading wire:target="disputeResult({{ $match->id }})">Wait...</span>
                                </button>
                            </div>
                        @else
                            <div class="flex items-center justify-center gap-2 rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 text-center">
                                <i data-lucide="clock" class="h-4 w-4 animate-pulse text-amber-500"></i>
                                <span class="font-bold text-amber-500">Waiting for opponent response...</span>
                            </div>
                        @endif
                    </div>
                @elseif($match->status === \App\Shared\Enums\HeadToHeadMatchStatus::DISPUTED)
                    <div class="space-y-3 text-center text-xs text-zinc-400">
                        <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-red-950 ring-4 ring-red-900/30">
                            <i data-lucide="alert-triangle" class="h-5 w-5 text-red-500"></i>
                        </div>
                        <p class="font-orbitron font-black text-red-400 uppercase tracking-widest">Under Admin Review</p>
                        @if($match->disputer)
                            <p class="text-[10px]">Disputed by <strong class="text-white">{{ $match->disputer->username }}</strong></p>
                        @endif
                        @if($match->dispute_proof_path)
                            <a href="/storage/{{ $match->dispute_proof_path }}" target="_blank" class="mx-auto mt-2 flex w-max items-center gap-1 rounded bg-zinc-800 px-3 py-1.5 text-cyan-300 hover:bg-zinc-700">
                                <i data-lucide="image" class="h-3 w-3"></i> View Proof
                            </a>
                        @endif
                    </div>
                @elseif(in_array($match->status, [\App\Shared\Enums\HeadToHeadMatchStatus::COMPLETED, \App\Shared\Enums\HeadToHeadMatchStatus::CANCELLED, \App\Shared\Enums\HeadToHeadMatchStatus::EXPIRED], true))
                    <div class="flex flex-col items-center justify-center h-full space-y-2 text-center text-xs">
                        @if($match->winner)
                            <div class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-emerald-950 ring-4 ring-emerald-900/30 mb-1">
                                <i data-lucide="trophy" class="h-4 w-4 text-emerald-400"></i>
                            </div>
                            <p class="text-zinc-400">Winner</p>
                            <p class="font-orbitron text-lg font-black text-emerald-400">{{ $match->winner->username }}</p>
                        @elseif($match->dispute_resolution?->value === 'refund')
                            <i data-lucide="undo" class="h-6 w-6 text-amber-500 mb-1"></i>
                            <p class="font-orbitron font-black text-amber-400 uppercase tracking-wider">Voided & Refunded</p>
                        @else
                            <i data-lucide="x-circle" class="h-6 w-6 text-zinc-600 mb-1"></i>
                            <p class="font-orbitron font-black text-zinc-500 uppercase tracking-wider">Closed</p>
                            <p class="text-[10px] text-zinc-600">No payout awarded.</p>
                        @endif
                        
                        @if($match->dispute_resolution)
                            <span class="mt-2 inline-block rounded bg-zinc-900 px-2 py-1 text-[9px] font-bold text-zinc-500">
                                Resolved: {{ str_replace('_', ' ', $match->dispute_resolution->value) }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>
            
        </div>
    </div>
</article>
