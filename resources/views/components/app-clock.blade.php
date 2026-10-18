@php
    $clockTimezone = app(\App\Modules\Tournament\Services\TournamentTimezone::class)->value();
    $clockNow = now($clockTimezone);
@endphp
<div x-data="systemClock(@js(['now' => $clockNow->toIso8601String(), 'timezone' => $clockTimezone]))" class="rounded-lg border border-zinc-800 bg-zinc-950/50 px-3 py-2">
    <p class="text-[9px] font-bold text-zinc-400">{{ __('App time (:timezone)', ['timezone' => $clockTimezone]) }}</p>
    <p x-text="time" class="font-mono text-sm font-bold tabular-nums text-zinc-100">{{ $clockNow->format('H:i:s') }}</p>
    <p x-text="date" class="text-[10px] text-zinc-500">{{ $clockNow->format('M d, Y') }}</p>
</div>
