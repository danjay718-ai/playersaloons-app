@props(['tournament'])
@php($countdownStatus = $tournament->status->value ?? $tournament->status)
<span {{ $attributes->class(['inline-flex min-w-0 items-center gap-2 text-xs font-semibold tabular-nums text-lime-300']) }}
    data-server-now="{{ now()->getTimestampMs() }}" x-data="tournamentCountdown(Number($el.dataset.serverNow), @js([
       'startsIn' => __('Starts in'),
       'ongoing' => __('In progress'),
       'completed' => __('Completed'),
       'cancelled' => __('Cancelled'),
       'refunded' => __('Refunded'),
       'pending' => __('Schedule pending'),
       'reached' => __('Starting now'),
    ]))">
    <i data-lucide="clock-3" class="h-3.5 w-3.5 shrink-0" aria-hidden="true"></i>
    <time class="min-w-0" title="{{ $tournament->start_at?->copy()->setTimezone(app(\App\Modules\Tournament\Services\TournamentTimezone::class)->value())->format('M d, Y H:i T') }}" datetime="{{ $tournament->start_at?->toIso8601String() }}" x-text="countdown(@js($tournament->start_at?->getTimestampMs()), @js($countdownStatus))">
        {{ in_array($countdownStatus, ['ONGOING', 'COMPLETED', 'CANCELLED', 'REFUNDED'], true) ? $tournament->status->label() : ($tournament->start_at?->diffForHumans() ?? __('Schedule pending')) }}
    </time>
</span>
