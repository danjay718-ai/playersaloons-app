@props(['template'])

@php
    $slots = $template->scheduleSlots ?? collect();
    $timezone = $template->timezone ?: config('app.timezone');
    $start = $slots->filter(fn ($slot) => $slot->schedule_start_at !== null)->min('schedule_start_at');
    $end = $slots->filter(fn ($slot) => $slot->schedule_end_at !== null)->max('schedule_end_at');
    $frequency = $template->recurrence_frequency?->value ?? 'one_time';
    $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $recurrenceDetail = match ($frequency) {
        'daily' => 'Daily · every day',
        'weekly' => 'Weekly · '.$slots->pluck('day_of_week')->filter(fn ($day) => $day !== null)->unique()->sort()->map(fn ($day) => $weekdays[$day] ?? null)->filter()->implode(', '),
        'monthly' => 'Monthly · day '.$slots->pluck('day_of_month')->filter(fn ($day) => $day !== null)->unique()->sort()->implode(', '),
        default => 'One-time schedule',
    };
@endphp

<div class="min-w-[150px]">
    @if($start)
        <p class="font-medium text-slate-300">{{ $start->timezone($timezone)->format('M j, Y') }}@if($end) <span class="text-slate-600">→</span> {{ $end->timezone($timezone)->format('M j, Y') }}@endif</p>
    @else
        <p class="text-slate-500">Date not set</p>
    @endif
    <p class="mt-1 text-[10px] leading-relaxed text-slate-500">{{ $recurrenceDetail }}</p>
</div>
