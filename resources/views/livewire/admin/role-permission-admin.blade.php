<div x-data="{
    createModal: { open: false, name: '' },
    editModal: { open: false, id: null, name: '' },
    deleteModal: { open: false, id: null, name: '' },
    openCreate() {
        this.createModal = { open: true, name: '' };
    },
    openEdit(id, name) {
        this.editModal = { open: true, id: id, name: name };
    },
    openDelete(id, name) {
        this.deleteModal = { open: true, id: id, name: name };
    },
    closeAll() {
        this.createModal.open = false;
        this.editModal.open = false;
        this.deleteModal.open = false;
    }
}"
x-on:role-created.window="createModal.open = false"
x-on:role-updated.window="editModal.open = false"
x-on:role-deleted.window="deleteModal.open = false"
x-on:keydown.escape.window="closeAll()">

    <!-- Feedback Alerts -->
    @if(session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center justify-between transition-all duration-300">
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>{{ session('success') }}</span>
            </div>
            <button @click="show = false" class="text-emerald-500/70 hover:text-emerald-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
        </div>
    @endif
    @if(session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center justify-between transition-all duration-300">
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>{{ session('error') }}</span>
            </div>
            <button @click="show = false" class="text-red-500/70 hover:text-red-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
        </div>
    @endif

    <div class="flex flex-col md:flex-row gap-6">
        <!-- Left Sidebar: Roles List -->
        <div class="w-full md:w-1/3 xl:w-1/4 flex flex-col space-y-2 relative">
            <div wire:loading wire:target="setActiveRole" class="absolute inset-0 bg-[#090d16]/50 backdrop-blur-[2px] z-20 flex items-center justify-center rounded-xl">
                <svg class="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            </div>
            
            <div class="flex items-center justify-between mb-2 px-1">
                <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-500">Available Roles</h3>
                @if($canManageRoles)<button type="button" @click="openCreate()" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Add Role</span>
                </button>@endif
            </div>
            
            @foreach($roles as $role)
                <div class="group flex items-center justify-between w-full px-4 py-3.5 rounded-xl border transition-all duration-200 cursor-pointer {{ $activeRoleId === $role->id ? 'bg-indigo-600/10 border-indigo-500/50 shadow-[0_0_15px_rgba(79,70,229,0.1)]' : 'bg-slate-900 border-slate-800 hover:border-slate-700 hover:bg-slate-800' }}"
                     wire:click="setActiveRole({{ $role->id }})">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors {{ $activeRoleId === $role->id ? 'bg-indigo-500/20 text-indigo-400' : 'bg-slate-800 text-slate-500 group-hover:text-slate-400' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                @if($role->name === 'SUPER_ADMIN')
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                @endif
                            </svg>
                        </div>
                        <div class="truncate min-w-0">
                            <h3 class="text-xs font-extrabold uppercase tracking-widest truncate transition-colors {{ $activeRoleId === $role->id ? 'text-indigo-400' : 'text-slate-300 group-hover:text-slate-200' }}">{{ $role->name }}</h3>
                            <p class="text-[10px] text-slate-500 mt-0.5 font-medium">{{ $role->name === 'SUPER_ADMIN' ? 'All' : $role->permissions->count() }} permissions</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0 ml-2" @click.stop>
                        @if($canManageRoles && !in_array($role->name, \App\Livewire\Admin\RolePermissionAdmin::PROTECTED_ROLES, true))
                            <button type="button" @click.stop="openEdit({{ $role->id }}, @js($role->name))" title="Rename Role" class="p-1 text-slate-500 hover:text-indigo-400 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button type="button" @click.stop="openDelete({{ $role->id }}, @js($role->name))" title="Delete Role" class="p-1 text-slate-500 hover:text-red-400 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Right Panel: Permissions -->
        <div class="w-full md:w-2/3 xl:w-3/4">
            @php
                $activeRole = $roles->firstWhere('id', $activeRoleId);
            @endphp

            @if($activeRole)
                <div class="bg-[#0f172a] border border-slate-800 rounded-xl shadow-lg flex flex-col h-full overflow-hidden">
                    
                    <!-- Header -->
                    <div class="px-6 py-5 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center relative overflow-hidden">
                        <div class="relative z-10">
                            <h2 class="text-lg font-black text-slate-200 uppercase tracking-wider flex items-center gap-2">
                                {{ $activeRole->name }} <span class="text-slate-500 font-bold lowercase text-sm tracking-normal">permissions matrix</span>
                            </h2>
                            <p class="text-[11px] text-slate-400 mt-1 font-medium">Toggle access privileges. Changes are saved and applied automatically.</p>
                        </div>
                        @if($activeRole->name === 'SUPER_ADMIN')
                            <div class="relative z-10 px-3 py-1.5 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-md text-[10px] font-black uppercase tracking-widest flex items-center shadow-[0_0_10px_rgba(79,70,229,0.2)]">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 mr-2 animate-pulse"></span>
                                God Mode Enabled
                            </div>
                        @endif
                    </div>

                    <!-- Permissions Area -->
                    <div class="p-6 relative">
                        <!-- Loading overlay for UX during toggle -->
                        <div wire:loading wire:target="togglePermission" class="absolute inset-0 bg-[#0f172a]/60 backdrop-blur-[1px] z-20 flex items-center justify-center transition-all duration-200">
                            <div class="bg-[#0b0f19] border border-slate-800 px-4 py-3 rounded-xl shadow-xl flex items-center gap-3">
                                <svg class="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">Syncing...</span>
                            </div>
                        </div>

                        @if($activeRole->name === 'SUPER_ADMIN')
                            <!-- Super Admin Placeholder -->
                            <div class="flex flex-col items-center justify-center py-20 text-center">
                                <div class="w-20 h-20 bg-indigo-500/5 border border-indigo-500/20 rounded-2xl flex items-center justify-center mb-6 shadow-[0_0_30px_rgba(79,70,229,0.1)] relative">
                                    <div class="absolute inset-0 bg-indigo-500/20 blur-xl rounded-full"></div>
                                    <svg class="w-10 h-10 text-indigo-400 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </div>
                                <h3 class="text-slate-200 font-black text-xl mb-3 tracking-wide">Unrestricted Access</h3>
                                <p class="text-slate-400 text-sm max-w-md leading-relaxed font-medium">The <span class="text-indigo-400 font-bold">SUPER_ADMIN</span> role inherently bypasses all standard permission checks. Individual toggles are unnecessary and disabled for security.</p>
                            </div>
                        @else
                            <!-- Grouped Toggles -->
                            <div class="space-y-8">
                                @foreach($groupedPermissions as $groupName => $permissions)
                                    <div>
                                        <h4 class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-4 flex items-center">
                                            {{ $groupName }}
                                            <div class="ml-4 h-px bg-slate-800 flex-grow"></div>
                                        </h4>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                            @foreach($permissions as $perm)
                                                @php
                                                    $hasPerm = $activeRole->permissions->contains('name', $perm->name);
                                                @endphp
                                                <div @if($canManageRoles) wire:click="togglePermission('{{ $perm->name }}')" @endif
                                                     class="group flex items-center justify-between p-3.5 rounded-xl border transition-all duration-200 select-none {{ $canManageRoles ? 'cursor-pointer' : 'cursor-not-allowed opacity-70' }}
                                                     {{ $hasPerm ? 'bg-indigo-600/10 border-indigo-500/30 shadow-[0_0_10px_rgba(79,70,229,0.05)] hover:bg-indigo-600/15' : 'bg-slate-900/50 border-slate-800 hover:border-slate-700 hover:bg-slate-800/80' }}">
                                                    
                                                    <div class="flex flex-col mr-3 truncate">
                                                        <span class="text-[11px] font-bold font-mono truncate transition-colors {{ $hasPerm ? 'text-indigo-300' : 'text-slate-400 group-hover:text-slate-300' }}">
                                                            {{ $perm->name }}
                                                        </span>
                                                    </div>

                                                    <!-- Modern Toggle Switch -->
                                                    <div class="relative inline-flex items-center shrink-0">
                                                        <div class="w-8 h-4.5 rounded-full transition-colors duration-300 ease-in-out {{ $hasPerm ? 'bg-indigo-500' : 'bg-slate-700 group-hover:bg-slate-600' }}"></div>
                                                        <div class="absolute left-0.5 top-0.5 bg-white w-3.5 h-3.5 rounded-full transition-transform duration-300 ease-in-out {{ $hasPerm ? 'translate-x-3.5 shadow-[0_0_5px_rgba(255,255,255,0.5)]' : 'translate-x-0' }}"></div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Create Role Modal (Instant Alpine) -->
    <div x-cloak x-show="createModal.open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click.self="createModal.open = false"></div>
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
            <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Create New Role</h3>
                <button type="button" @click="createModal.open = false" class="text-slate-400 hover:text-white">✕</button>
            </div>

            <form @submit.prevent="$wire.createRole(createModal.name)" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Role Name</label>
                    <input type="text" x-model="createModal.name" placeholder="e.g. TOURNAMENT_REFEREE"
                           class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 uppercase font-mono focus:outline-none focus:border-indigo-500">
                    @error('newRoleName') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                    <button type="button" @click="createModal.open = false" 
                            class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                            class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors flex items-center gap-2">
                        <span wire:loading wire:target="createRole" class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                        <span wire:loading.remove wire:target="createRole">Create Role</span>
                        <span wire:loading wire:target="createRole">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Role Name Modal (Instant Alpine) -->
    <div x-cloak x-show="editModal.open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click.self="editModal.open = false"></div>
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
            <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Rename Role</h3>
                <button type="button" @click="editModal.open = false" class="text-slate-400 hover:text-white">✕</button>
            </div>

            <form @submit.prevent="$wire.updateRoleName(editModal.id, editModal.name)" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">New Role Name</label>
                    <input type="text" x-model="editModal.name" placeholder="e.g. SENIOR_ORGANIZER"
                           class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 uppercase font-mono focus:outline-none focus:border-indigo-500">
                    @error('editRoleName') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                    <button type="button" @click="editModal.open = false" 
                            class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                            class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors flex items-center gap-2">
                        <span wire:loading wire:target="updateRoleName" class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                        <span wire:loading.remove wire:target="updateRoleName">Save Name</span>
                        <span wire:loading wire:target="updateRoleName">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Role Modal (Instant Alpine) -->
    <div x-cloak x-show="deleteModal.open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click.self="deleteModal.open = false"></div>
        <div class="bg-[#0f172a] border border-red-500/30 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
            <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                <h3 class="text-sm font-bold text-red-400 uppercase tracking-wider">Delete Role</h3>
                <button type="button" @click="deleteModal.open = false" class="text-slate-400 hover:text-white">✕</button>
            </div>

            <div class="p-6 space-y-4">
                <p class="text-xs text-slate-300 leading-relaxed">
                    Are you sure you want to permanently delete the role <strong class="text-red-400 font-mono" x-text="deleteModal.name"></strong>?
                    <span class="block mt-1 text-slate-400">This action cannot be undone. All assigned permissions for this role will be removed.</span>
                </p>

                <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                    <button type="button" @click="deleteModal.open = false" 
                            class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="button" @click="deleteModal.open = false; $wire.confirmDeleteRole(deleteModal.id)"
                            class="bg-red-600 hover:bg-red-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg transition-colors flex items-center gap-2">
                        <span>Delete Role</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
