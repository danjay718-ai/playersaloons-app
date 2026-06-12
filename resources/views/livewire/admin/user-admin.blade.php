<div>
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <!-- Search and Filters -->
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <input type="text" wire:model.live="search" placeholder="Search by username or email..." 
                   class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 w-full sm:w-64">
            
            <select wire:model.live="statusFilter" 
                    class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="">All Statuses</option>
                @foreach(\App\Shared\Enums\UserStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ strtoupper($status->name) }}</option>
                @endforeach
            </select>

            <select wire:model.live="roleFilter" 
                    class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="">All Roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->name }}">{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if(session()->has('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <i data-lucide="alert-circle" class="w-4 h-4 mr-2"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Users Table -->
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                        <th class="p-4">User</th>
                        <th class="p-4">Display Name</th>
                        <th class="p-4">Country</th>
                        <th class="p-4">Assigned Roles</th>
                        <th class="p-4">Account status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @forelse($users as $usr)
                        <tr class="hover:bg-slate-900/40">
                            <td class="p-4 font-semibold text-slate-200">
                                <span class="block text-slate-200 hover:text-indigo-400 cursor-pointer" wire:click="selectUser({{ $usr->id }})">
                                    {{ $usr->username }}
                                </span>
                                <span class="block text-[10px] text-slate-500 font-normal mt-0.5">{{ $usr->email }}</span>
                            </td>
                            <td class="p-4 text-slate-300">
                                {{ $usr->profile->display_name ?? 'N/A' }}
                            </td>
                            <td class="p-4 text-slate-350">
                                {{ $usr->profile->country_code ?? 'N/A' }}
                            </td>
                            <td class="p-4">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($usr->roles as $role)
                                        <span class="inline-block px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700 text-slate-300 text-[9px] font-bold uppercase">
                                            {{ $role->name }}
                                        </span>
                                    @empty
                                        <span class="text-slate-500 italic text-[9px]">no roles</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="p-4">
                                @php
                                    $statusColors = [
                                        'active' => 'bg-emerald-500/10 text-emerald-455 border-emerald-500/20',
                                        'suspended' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                        'banned' => 'bg-slate-800 text-slate-500 border-slate-700',
                                    ];
                                    $col = $statusColors[$usr->status->value] ?? 'bg-slate-800 text-slate-400 border-slate-750';
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold uppercase {{ $col }}">
                                    {{ $usr->status->value }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <button wire:click="selectUser({{ $usr->id }})" class="px-3 py-1 bg-slate-800 hover:bg-slate-750 border border-slate-700 text-slate-200 font-bold rounded-lg text-[10px] uppercase tracking-wider">
                                    Manage
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500 italic">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div>
        {{ $users->links() }}
    </div>

    <!-- Detail Modal -->
    @if($showDetailModal && $selectedUser)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showDetailModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-4xl w-full overflow-hidden shadow-2xl relative z-10 max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">User Account Management</h3>
                        <p class="text-[9px] text-slate-500 font-mono mt-0.5">{{ $selectedUser->uuid }}</p>
                    </div>
                    <button wire:click="$set('showDetailModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-6 flex-grow text-xs">
                    <!-- Basic User Info and Roles Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Profile Card -->
                        <div class="bg-slate-900 border border-slate-850 p-4 rounded-xl space-y-3">
                            <span class="text-[10px] text-slate-500 font-bold uppercase block tracking-wider">Profile Details</span>
                            <div class="space-y-2">
                                <div>
                                    <span class="text-slate-550 block">Username</span>
                                    <span class="text-slate-200 font-semibold text-sm">{{ $selectedUser->username }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-550 block">Email Address</span>
                                    <span class="text-slate-200 truncate block">{{ $selectedUser->email }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-550 block">Display Name</span>
                                    <span class="text-slate-200">{{ $selectedUser->profile->display_name ?? 'N/A' }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <span class="text-slate-550 block">Country</span>
                                        <span class="text-slate-200">{{ $selectedUser->profile->country_code ?? 'N/A' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-550 block">Timezone</span>
                                        <span class="text-slate-250 truncate block">{{ $selectedUser->profile->timezone ?? 'N/A' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Roles & Security Card -->
                        <div class="bg-slate-900 border border-slate-850 p-4 rounded-xl space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] text-slate-500 font-bold uppercase block tracking-wider">Access Roles</span>
                                @if(auth()->user()->hasRole('SUPER_ADMIN'))
                                    <div class="flex space-x-1.5">
                                        <button wire:click="openRoleModal('assign')" class="text-[9px] bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-2 py-0.5 rounded">Assign</button>
                                        <button wire:click="openRoleModal('revoke')" class="text-[9px] bg-red-950 border border-red-900 text-red-400 font-bold px-2 py-0.5 rounded">Revoke</button>
                                    </div>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                @forelse($selectedUser->roles as $role)
                                    <span class="px-2.5 py-1 rounded bg-slate-950 border border-slate-850 text-slate-300 font-black uppercase text-[10px]">
                                        {{ $role->name }}
                                    </span>
                                @empty
                                    <span class="text-slate-500 italic">No staff/access roles assigned. Default is PLAYER.</span>
                                @endforelse
                            </div>

                            <hr class="border-slate-850 my-2">

                            <div class="space-y-2">
                                <div>
                                    <span class="text-slate-550 block">Account Status</span>
                                    <span class="inline-block mt-1 px-2.5 py-0.5 rounded border text-[10px] font-black uppercase
                                          {{ $selectedUser->status->value === 'active' ? 'bg-emerald-500/10 text-emerald-450 border-emerald-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20' }}">
                                        {{ $selectedUser->status->value }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-slate-550 block">KYC Verification Status</span>
                                    <span class="inline-block mt-1 px-2.5 py-0.5 rounded border text-[10px] font-black uppercase
                                          {{ $userKyc && $userKyc->status->value === 'approved' ? 'bg-emerald-500/10 text-emerald-450 border-emerald-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20' }}">
                                        {{ $userKyc ? str_replace('_', ' ', $userKyc->status->value) : 'NOT SUBMITTED' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Wallet Liquidity Card -->
                        <div class="bg-slate-900 border border-slate-850 p-4 rounded-xl space-y-3">
                            <span class="text-[10px] text-slate-500 font-bold uppercase block tracking-wider">Wallet Balance</span>
                            <div class="bg-slate-950 border border-slate-850 p-4 rounded-lg text-center">
                                <span class="text-slate-500 text-[10px] font-bold uppercase block tracking-wider">AVAILABLE FUNDS</span>
                                <span class="text-2xl font-black text-emerald-450 mt-1 block">
                                    ${{ number_format((float)($selectedUser->wallet?->cached_balance ?? 0.00), 2) }}
                                </span>
                            </div>
                            <div class="text-[10px] text-slate-500 space-y-1">
                                <p><strong>Wallet UUID:</strong> {{ $selectedUser->wallet?->uuid ?? 'N/A' }}</p>
                                <p><strong>Wallet Status:</strong> <span class="uppercase font-semibold text-slate-350">{{ $selectedUser->wallet?->status->value ?? 'N/A' }}</span></p>
                            </div>
                        </div>
                    </div>

                    <!-- Ledger / Tournaments history split -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Wallet Ledger Entries -->
                        <div class="bg-slate-900 border border-slate-850 p-4 rounded-xl">
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block mb-3">Wallet Ledger History (Last 10)</span>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-[11px] border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-800 text-[9px] text-slate-550 font-bold uppercase">
                                            <th class="pb-2">Reference</th>
                                            <th class="pb-2">Type</th>
                                            <th class="pb-2">Amount</th>
                                            <th class="pb-2">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800/40">
                                        @forelse($walletHistory as $entry)
                                            <tr>
                                                <td class="py-2 text-slate-300 font-medium truncate max-w-[120px]" title="{{ $entry->description }}">
                                                    {{ str_replace('_', ' ', $entry->reference_type) }}
                                                </td>
                                                <td class="py-2 capitalize">
                                                    <span class="px-1 py-0.5 rounded text-[8px] font-bold uppercase {{ $entry->type === 'credit' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                                                        {{ $entry->type }}
                                                    </span>
                                                </td>
                                                <td class="py-2 text-slate-300">${{ number_format($entry->amount, 2) }}</td>
                                                <td class="py-2 text-slate-450">${{ number_format($entry->running_balance, 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="py-4 text-center text-slate-500 italic">No ledger transaction history.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tournament participations -->
                        <div class="bg-slate-900 border border-slate-850 p-4 rounded-xl">
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block mb-3">Tournament History</span>
                            <div class="overflow-y-auto max-h-48 divide-y divide-slate-800/40">
                                @forelse($tournamentHistory as $reg)
                                    <div class="py-2 flex items-center justify-between text-[11px]">
                                        <div class="truncate mr-2">
                                            <span class="font-semibold text-slate-300 truncate block">{{ $reg->tournament->name }}</span>
                                            <span class="text-[9px] text-slate-500 block">{{ $reg->created_at->format('Y-m-d H:i') }}</span>
                                        </div>
                                        <div class="flex items-center space-x-2 text-[9px]">
                                            <span class="px-1.5 py-0.5 rounded bg-slate-950 text-slate-400 font-bold uppercase">{{ $reg->status->value }}</span>
                                            <span class="px-1.5 py-0.5 rounded bg-slate-950 text-slate-400 font-bold uppercase">{{ $reg->payment_status->value }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="py-4 text-center text-slate-500 italic">No registered tournaments found.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <div>
                        @if($selectedUser->status === \App\Shared\Enums\UserStatus::ACTIVE)
                            <button wire:click="openSuspendModal" class="bg-red-950 hover:bg-red-900 border border-red-900/50 text-red-400 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                                Suspend Account
                            </button>
                        @elseif($selectedUser->status === \App\Shared\Enums\UserStatus::SUSPENDED)
                            <button wire:click="unsuspend" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                                Unsuspend Account
                            </button>
                        @endif
                    </div>
                    <button wire:click="$set('showDetailModal', false)" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                        Close Directory
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Suspend Reason Modal -->
    @if($showSuspendModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" wire:click="$set('showSuspendModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-red-400 uppercase tracking-wider">Suspend User Account</h3>
                    <button wire:click="$set('showSuspendModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form wire:submit.prevent="suspend" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Reason for Suspension</label>
                        <input type="text" wire:model="suspendReason" placeholder="e.g. Terms of Service violation - collusion"
                               class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-red-500">
                        @error('suspendReason') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showSuspendModal', false)" 
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-red-600 hover:bg-red-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Suspend User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Role Edit Modal -->
    @if($showRoleModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" wire:click="$set('showRoleModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider capitalize">{{ $roleAction }} Role</h3>
                    <button wire:click="$set('showRoleModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form wire:submit.prevent="updateRole" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Select Role</label>
                        <select wire:model="selectedRole" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-350 focus:outline-none focus:border-indigo-500">
                            <option value="">Select Role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                        @error('selectedRole') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showRoleModal', false)" 
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Confirm {{ ucfirst($roleAction) }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
