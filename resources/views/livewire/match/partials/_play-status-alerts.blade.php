@php
        $isRematch = (int) ($match->active_attempt_number ?? 1) > 1
            || in_array($match->resolution_reason, ['rematch', 'admin_draw_rematch', 'admin_rematch', 'agreed_rematch'], true)
            || $match->attempts->contains(fn ($attempt) => $attempt->status === 'rematch');
@endphp

@if($isParticipant && $rulingMessage)
    <div role="alert" class="rounded-xl border border-cyan-400/40 bg-cyan-500/10 p-4 text-cyan-100">
        <p class="text-xs font-black uppercase tracking-wider">{{ __('Admin Ruling') }}</p>
        <p class="mt-1 text-sm leading-relaxed">{{ $rulingMessage }}</p>
    </div>
@endif

@if($isParticipant && $match->status->value === 'ready')
    <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-4">
        <p class="text-xs font-bold text-white">{{ $isRematch ? __('This is a rematch') : __('Match starts automatically') }}</p>
        <p class="mt-1 text-[11px] text-zinc-400">{{ $match->tournament->status === \App\Shared\Enums\TournamentStatus::ONGOING ? 'The match is starting. Result submission will open shortly.' : 'Result submission opens when the tournament starts.' }}</p>
    </div>
@endif

@if($isParticipant && $match->status->value === 'in_progress')
    <div role="{{ $isRematch ? 'alert' : 'status' }}" class="flex items-center gap-3 rounded-xl border p-4 {{ $isRematch ? 'border-cyan-400/40 bg-cyan-500/10' : 'border-emerald-800/50 bg-emerald-950/20' }}">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $isRematch ? 'bg-cyan-400/15 text-cyan-300' : 'bg-emerald-500/15 text-emerald-300' }}"><i data-lucide="{{ $isRematch ? 'refresh-cw' : 'check-circle-2' }}" class="h-5 w-5"></i></span>
        @if($isRematch)
            <div><p class="text-xs font-black uppercase tracking-wider text-cyan-200">{{ __('This is a rematch — play again') }}</p><p class="mt-1 text-[11px] text-cyan-100/75">The previous attempt ended without a winner. Play this rematch and submit a new result{{ $isSubmitter ? '; your new result is submitted and the opponent still needs to respond' : '' }}.</p></div>
        @else
            <div><p class="text-xs font-black uppercase tracking-wider text-emerald-200">{{ __('Match in progress — play now') }}</p><p class="mt-1 text-[11px] text-zinc-400">{{ __('Play the match, then submit your result below.') }}</p></div>
        @endif
    </div>
@endif

