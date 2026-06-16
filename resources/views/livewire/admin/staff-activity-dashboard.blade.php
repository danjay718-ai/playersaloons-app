<div>
    {{-- Filters --}}
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-5 mb-6 shadow-sm">
        <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block mb-3">Filter</span>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-[10px] font-bold text-slate-450 uppercase mb-1">Staff Username</label>
                <input type="text" wire:model.live="staffFilter" placeholder="Search staff..."
                       class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-450 uppercase mb-1">Date From</label>
                <input type="date" wire:model.live="dateFrom"
                       class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-350 focus:outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-450 uppercase mb-1">Date To</label>
                <input type="date" wire:model.live="dateTo"
                       class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-350 focus:outline-none focus:border-indigo-500">
            </div>
        </div>
    </div>

    {{-- Top Actions Summary --}}
    @if($topActions->isNotEmpty())
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-5 mb-6 shadow-sm">
            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block mb-3">Top Actions in Period</span>
            <div class="flex flex-wrap gap-2">
                @foreach($topActions as $action)
                    <span class="inline-flex items-center gap-1.5 bg-slate-800 border border-slate-700 rounded-full px-3 py-1 text-[10px] font-bold text-slate-300 uppercase tracking-wider">
                        {{ $action->description }}
                        <span class="bg-indigo-600 text-white rounded-full px-1.5 py-0.5 text-[9px]">{{ $action->total }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Staff Activity Table --}}
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                        <th class="p-4">Staff Member</th>
                        <th class="p-4">Role</th>
                        <th class="p-4">Total Actions</th>
                        <th class="p-4">Action Breakdown</th>
                        <th class="p-4">Last Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @forelse($rows as $row)
                        <tr class="hover:bg-slate-900/40" wire:key="staff-{{ $row['user']->id }}">
                            <td class="p-4">
                                <span class="font-semibold text-slate-200">{{ $row['user']->username }}</span>
                                <span class="block text-[9px] text-slate-550 font-mono">{{ $row['user']->email }}</span>
                            </td>
                            <td class="p-4">
                                <span class="bg-slate-800 px-2 py-0.5 rounded text-[9px] font-bold text-indigo-300 uppercase">
                                    {{ $row['user']->roles->pluck('name')->first() ?? '—' }}
                                </span>
                            </td>
                            <td class="p-4">
                                @if($row['total'] > 0)
                                    <span class="text-slate-100 font-bold text-sm">{{ $row['total'] }}</span>
                                @else
                                    <span class="text-slate-600 italic">No activity</span>
                                @endif
                            </td>
                            <td class="p-4">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($row['counts'] as $action => $count)
                                        <span class="inline-flex items-center gap-1 bg-slate-900 border border-slate-800 rounded px-1.5 py-0.5 text-[9px] text-slate-400">
                                            {{ $action }} <span class="text-indigo-400 font-bold">×{{ $count }}</span>
                                        </span>
                                    @empty
                                        <span class="text-slate-600 italic text-[10px]">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="p-4 text-slate-500 text-[10px]">
                                @if($row['last_at'])
                                    {{ $row['last_at']->format('Y-m-d H:i') }}
                                    <span class="block text-slate-600">{{ $row['last_at']->diffForHumans() }}</span>
                                @else
                                    <span class="text-slate-600 italic">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-500 italic">No staff members found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
