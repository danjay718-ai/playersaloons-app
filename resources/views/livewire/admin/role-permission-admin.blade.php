<div>
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
            
            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2 px-1">Available Roles</h3>
            
            @foreach($roles as $role)
                <button wire:click="setActiveRole({{ $role->id }})" 
                        class="group flex items-center justify-between w-full text-left px-4 py-3.5 rounded-xl border transition-all duration-200 {{ $activeRoleId === $role->id ? 'bg-indigo-600/10 border-indigo-500/50 shadow-[0_0_15px_rgba(79,70,229,0.1)]' : 'bg-slate-900 border-slate-800 hover:border-slate-700 hover:bg-slate-800' }}">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors {{ $activeRoleId === $role->id ? 'bg-indigo-500/20 text-indigo-400' : 'bg-slate-800 text-slate-500 group-hover:text-slate-400' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                @if($role->name === 'SUPER_ADMIN')
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                @endif
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xs font-extrabold uppercase tracking-widest transition-colors {{ $activeRoleId === $role->id ? 'text-indigo-400' : 'text-slate-300 group-hover:text-slate-200' }}">{{ $role->name }}</h3>
                            <p class="text-[10px] text-slate-500 mt-0.5 font-medium">{{ $role->name === 'SUPER_ADMIN' ? 'All' : $role->permissions->count() }} permissions assigned</p>
                        </div>
                    </div>
                    <div class="transition-all duration-200 {{ $activeRoleId === $role->id ? 'opacity-100 translate-x-0 text-indigo-400' : 'opacity-0 -translate-x-2 text-slate-600' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </div>
                </button>
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
                                                <div wire:click="togglePermission('{{ $perm->name }}')" 
                                                     class="group flex items-center justify-between p-3.5 rounded-xl border transition-all duration-200 cursor-pointer select-none 
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
</div>
