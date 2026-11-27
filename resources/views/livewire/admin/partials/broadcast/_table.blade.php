{{--
  Partial: broadcast/_table.blade.php
  Props:
    $broadcasts  – paginated BroadcastMessage collection
    $isSuperAdmin – bool
--}}
<div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                    <th class="p-4">Title</th>
                    <th class="p-4">Message</th>
                    <th class="p-4">Starts At</th>
                    <th class="p-4">Ends At</th>
                    <th class="p-4">Status</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                @forelse($broadcasts as $broadcast)
                    @php
                        $now      = now();
                        $isActive = (! $broadcast->starts_at || $broadcast->starts_at <= $now)
                                 && (! $broadcast->ends_at   || $broadcast->ends_at   >  $now);
                        $isExpired   = $broadcast->ends_at && $broadcast->ends_at <= $now;
                        $isScheduled = $broadcast->starts_at && $broadcast->starts_at > $now;
                    @endphp
                    <tr class="hover:bg-slate-900/40" wire:key="broadcast-{{ $broadcast->id }}">
                        <td class="p-4 max-w-[220px]">
                            <span class="block font-semibold text-slate-200 truncate">{{ $broadcast->title }}</span>
                            <div class="mt-1 flex flex-wrap items-center gap-1">
                                @if($broadcast->display_type === 'popup_modal' || $broadcast->display_type === 'both')
                                    <span class="inline-flex rounded border border-indigo-500/30 bg-indigo-500/10 px-1.5 py-0.5 text-[9px] font-bold uppercase text-indigo-300">
                                        {{ $broadcast->display_type === 'both' ? __('Feed + Popup') : __('Popup Modal') }}
                                    </span>
                                    <span class="inline-flex rounded border border-slate-700 bg-slate-800/80 px-1.5 py-0.5 text-[9px] font-semibold text-slate-300">
                                        {{ match($broadcast->target_surface) {
                                            'homepage' => __('Homepage'),
                                            'player_dashboard' => __('Dashboard'),
                                            default => __('Home & Dashboard'),
                                        } }}
                                    </span>
                                    @if($broadcast->media_type === 'video')
                                        <span class="inline-flex rounded border border-cyan-500/30 bg-cyan-500/10 px-1.5 py-0.5 text-[9px] font-bold uppercase text-cyan-300">
                                            {{ $broadcast->is_video_only ? __('Video Only') : __('Video') }}
                                        </span>
                                    @elseif($broadcast->media_type === 'image')
                                        <span class="inline-flex rounded border border-emerald-500/30 bg-emerald-500/10 px-1.5 py-0.5 text-[9px] font-bold uppercase text-emerald-300">
                                            {{ __('Image') }}
                                        </span>
                                    @endif
                                @else
                                    <span class="inline-flex rounded border border-slate-700/80 bg-slate-800/60 px-1.5 py-0.5 text-[9px] font-semibold uppercase text-slate-400">
                                        {{ __('Feed') }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="p-4 text-slate-400 max-w-[260px] truncate">
                            {{ $broadcast->message ?: '—' }}
                        </td>
                        <td class="p-4 text-slate-400 whitespace-nowrap">
                            {{ $broadcast->starts_at?->format('M d, Y H:i') ?? '—' }}
                        </td>
                        <td class="p-4 text-slate-400 whitespace-nowrap">
                            {{ $broadcast->ends_at?->format('M d, Y H:i') ?? '—' }}
                        </td>
                        <td class="p-4">
                            @if($isExpired)
                                <span class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold uppercase bg-slate-800 text-slate-500 border-slate-700">Expired</span>
                            @elseif($isScheduled)
                                <span class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold uppercase bg-amber-500/10 text-amber-400 border-amber-500/20">Scheduled</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold uppercase bg-emerald-500/10 text-emerald-400 border-emerald-500/20">Active</span>
                            @endif
                        </td>
                        <td class="p-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @php
                                    $rowPayload = [
                                        'id' => $broadcast->id,
                                        'title' => $broadcast->title,
                                        'message' => $broadcast->message,
                                        'display_type' => $broadcast->display_type ?: 'feed',
                                        'target_surface' => $broadcast->target_surface ?: 'both',
                                        'badge_label' => $broadcast->badge_label ?? '',
                                        'media_type' => $broadcast->media_type ?: 'none',
                                        'media_url' => $broadcast->media_url ?? '',
                                        'is_video_only' => (bool) $broadcast->is_video_only,
                                        'cta_label' => $broadcast->cta_label ?? '',
                                        'cta_url' => $broadcast->cta_url ?? '',
                                        'starts_at' => $broadcast->starts_at?->format('Y-m-d\TH:i') ?? '',
                                        'ends_at' => $broadcast->ends_at?->format('Y-m-d\TH:i') ?? '',
                                    ];
                                @endphp
                                <button type="button" @click="openEditModal(@js($rowPayload))"
                                        class="px-3 py-1 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 font-bold rounded-lg text-[10px] uppercase tracking-wider transition-colors">
                                    Edit
                                </button>
                                @unless($isExpired)
                                    <button wire:click="confirmExpire({{ $broadcast->id }})"
                                            class="px-3 py-1 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/20 text-amber-400 font-bold rounded-lg text-[10px] uppercase tracking-wider transition-colors">
                                        Expire
                                    </button>
                                @endunless
                                @if($isSuperAdmin)
                                    <livewire:admin.recoverable-delete resource="broadcasts" :record-id="$broadcast->id" :key="'delete-broadcast-'.$broadcast->id" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-500 italic">No broadcasts found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $broadcasts->links('vendor.livewire.custom-pagination') }}
