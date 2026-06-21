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
        @elseif(in_array($match->status, [\App\Shared\Enums\HeadToHeadMatchStatus::COMPLETED, \App\Shared\Enums\HeadToHeadMatchStatus::CANCELLED, \App\Shared\Enums\HeadToHeadMatchStatus::EXPIRED], true))
            <div class="min-w-56 space-y-2 text-xs text-zinc-400">
                @if($match->winner)
                    <p>Winner: <strong class="text-emerald-300">{{ $match->winner->username }}</strong></p>
                @elseif($match->dispute_resolution?->value === 'refund')
                    <p class="text-amber-300">Voided and refunded</p>
                @else
                    <p class="text-zinc-500">Closed without payout.</p>
                @endif
                @if($match->dispute_resolution)
                    <p>Resolution: {{ str_replace('_', ' ', $match->dispute_resolution->value) }}</p>
                @endif
            </div>
        @endif
    </div>
</article>
