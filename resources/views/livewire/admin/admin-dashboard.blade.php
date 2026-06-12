<div>
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Escrow Card -->
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Escrow Balance</p>
                    <h3 class="text-2xl font-extrabold text-slate-100 mt-1">${{ number_format($stats['total_escrow'], 2) }}</h3>
                </div>
                <div class="p-3 bg-emerald-500/10 text-emerald-400 rounded-xl">
                    <i data-lucide="banknote" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center text-xs text-slate-500">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-2"></span>
                <span>Active platform liquidity</span>
            </div>
        </div>

        <!-- Users Card -->
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Users</p>
                    <h3 class="text-2xl font-extrabold text-slate-100 mt-1">{{ number_format($stats['total_users']) }}</h3>
                </div>
                <div class="p-3 bg-indigo-500/10 text-indigo-400 rounded-xl">
                    <i data-lucide="users" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center text-xs text-slate-500">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mr-2"></span>
                <span>Registered members</span>
            </div>
        </div>

        <!-- Pending KYC Card -->
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-5 shadow-sm relative group overflow-hidden">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pending KYC</p>
                    <h3 class="text-2xl font-extrabold text-slate-100 mt-1">{{ $stats['pending_kyc'] }}</h3>
                </div>
                <div class="p-3 bg-amber-500/10 text-amber-400 rounded-xl">
                    <i data-lucide="file-check" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center text-xs">
                @if($stats['pending_kyc'] > 0)
                    <a href="/admin/kyc" wire:navigate class="text-amber-400 hover:text-amber-300 font-semibold flex items-center">
                        <span>Review submissions</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5 ml-1"></i>
                    </a>
                @else
                    <span class="text-slate-500 flex items-center">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-500 mr-2"></span>
                        <span>All caught up</span>
                    </span>
                @endif
            </div>
        </div>

        <!-- Pending Withdrawals Card -->
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pending Withdrawals</p>
                    <h3 class="text-2xl font-extrabold text-slate-100 mt-1">{{ $stats['pending_withdrawals'] }}</h3>
                </div>
                <div class="p-3 bg-fuchsia-500/10 text-fuchsia-400 rounded-xl">
                    <i data-lucide="wallet" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center text-xs">
                @if($stats['pending_withdrawals'] > 0)
                    <a href="/admin/withdrawals" wire:navigate class="text-fuchsia-400 hover:text-fuchsia-300 font-semibold flex items-center">
                        <span>Process withdrawals</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5 ml-1"></i>
                    </a>
                @else
                    <span class="text-slate-500 flex items-center">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-500 mr-2"></span>
                        <span>No pending requests</span>
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Active Entities Dashboard -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Tournaments Overview -->
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-5 shadow-sm">
            <h4 class="text-sm font-bold text-slate-200 mb-4 flex items-center justify-between border-b border-slate-800 pb-3">
                <span>Tournaments Overview</span>
                <span class="text-xs text-indigo-400 hover:underline"><a href="/admin/tournaments" wire:navigate>Manage</a></span>
            </h4>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 font-medium">Active (Published & Ongoing)</span>
                    <span class="text-xs font-bold text-slate-200">{{ $stats['active_tournaments'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 font-medium">Completed</span>
                    <span class="text-xs font-bold text-slate-200">{{ $stats['completed_tournaments'] }}</span>
                </div>
            </div>
        </div>

        <!-- Matches Overview -->
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-5 shadow-sm">
            <h4 class="text-sm font-bold text-slate-200 mb-4 flex items-center justify-between border-b border-slate-800 pb-3">
                <span>Matches Overview</span>
                <span class="text-xs text-indigo-400 hover:underline"><a href="/admin/matches" wire:navigate>Manage</a></span>
            </h4>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 font-medium">Active (Ready & In Progress)</span>
                    <span class="text-xs font-bold text-slate-200">{{ $stats['active_matches'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 font-medium">Completed / Forfeited</span>
                    <span class="text-xs font-bold text-slate-200">{{ $stats['completed_matches'] }}</span>
                </div>
            </div>
        </div>

        <!-- Disputes Card -->
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-5 shadow-sm">
            <h4 class="text-sm font-bold text-slate-200 mb-4 flex items-center justify-between border-b border-slate-800 pb-3">
                <span>Match Disputes</span>
                <span class="text-xs text-indigo-400 hover:underline"><a href="/admin/matches?filter=disputes" wire:navigate>Manage</a></span>
            </h4>
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-medium">Open Disputes</span>
                    <p class="text-2xl font-extrabold text-red-400 mt-1">{{ $stats['open_disputes'] }}</p>
                </div>
                <div class="p-2.5 {{ $stats['open_disputes'] > 0 ? 'bg-red-500/10 text-red-400 animate-pulse' : 'bg-slate-800 text-slate-500' }} rounded-lg">
                    <i data-lucide="swords" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="mt-4 text-[11px]">
                @if($stats['open_disputes'] > 0)
                    <a href="/admin/matches?filter=disputes" wire:navigate class="text-red-400 hover:underline flex items-center">
                        <span>Disputes require attention</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5 ml-1"></i>
                    </a>
                @else
                    <span class="text-slate-500">No unresolved disputes</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Recent Activity log -->
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-6 shadow-sm">
        <h4 class="text-sm font-bold text-slate-200 mb-4 border-b border-slate-800 pb-3">Recent Security & Audit Logs</h4>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                        <th class="pb-3">Log Event</th>
                        <th class="pb-3">Actor</th>
                        <th class="pb-3">Context</th>
                        <th class="pb-3">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @forelse($recentActivities as $activity)
                        <tr class="hover:bg-slate-900/40">
                            <td class="py-3 text-slate-200 font-medium">{{ $activity->description }}</td>
                            <td class="py-3 text-slate-400">
                                @if($activity->causer)
                                    <span class="text-slate-300 font-semibold">{{ $activity->causer->username }}</span>
                                    <span class="text-[9px] bg-slate-800 px-1.5 py-0.5 rounded text-slate-500 uppercase ml-1">
                                        {{ $activity->causer->roles->pluck('name')->first() ?? 'User' }}
                                    </span>
                                @else
                                    <span class="text-slate-500 italic">System Auto</span>
                                @endif
                            </td>
                            <td class="py-3 text-slate-500 max-w-[200px] truncate">
                                {{ basename($activity->subject_type ?? '') }} (ID: {{ $activity->subject_id }})
                            </td>
                            <td class="py-3 text-slate-400">{{ $activity->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-slate-500 italic">No activity logs recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 text-right">
            <a href="/admin/audit-logs" wire:navigate class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold">View All Audit Logs →</a>
        </div>
    </div>
</div>
