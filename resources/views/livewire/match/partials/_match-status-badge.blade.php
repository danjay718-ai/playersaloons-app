@php
    $statusColors = [
        'pending' => 'bg-zinc-800 text-zinc-400 border-zinc-700',
        'ready' => 'bg-blue-950/30 text-blue-400 border-blue-900/40',
        'in_progress' => 'bg-violet-950/40 text-violet-300 border-violet-850/60 animate-pulse',
        'result_submitted' => 'bg-amber-950/30 text-amber-400 border-amber-900/40',
        'waiting_for_confirmation' => 'bg-amber-950/30 text-amber-400 border-amber-900/40',
        'disputed' => 'bg-red-950/30 text-red-400 border-red-900/40 shadow-sm shadow-red-500/5',
        'completed' => 'bg-emerald-950/30 text-emerald-400 border-emerald-900/40',
        'forfeited' => 'bg-zinc-800 text-zinc-400 border-zinc-700',
    ];
    $colorClass = $statusColors[$match->status->value ?? $match->status] ?? 'bg-zinc-800 text-zinc-400 border-zinc-700';
@endphp
<span class="text-xs font-bold uppercase tracking-widest border rounded-full px-4 py-1.5 {{ $colorClass }}">
    @if((int) $match->tournament->workflow_version === 2 && $match->status->value === 'waiting_for_confirmation')
        Waiting for opponent result
    @else
        {{ $match->status->value === 'ready' ? ($match->tournament->status === \App\Shared\Enums\TournamentStatus::ONGOING ? 'Starting' : 'Scheduled') : str_replace('_', ' ', $match->status->value ?? $match->status) }}
    @endif
</span>
