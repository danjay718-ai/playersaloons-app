<div>
    <!-- Filters Area -->
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-5 mb-6 shadow-sm">
        <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block mb-3">Filter Logs</span>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-[10px] font-bold text-slate-450 uppercase mb-1">Actor Username</label>
                <input type="text" wire:model.live="actorSearch" placeholder="Search actor..." 
                       class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold text-slate-450 uppercase mb-1">Action Name</label>
                <input type="text" wire:model.live="actionFilter" placeholder="e.g. kyc_approved" 
                       class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-450 uppercase mb-1">Entity Type</label>
                <input type="text" wire:model.live="entityTypeFilter" placeholder="e.g. KycSubmission" 
                       class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-450 uppercase mb-1">Start Date</label>
                <input type="date" wire:model.live="startDate" 
                       class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-350 focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-450 uppercase mb-1">End Date</label>
                <input type="date" wire:model.live="endDate" 
                       class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-350 focus:outline-none focus:border-indigo-500">
            </div>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                        <th class="p-4">Log ID</th>
                        <th class="p-4">Action Event</th>
                        <th class="p-4">Actor</th>
                        <th class="p-4">Subject Entity</th>
                        <th class="p-4">IP Address</th>
                        <th class="p-4">Timestamp</th>
                        <th class="p-4 text-right">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-900/40" wire:key="log-{{ $log->id }}">
                            <td class="p-4 text-slate-450 font-mono">
                                #{{ $log->id }}
                            </td>
                            <td class="p-4 font-semibold text-slate-200 uppercase tracking-wider text-[10px]">
                                <span class="bg-slate-800 px-2 py-0.5 rounded border border-slate-700 text-slate-300">
                                    {{ $log->description }}
                                </span>
                            </td>
                            <td class="p-4 text-slate-300">
                                @if($log->causer)
                                    <span class="font-semibold text-slate-250">{{ $log->causer->username }}</span>
                                    <span class="block text-[9px] text-slate-550">{{ $log->causer->roles->pluck('name')->first() ?? 'Staff' }}</span>
                                @else
                                    <span class="text-slate-550 italic">System Automation</span>
                                @endif
                            </td>
                            <td class="p-4 text-slate-400 font-medium">
                                @if($log->subject_type)
                                    <span class="block">{{ basename($log->subject_type) }}</span>
                                    <span class="block text-[9px] text-slate-550 font-mono">ID: {{ $log->subject_id }}</span>
                                @else
                                    <span class="text-slate-600 italic">—</span>
                                @endif
                            </td>
                            <td class="p-4 text-slate-500 font-mono">
                                {{ $log->properties['ip'] ?? '127.0.0.1' }}
                            </td>
                            <td class="p-4 text-slate-400">
                                {{ $log->created_at->format('Y-m-d H:i:s') }}
                                <span class="block text-[9px] text-slate-550 font-normal mt-0.5">{{ $log->created_at->diffForHumans() }}</span>
                            </td>
                            <td class="p-4 text-right">
                                <button wire:click="selectLog({{ $log->id }})" class="p-1.5 text-slate-400 hover:text-white bg-slate-800 rounded-lg" title="Open details">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500 italic">No audit log records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div>
        {{ $logs->links() }}
    </div>

    <!-- Detail Modal -->
    @if($showDetailModal && $selectedLog)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showDetailModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-lg w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Audit Log Details</h3>
                    <button wire:click="$set('showDetailModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-slate-500 font-medium block">Log ID</span>
                            <span class="text-slate-200 font-semibold block mt-0.5">#{{ $selectedLog->id }}</span>
                        </div>
                        <div>
                            <span class="text-slate-550 block">Log Name Group</span>
                            <span class="text-slate-200 font-semibold block mt-0.5">{{ $selectedLog->log_name }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-slate-550 block">Action Event</span>
                            <span class="inline-block mt-1 px-2 py-0.5 rounded border border-slate-700 bg-slate-800 text-slate-200 font-bold uppercase text-[9px]">
                                {{ $selectedLog->description }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-550 block font-medium">Timestamp</span>
                            <span class="text-slate-350 block mt-0.5">{{ $selectedLog->created_at->format('Y-m-d H:i:s') }}</span>
                        </div>
                    </div>

                    <hr class="border-slate-850">

                    <div>
                        <span class="text-slate-550 block font-semibold mb-1.5">JSON Parameters / Properties Payload</span>
                        <pre class="bg-slate-950 border border-slate-850 rounded-lg p-4 font-mono text-[10px] text-indigo-300 overflow-x-auto whitespace-pre-wrap leading-relaxed">{{ json_encode($selectedLog->properties, JSON_PRETTY_PRINT) }}</pre>
                    </div>

                    @if($selectedLog->causer)
                        <hr class="border-slate-850">
                        <div>
                            <span class="text-slate-550 block font-semibold mb-1">Actor (Caused By)</span>
                            <p class="text-slate-300"><strong>Username:</strong> {{ $selectedLog->causer->username }} (ID: {{ $selectedLog->causer->id }})</p>
                        </div>
                    @endif

                    @if($selectedLog->subject_type)
                        <hr class="border-slate-850">
                        <div>
                            <span class="text-slate-550 block font-semibold mb-1">Subject (Performed On)</span>
                            <p class="text-slate-300"><strong>Model:</strong> {{ $selectedLog->subject_type }} (ID: {{ $selectedLog->subject_id }})</p>
                        </div>
                    @endif
                </div>

                <div class="px-6 py-4 border-t border-slate-800 bg-[#0b0f19] flex justify-end">
                    <button wire:click="$set('showDetailModal', false)" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                        Close Log
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
