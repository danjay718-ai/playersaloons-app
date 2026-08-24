<div x-data="{ 
    activeTab: '{{ $activeTab }}',
    createModal: { open: false, mode: 'player', username: '', email: '', displayName: '', countryCode: 'PH', role: '', password: '', passwordConfirmation: '', showPassword: false, showPasswordConfirmation: false },
    editModal: { open: false, id: null, username: '', email: '', displayName: '', countryCode: '' },
    passwordModal: { open: false, id: null, username: '', password: '', passwordConfirmation: '', showPassword: false, showPasswordConfirmation: false },
    deleteModal: { open: false, id: null, username: '' },
    suspendModal: { open: false, id: null, username: '', reason: '' },
    roleModal: { open: false, id: null, username: '', action: 'assign', role: '' },
    transferModal: { open: false, id: null, username: '', confirm: '' },

    openCreate(mode) {
        this.createModal = { open: true, mode: mode, username: '', email: '', displayName: '', countryCode: 'PH', role: '', password: '', passwordConfirmation: '', showPassword: false, showPasswordConfirmation: false };
    },
    openEdit(id, username, email, displayName, countryCode) {
        this.editModal = { open: true, id: id, username: username, email: email, displayName: displayName, countryCode: countryCode };
    },
    openPassword(id, username) {
        this.passwordModal = { open: true, id: id, username: username, password: '', passwordConfirmation: '', showPassword: false, showPasswordConfirmation: false };
    },
    openDelete(id, username) {
        this.deleteModal = { open: true, id: id, username: username };
    },
    openSuspend(id, username) {
        this.suspendModal = { open: true, id: id, username: username, reason: '' };
    },
    openRole(id, username, action) {
        this.roleModal = { open: true, id: id, username: username, action: action, role: '' };
    },
    openTransfer(id, username) {
        this.transferModal = { open: true, id: id, username: username, confirm: '' };
    },
    closeAll() {
        this.createModal.open = false;
        this.editModal.open = false;
        this.passwordModal.open = false;
        this.deleteModal.open = false;
        this.suspendModal.open = false;
        this.roleModal.open = false;
        this.transferModal.open = false;
    }
}"
x-on:open-create.window="openCreate($event.detail?.mode)"
x-on:open-edit.window="openEdit($event.detail.id, $event.detail.username, $event.detail.email, $event.detail.displayName, $event.detail.countryCode)"
x-on:open-password.window="openPassword($event.detail.id, $event.detail.username)"
x-on:open-delete.window="openDelete($event.detail.id, $event.detail.username)"
x-on:open-suspend.window="openSuspend($event.detail.id, $event.detail.username)"
x-on:open-role.window="openRole($event.detail.id, $event.detail.username, $event.detail.action)"
x-on:open-transfer.window="openTransfer($event.detail.id, $event.detail.username)"
x-on:user-created.window="createModal.open = false"
x-on:user-updated.window="editModal.open = false"
x-on:password-reset.window="passwordModal.open = false"
x-on:user-deleted.window="deleteModal.open = false"
x-on:user-suspended.window="suspendModal.open = false"
x-on:user-role-updated.window="roleModal.open = false"
x-on:super-admin-transferred.window="transferModal.open = false"
x-on:keydown.escape.window="closeAll()">
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
                <button type="button" @click="openCreate('player')" class="flex-1 rounded-lg border border-indigo-500/40 bg-indigo-500/10 px-4 py-2 text-xs font-bold uppercase tracking-wider text-indigo-300 transition hover:bg-indigo-500/20 sm:flex-none">
                    + Add Player
                </button>
                <button type="button" @click="openCreate('user')" class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-indigo-500 sm:flex-none">
                    + Add User
                </button>
            </div>
        @endcan
    </div>

    <!-- Feedback Alerts -->
    @if(session()->has('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Users Table -->
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl shadow-sm mb-6">
        <div class="overflow-x-auto min-h-[350px]">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                        <th class="p-4">User</th>
                        <th class="p-4">Display Name</th>
                        <th class="p-4">Country</th>
                        <th class="p-4">Assigned Roles</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($users as $usr)
                        @php
                            $isNearBottom = $loop->iteration > ($loop->count - 3) && $loop->count > 2;
                        @endphp
                        <tr wire:key="user-row-{{ $usr->id }}" class="hover:bg-slate-800/30 transition-colors">
                            <td class="p-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-slate-300 relative">
                                        {{ strtoupper(substr($usr->username, 0, 2)) }}
                                        @if($usr->is_online)
                                            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-500 border-2 border-slate-900" title="Online"></span>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-200 flex items-center space-x-1.5">
                                            <span>{{ $usr->username }}</span>
                                            @if($usr->hasRole('SUPER_ADMIN'))
                                                <span class="bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-[8px] font-extrabold px-1 rounded uppercase tracking-wider">Super Admin</span>
                                            @endif
                                        </div>
                                        <div class="text-[10px] text-slate-500">{{ $usr->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-slate-300">
                                {{ $usr->profile->display_name ?? 'N/A' }}
                            </td>
                            <td class="p-4 text-slate-400">
                                @php
                                    $cCode = $usr->profile->country_code ?? '';
                                    $cName = $eligibleCountries[$cCode] ?? $cCode;
                                @endphp
                                <span class="font-medium text-slate-300">{{ $cName ? $cName : 'N/A' }}</span>
                                @if($cCode)
                                    <span class="text-[10px] text-slate-500 font-mono">({{ $cCode }})</span>
                                @endif
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
                                        'active' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                        'suspended' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                        'banned' => 'bg-slate-800 text-slate-500 border-slate-700',
                                    ];
                                    $col = $statusColors[$usr->status->value] ?? 'bg-slate-800 text-slate-400 border-slate-700';
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold uppercase {{ $col }}">
                                    {{ $usr->status->value }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <div x-data="{ open: false }" class="relative inline-block text-left" @click.away="open = false">
                                    <button @click="open = !open" type="button" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-750 border border-slate-700 text-slate-200 font-bold rounded-lg text-[10px] uppercase tracking-wider flex items-center space-x-1 ml-auto transition-colors">
                                        <span>Actions</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    <div x-cloak x-show="open" 
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="transform opacity-0 scale-95"
                                         x-transition:enter-end="transform opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="transform opacity-100 scale-100"
                                         x-transition:leave-end="transform opacity-0 scale-95"
                                         style="display: none;" 
                                         class="absolute right-0 {{ $isNearBottom ? 'bottom-full mb-2 origin-bottom-right' : 'top-full mt-2 origin-top-right' }} w-48 bg-[#0b0f19] rounded-lg shadow-2xl z-50 border border-slate-700 py-1 overflow-hidden">
                                        @can('update', $usr)
                                            <button type="button" @click.stop="$dispatch('open-edit', { id: {{ $usr->id }}, username: @js($usr->username), email: @js($usr->email), displayName: @js($usr->profile->display_name ?? ''), countryCode: @js($usr->profile->country_code ?? '') }); open = false" class="block w-full text-left px-4 py-2.5 text-xs text-slate-300 hover:bg-slate-800 hover:text-white uppercase font-bold tracking-wider transition-colors">
                                                Edit Data
                                            </button>
                                        @endcan
                                        @can('resetPassword', $usr)
                                            <button type="button" @click.stop="$dispatch('open-password', { id: {{ $usr->id }}, username: @js($usr->username) }); open = false" class="block w-full text-left px-4 py-2.5 text-xs text-slate-300 hover:bg-slate-800 hover:text-white uppercase font-bold tracking-wider transition-colors">
                                                Reset Password
                                            </button>
                                        @endcan
                                        @can('delete', $usr)
                                            @if(! $usr->hasRole('SUPER_ADMIN') && $usr->id !== auth()->id())
                                                <button type="button" @click.stop="$dispatch('open-delete', { id: {{ $usr->id }}, username: @js($usr->username) }); open = false" class="block w-full text-left px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-red-400 transition-colors hover:bg-red-950/40 hover:text-red-300">
                                                    Delete User
                                                </button>
                                            @endif
                                        @endcan
                                        @if(auth()->user()?->hasRole('SUPER_ADMIN') && $usr->id !== auth()->id() && ! $usr->hasRole('SUPER_ADMIN'))
                                            <button type="button" @click.stop="$dispatch('open-transfer', { id: {{ $usr->id }}, username: @js($usr->username) }); open = false" class="block w-full text-left px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-amber-400 transition-colors hover:bg-amber-950/40 hover:text-amber-300">
                                                Transfer Super Admin
                                            </button>
                                        @endif
                                        <div class="border-t border-slate-800 my-0.5"></div>
                                        <button type="button" @click.stop="$wire.selectUser({{ $usr->id }}); open = false" class="block w-full text-left px-4 py-2.5 text-xs text-indigo-400 hover:bg-slate-800 hover:text-indigo-300 uppercase font-bold tracking-wider transition-colors">
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


    <!-- Pagination -->
    <div class="mt-4">
        {{ $users->links('vendor.livewire.custom-pagination') }}
    </div>

    <!-- Create User / Player Modal -->
    <div x-cloak x-show="createModal.open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click.self="createModal.open = false"></div>
        <div class="relative z-10 w-full max-w-md overflow-hidden rounded-xl border border-slate-800 bg-[#0f172a] shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 bg-[#0b0f19] px-6 py-4">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-200">
                    Add <span x-text="createModal.mode === 'player' ? 'Player' : 'User'"></span>
                </h3>
                <button type="button" @click="createModal.open = false" class="text-slate-400 hover:text-white">✕</button>
            </div>
            <form @submit.prevent="$wire.createUser(createModal.mode, createModal.username, createModal.email, createModal.displayName, createModal.countryCode, createModal.role, createModal.password, createModal.passwordConfirmation)" class="space-y-4 p-6">
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-400">Username</label>
                    <input x-model="createModal.username" type="text" placeholder="username" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                    @error('createUsername')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-400">Email</label>
                    <input x-model="createModal.email" type="email" placeholder="user@example.com" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                    @error('createEmail')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-400">Display Name</label>
                    <input x-model="createModal.displayName" type="text" placeholder="Display Name" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                    @error('createDisplayName')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-400">Country</label>
                    <select x-model="createModal.countryCode" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        <option value="">Select country</option>
                        @foreach($eligibleCountries as $code => $name)
                            <option value="{{ $code }}">{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </select>
                    @error('createCountryCode')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                </div>
                <div x-show="createModal.mode === 'user'">
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-400">Role</label>
                    <select x-model="createModal.role" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        <option value="">Select role</option>
                        @foreach($roles->where('name', '!=', 'PLAYER') as $role)
                            @if($role->name !== 'SUPER_ADMIN')
                                <option value="{{ $role->name }}">{{ $role->name }}</option>
                            @endif
                        @endforeach
                    </select>
                    @error('createRole')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-400">Password</label>
                    <div class="relative">
                        <input x-model="createModal.password" :type="createModal.showPassword ? 'text' : 'password'" placeholder="••••••••" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 pr-10 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        <button type="button" @click="createModal.showPassword = !createModal.showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300 focus:outline-none transition-colors" tabindex="-1" :title="createModal.showPassword ? 'Hide password' : 'Show password'">
                            <svg x-show="!createModal.showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="createModal.showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1">Min. 8 characters with letters, mixed case, and numbers.</p>
                    @error('createPassword')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-400">Confirm Password</label>
                    <div class="relative">
                        <input x-model="createModal.passwordConfirmation" :type="createModal.showPasswordConfirmation ? 'text' : 'password'" placeholder="••••••••" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 pr-10 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        <button type="button" @click="createModal.showPasswordConfirmation = !createModal.showPasswordConfirmation" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300 focus:outline-none transition-colors" tabindex="-1" :title="createModal.showPasswordConfirmation ? 'Hide password' : 'Show password'">
                            <svg x-show="!createModal.showPasswordConfirmation" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="createModal.showPasswordConfirmation" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                    @error('createPasswordConfirmation')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                    <button type="button" @click="createModal.open = false" class="rounded-lg bg-slate-800 px-4 py-2.5 text-xs font-bold uppercase text-slate-200 hover:bg-slate-700 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold uppercase text-white hover:bg-indigo-500 transition-colors flex items-center gap-2">
                        <span wire:loading wire:target="createUser" class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                        <span wire:loading.remove wire:target="createUser">Create Account</span>
                        <span wire:loading wire:target="createUser">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Data Modal -->
    <div x-cloak x-show="editModal.open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click.self="editModal.open = false"></div>
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
            <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Edit User Data</h3>
                    <p class="text-[10px] text-slate-400 font-mono mt-0.5" x-text="'User: ' + editModal.username"></p>
                </div>
                <button type="button" @click="editModal.open = false" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form @submit.prevent="$wire.updateUser(editModal.id, editModal.username, editModal.email, editModal.displayName, editModal.countryCode)" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Username</label>
                    <input type="text" x-model="editModal.username" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                    @error('editUsername') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Email</label>
                    <input type="email" x-model="editModal.email" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                    @error('editEmail') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Display Name</label>
                    <input type="text" x-model="editModal.displayName" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                    @error('editDisplayName') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Country</label>
                    <select x-model="editModal.countryCode" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        <option value="">Select country</option>
                        @foreach($eligibleCountries as $code => $name)
                            <option value="{{ $code }}">{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </select>
                    @error('editCountryCode') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                    <button type="button" @click="editModal.open = false" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" wire:loading.attr="disabled" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors flex items-center gap-2">
                        <span wire:loading wire:target="updateUser" class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                        <span wire:loading.remove wire:target="updateUser">Save Changes</span>
                        <span wire:loading wire:target="updateUser">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div x-cloak x-show="passwordModal.open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click.self="passwordModal.open = false"></div>
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
            <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Reset User Password</h3>
                    <p class="text-[10px] text-slate-400 font-medium mt-0.5" x-text="passwordModal.username ? 'Reset password for: ' + passwordModal.username : ''"></p>
                </div>
                <button type="button" @click="passwordModal.open = false" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form @submit.prevent="$wire.resetPassword(passwordModal.id, passwordModal.password, passwordModal.passwordConfirmation)" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">New Password</label>
                    <div class="relative">
                        <input :type="passwordModal.showPassword ? 'text' : 'password'" x-model="passwordModal.password" placeholder="••••••••" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 pr-10 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        <button type="button" @click="passwordModal.showPassword = !passwordModal.showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300 focus:outline-none transition-colors" tabindex="-1" :title="passwordModal.showPassword ? 'Hide password' : 'Show password'">
                            <svg x-show="!passwordModal.showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="passwordModal.showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1">Min. 8 characters with uppercase, lowercase, and numbers.</p>
                    @error('newPassword') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Confirm New Password</label>
                    <div class="relative">
                        <input :type="passwordModal.showPasswordConfirmation ? 'text' : 'password'" x-model="passwordModal.passwordConfirmation" placeholder="••••••••" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 pr-10 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        <button type="button" @click="passwordModal.showPasswordConfirmation = !passwordModal.showPasswordConfirmation" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300 focus:outline-none transition-colors" tabindex="-1" :title="passwordModal.showPasswordConfirmation ? 'Hide password' : 'Show password'">
                            <svg x-show="!passwordModal.showPasswordConfirmation" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="passwordModal.showPasswordConfirmation" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                    @error('newPasswordConfirmation') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                    <button type="button" @click="passwordModal.open = false" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" wire:loading.attr="disabled" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors flex items-center gap-2">
                        <span wire:loading wire:target="resetPassword" class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                        <span wire:loading.remove wire:target="resetPassword">Reset Password</span>
                        <span wire:loading wire:target="resetPassword">Resetting...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div x-cloak x-show="deleteModal.open" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click.self="deleteModal.open = false"></div>
        <div class="relative z-10 w-full max-w-md overflow-hidden rounded-xl border border-red-900/60 bg-[#0f172a] shadow-2xl">
            <div class="border-b border-slate-800 bg-[#0b0f19] px-6 py-4 flex justify-between items-center">
                <h3 class="text-sm font-bold uppercase tracking-wider text-red-400">Delete User Account</h3>
                <button type="button" @click="deleteModal.open = false" class="text-slate-400 hover:text-white">✕</button>
            </div>
            <div class="space-y-5 p-6">
                <p class="text-sm leading-relaxed text-slate-300">
                    Are you sure you want to delete user <strong class="text-white font-mono" x-text="deleteModal.username"></strong>?
                    <span class="block mt-2 text-xs text-slate-400">This will soft-delete the user account and revoke normal access. Records and transaction history are retained.</span>
                </p>
                <div class="flex justify-end gap-3 pt-2 border-t border-slate-800">
                    <button type="button" @click="deleteModal.open = false" class="rounded-lg bg-slate-800 px-4 py-2.5 text-xs font-bold uppercase text-slate-200 hover:bg-slate-700 transition-colors">
                        Cancel
                    </button>
                    <button @click="$wire.deleteUser(deleteModal.id)" wire:loading.attr="disabled" class="rounded-lg bg-red-600 px-4 py-2.5 text-xs font-bold uppercase text-white hover:bg-red-500 transition-colors flex items-center gap-2">
                        <span wire:loading wire:target="deleteUser" class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                        <span wire:loading.remove wire:target="deleteUser">Delete Account</span>
                        <span wire:loading wire:target="deleteUser">Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
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
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] text-slate-500 font-bold uppercase block tracking-wider">Profile Details</span>
                                @can('update', $selectedUser)
                                    <button type="button" @click="$dispatch('open-edit', { id: {{ $selectedUser->id }}, username: @js($selectedUser->username), email: @js($selectedUser->email), displayName: @js($selectedUser->profile->display_name ?? ''), countryCode: @js($selectedUser->profile->country_code ?? '') })" class="text-[9px] bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-2 py-0.5 rounded transition-colors">
                                        Edit Data
                                    </button>
                                @endcan
                            </div>
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
                                        <button type="button" @click="$dispatch('open-role', { id: {{ $selectedUser->id }}, username: @js($selectedUser->username), action: 'assign' })" class="text-[9px] bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-2 py-0.5 rounded transition-colors">Assign</button>
                                        <button type="button" @click="$dispatch('open-role', { id: {{ $selectedUser->id }}, username: @js($selectedUser->username), action: 'revoke' })" class="text-[9px] bg-red-950 border border-red-900 text-red-400 font-bold px-2 py-0.5 rounded transition-colors">Revoke</button>
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
                            <button type="button" @click="$dispatch('open-suspend', { id: {{ $selectedUser->id }}, username: @js($selectedUser->username) })" class="bg-red-950 hover:bg-red-900 border border-red-900/50 text-red-400 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                                Suspend Account
                            </button>
                        @elseif($selectedUser->status === \App\Shared\Enums\UserStatus::SUSPENDED)
                            <button wire:click="unsuspend" wire:loading.attr="disabled" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
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
    <div x-cloak x-show="suspendModal.open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click.self="suspendModal.open = false"></div>
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
            <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                <h3 class="text-sm font-bold text-red-400 uppercase tracking-wider">
                    Suspend User: <span class="font-mono text-slate-200" x-text="suspendModal.username"></span>
                </h3>
                <button type="button" @click="suspendModal.open = false" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form @submit.prevent="$wire.suspend(suspendModal.id, suspendModal.reason)" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Reason for Suspension</label>
                    <input type="text" x-model="suspendModal.reason" placeholder="e.g. Terms of Service violation - collusion"
                           class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-red-500">
                    @error('suspendReason') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                    <button type="button" @click="suspendModal.open = false" 
                            class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                            class="bg-red-600 hover:bg-red-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors flex items-center gap-2">
                        <span wire:loading wire:target="suspend" class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                        <span wire:loading.remove wire:target="suspend">Suspend User</span>
                        <span wire:loading wire:target="suspend">Suspending...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Role Edit Modal -->
    <div x-cloak x-show="roleModal.open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click.self="roleModal.open = false"></div>
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
            <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider capitalize">
                    <span x-text="roleModal.action === 'assign' ? 'Assign Role to ' : 'Revoke Role from '"></span>
                    <span class="text-indigo-400 font-mono" x-text="roleModal.username"></span>
                </h3>
                <button type="button" @click="roleModal.open = false" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form @submit.prevent="$wire.updateRole(roleModal.id, roleModal.role, roleModal.action)" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Select Role</label>
                    <select x-model="roleModal.role" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-350 focus:outline-none focus:border-indigo-500">
                        <option value="">Select Role</option>
                        @foreach($roles as $role)
                            @if($role->name !== 'SUPER_ADMIN')
                                <option value="{{ $role->name }}">{{ $role->name }}</option>
                            @endif
                        @endforeach
                    </select>
                    @error('selectedRole') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                    <button type="button" @click="roleModal.open = false" 
                            class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                            class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors flex items-center gap-2">
                        <span wire:loading wire:target="updateRole" class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                        <span wire:loading.remove wire:target="updateRole">Confirm Action</span>
                        <span wire:loading wire:target="updateRole">Processing...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transfer Super Admin Modal -->
    <div x-cloak x-show="transferModal.open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click.self="transferModal.open = false"></div>
        <div class="bg-[#0f172a] border border-amber-500/30 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
            <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                <h3 class="text-sm font-bold text-amber-400 uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Transfer Super Admin Ownership
                </h3>
                <button type="button" @click="transferModal.open = false" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form @submit.prevent="$wire.executeTransferSuperAdmin(transferModal.id, transferModal.confirm)" class="p-6 space-y-4">
                <p class="text-xs text-slate-300 leading-relaxed">
                    You are about to transfer the <strong class="text-amber-400">SUPER_ADMIN</strong> role to <strong class="text-white font-mono" x-text="transferModal.username"></strong>.
                    <span class="block mt-1 text-slate-400">There can only be one Super Admin in the system. Your account will automatically become a standard <strong class="text-indigo-400">ADMIN</strong>.</span>
                </p>

                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">
                        Type <span class="text-amber-400 font-mono" x-text="transferModal.username"></span> to confirm:
                    </label>
                    <input type="text" x-model="transferModal.confirm" :placeholder="transferModal.username"
                           class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-amber-500 font-mono">
                    @error('transferConfirmUsername') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                    <button type="button" @click="transferModal.open = false" 
                            class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                            class="bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors flex items-center gap-2">
                        <span wire:loading wire:target="executeTransferSuperAdmin" class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                        <span wire:loading.remove wire:target="executeTransferSuperAdmin">Transfer Ownership</span>
                        <span wire:loading wire:target="executeTransferSuperAdmin">Transferring...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


