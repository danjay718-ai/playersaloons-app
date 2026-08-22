<div x-data="{ createModal: null }" @user-created.window="createModal = null">
    <!-- Tabs -->
    <div class="flex space-x-1 border-b border-slate-800 mb-6 relative">
        <div wire:loading wire:target="setTab" class="absolute top-0 right-0 p-3">
            <svg class="animate-spin h-4 w-4 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
        </div>
        <button wire:click="setTab('players')" class="px-4 py-3 text-xs font-bold uppercase tracking-wider focus:outline-none transition-colors {{ $activeTab === 'players' ? 'text-indigo-400 border-b-2 border-indigo-400' : 'text-slate-500 hover:text-slate-300' }}">
            Players ({{ $playersCount }})
        </button>
        <button wire:click="setTab('users')" class="px-4 py-3 text-xs font-bold uppercase tracking-wider focus:outline-none transition-colors {{ $activeTab === 'users' ? 'text-indigo-400 border-b-2 border-indigo-400' : 'text-slate-500 hover:text-slate-300' }}">
            All Users ({{ $usersCount }})
        </button>
    </div>

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <!-- Search and Filters -->
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            @if($activeTab === 'users')
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by username or email..." 
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
            @else
                <select wire:model.live="onlineFilter" 
                        class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                    <option value="">All (Online/Offline)</option>
                    <option value="online">Online Only</option>
                    <option value="offline">Offline Only</option>
                </select>

                <input type="text" wire:model.live.debounce.300ms="countryFilter" placeholder="Country Code (e.g. PH)" 
                       class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 w-full sm:w-48">
            @endif
        </div>
        @can('create', \App\Modules\Identity\Models\User::class)
            <div class="flex w-full gap-2 sm:w-auto">
                <button type="button" @click="createModal = 'player'" class="flex-1 rounded-lg border border-indigo-500/40 bg-indigo-500/10 px-4 py-2 text-xs font-bold uppercase tracking-wider text-indigo-300 transition hover:bg-indigo-500/20 sm:flex-none">Add Player</button>
                <button type="button" @click="createModal = 'user'" class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-indigo-500 sm:flex-none">Add User</button>
            </div>
        @endcan
    </div>

    <!-- Feedback Alerts -->
    @if(session()->has('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Users Table -->
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
        <div class="overflow-x-auto min-h-[300px]">
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
                        <tr class="hover:bg-slate-900/40" wire:key="user-{{ $usr->id }}">
                            <td class="p-4 font-semibold text-slate-200">
                                <span class="flex items-center gap-2 text-slate-200 hover:text-indigo-400 cursor-pointer" wire:click="selectUser({{ $usr->id }})">
                                    <span class="inline-block w-2 h-2 rounded-full flex-shrink-0 {{ $usr->is_online ? 'bg-emerald-400' : 'bg-slate-600' }}" title="{{ $usr->is_online ? 'Online' : 'Offline' }}"></span>
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
                                <div x-data="{ open: false }" class="relative inline-block text-left" @click.away="open = false" wire:ignore.self>
                                    <button @click="open = !open" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-750 border border-slate-700 text-slate-200 font-bold rounded-lg text-[10px] uppercase tracking-wider flex items-center space-x-1 ml-auto transition-colors">
                                        <span>Actions</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    <div x-show="open" x-transition style="display: none;" class="absolute right-0 mt-2 w-40 bg-slate-800 rounded-lg shadow-xl z-50 border border-slate-700 py-1 overflow-hidden">
                                        @can('update', $usr)<button wire:click="editUser({{ $usr->id }}); open = false" class="block w-full text-left px-4 py-2.5 text-xs text-slate-300 hover:bg-slate-700 hover:text-white uppercase font-bold tracking-wider transition-colors relative">Edit Data</button>@endcan
                                        @can('resetPassword', $usr)<button wire:click="prepareResetPassword({{ $usr->id }}); open = false" class="block w-full text-left px-4 py-2.5 text-xs text-slate-300 hover:bg-slate-700 hover:text-white uppercase font-bold tracking-wider transition-colors">Reset Password</button>@endcan
                                        @can('delete', $usr)<button wire:click="confirmDeleteUser({{ $usr->id }}); open = false" class="block w-full text-left px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-red-400 transition-colors hover:bg-red-950/40 hover:text-red-300">Delete User</button>@endcan
                                        <div class="border-t border-slate-700 my-0.5"></div>
                                        <button wire:click="selectUser({{ $usr->id }}); open = false" class="block w-full text-left px-4 py-2.5 text-xs text-indigo-400 hover:bg-slate-700 hover:text-indigo-300 uppercase font-bold tracking-wider transition-colors">
                                            Full Manage
                                        </button>
                                    </div>
                                </div>
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

    <!-- Create User / Player Modal -->
        <div x-cloak x-show="createModal !== null" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click="createModal = null"></div>
            <div class="relative z-10 w-full max-w-md overflow-hidden rounded-xl border border-slate-800 bg-[#0f172a] shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 bg-[#0b0f19] px-6 py-4"><h3 class="text-sm font-bold uppercase tracking-wider text-slate-200">Add <span x-text="createModal === 'player' ? 'Player' : 'User'"></span></h3><button type="button" @click="createModal = null" class="text-slate-400 hover:text-white">×</button></div>
                <form @submit.prevent="await $wire.set('createMode', createModal); await $wire.createUser()" class="space-y-4 p-6">
                    <div><label class="mb-1 block text-xs font-bold uppercase text-slate-400">Username</label><input wire:model="createUsername" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100">@error('createUsername')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror</div>
                    <div><label class="mb-1 block text-xs font-bold uppercase text-slate-400">Email</label><input wire:model="createEmail" type="email" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100">@error('createEmail')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror</div>
                    <div><label class="mb-1 block text-xs font-bold uppercase text-slate-400">Display Name</label><input wire:model="createDisplayName" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100"></div>
                    <div><label class="mb-1 block text-xs font-bold uppercase text-slate-400">Country Code</label><input wire:model="createCountryCode" maxlength="2" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm uppercase text-slate-100"></div>
                    <div x-show="createModal === 'user'"><label class="mb-1 block text-xs font-bold uppercase text-slate-400">Role</label><select wire:model="createRole" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100"><option value="">Select role</option>@foreach($roles->where('name', '!=', 'PLAYER') as $role)@if($role->name !== 'SUPER_ADMIN' || auth()->user()?->hasRole('SUPER_ADMIN'))<option value="{{ $role->name }}">{{ $role->name }}</option>@endif @endforeach</select>@error('createRole')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror</div>
                    <div><label class="mb-1 block text-xs font-bold uppercase text-slate-400">Password</label><input wire:model="createPassword" type="password" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100">@error('createPassword')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror</div>
                    <div><label class="mb-1 block text-xs font-bold uppercase text-slate-400">Confirm Password</label><input wire:model="createPasswordConfirmation" type="password" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100"></div>
                    <div class="flex justify-end gap-3 border-t border-slate-800 pt-4"><button type="button" @click="createModal = null" class="rounded-lg bg-slate-800 px-4 py-2.5 text-xs font-bold uppercase text-slate-200">Cancel</button><button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold uppercase text-white hover:bg-indigo-500">Create</button></div>
                </form>
            </div>
        </div>

    <!-- Delete User Modal -->
    @if($showDeleteModal)
        <div x-data="{ open: true }" x-show="open" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click="open = false; $wire.set('showDeleteModal', false)"></div>
            <div class="relative z-10 w-full max-w-md overflow-hidden rounded-xl border border-red-900/60 bg-[#0f172a] shadow-2xl">
                <div class="border-b border-slate-800 bg-[#0b0f19] px-6 py-4"><h3 class="text-sm font-bold uppercase tracking-wider text-red-400">Delete User?</h3></div>
                <div class="space-y-5 p-6"><p class="text-sm leading-relaxed text-slate-400">This soft-deletes the account and removes it from normal user lists. Existing records and audit history are retained.</p><div class="flex justify-end gap-3"><button type="button" @click="open = false; $wire.set('showDeleteModal', false)" class="rounded-lg bg-slate-800 px-4 py-2.5 text-xs font-bold uppercase text-slate-200">Cancel</button><button wire:click="deleteUser" class="rounded-lg bg-red-600 px-4 py-2.5 text-xs font-bold uppercase text-white hover:bg-red-500">Delete User</button></div></div>
            </div>
        </div>
    @endif

    <!-- Pagination -->
    <div class="mt-4">
        {{ $users->links('vendor.livewire.custom-pagination') }}
    </div>

    <!-- Edit User Data Modal -->
    @if($showEditModal)
        <div x-data="{ open: true }" x-show="open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click="open = false; $wire.set('showEditModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Edit User Data</h3>
                    <button type="button" @click="open = false; $wire.set('showEditModal', false)" class="text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form wire:submit.prevent="updateUser" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Username</label>
                        <input type="text" wire:model="editUsername" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        @error('editUsername') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Email</label>
                        <input type="email" wire:model="editEmail" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        @error('editEmail') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Display Name</label>
                        <input type="text" wire:model="editDisplayName" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        @error('editDisplayName') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Country Code (e.g. PH)</label>
                        <input type="text" wire:model="editCountryCode" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500" maxlength="2">
                        @error('editCountryCode') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" @click="open = false; $wire.set('showEditModal', false)" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Reset Password Modal -->
    @if($showPasswordModal)
        <div x-data="{ open: true }" x-show="open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click="open = false; $wire.set('showPasswordModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Reset User Password</h3>
                    <button type="button" @click="open = false; $wire.set('showPasswordModal', false)" class="text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form wire:submit.prevent="resetPassword" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">New Password</label>
                        <input type="password" wire:model="newPassword" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        @error('newPassword') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Confirm New Password</label>
                        <input type="password" wire:model="newPasswordConfirmation" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" @click="open = false; $wire.set('showPasswordModal', false)" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                            Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Detail Modal -->
    @if($showDetailModal && $selectedUser)
        <div x-data="{ open: true }" x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="open = false; $wire.set('showDetailModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-4xl w-full overflow-hidden shadow-2xl relative z-10 max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">User Account Management</h3>
                        <p class="text-[9px] text-slate-500 font-mono mt-0.5">{{ $selectedUser->uuid }}</p>
                    </div>
                    <button type="button" @click="open = false; $wire.set('showDetailModal', false)" class="text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
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
                                        <button wire:click="openRoleModal('assign')" class="text-[9px] bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-2 py-0.5 rounded transition-colors">Assign</button>
                                        <button wire:click="openRoleModal('revoke')" class="text-[9px] bg-red-950 border border-red-900 text-red-400 font-bold px-2 py-0.5 rounded transition-colors">Revoke</button>
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
                            <button wire:click="openSuspendModal" class="bg-red-950 hover:bg-red-900 border border-red-900/50 text-red-400 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                                Suspend Account
                            </button>
                        @elseif($selectedUser->status === \App\Shared\Enums\UserStatus::SUSPENDED)
                            <button wire:click="unsuspend" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                                Unsuspend Account
                            </button>
                        @endif
                    </div>
                    <button type="button" @click="open = false; $wire.set('showDetailModal', false)" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                        Close Directory
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Suspend Reason Modal -->
    @if($showSuspendModal)
        <div x-data="{ open: true }" x-show="open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click="open = false; $wire.set('showSuspendModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-red-400 uppercase tracking-wider">Suspend User Account</h3>
                    <button type="button" @click="open = false; $wire.set('showSuspendModal', false)" class="text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
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
                        <button type="button" @click="open = false; $wire.set('showSuspendModal', false)" 
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-red-600 hover:bg-red-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                            Suspend User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Role Edit Modal -->
    @if($showRoleModal)
        <div x-data="{ open: true }" x-show="open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click="open = false; $wire.set('showRoleModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider capitalize">{{ $roleAction }} Role</h3>
                    <button type="button" @click="open = false; $wire.set('showRoleModal', false)" class="text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
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
                        <button type="button" @click="open = false; $wire.set('showRoleModal', false)" 
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                            Confirm {{ ucfirst($roleAction) }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
