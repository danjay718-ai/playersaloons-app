<div class="space-y-6 max-w-4xl">
    <!-- Profile Page Header -->
    <div class="bg-gradient-to-r from-indigo-950/40 via-slate-900/60 to-transparent border border-slate-800 rounded-xl p-6 md:p-8 relative overflow-hidden">
        <div class="absolute -top-20 -right-20 w-60 h-60 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>
        
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
            <div class="flex items-center space-x-4">
                <div class="bg-indigo-600/15 p-4 rounded-xl border border-indigo-500/30 text-indigo-400 shadow-[0_0_15px_rgba(99,102,241,0.15)]">
                    <i data-lucide="user" class="w-8 h-8"></i>
                </div>
                <div>
                    <h2 class="text-xl md:text-2xl font-bold tracking-wide text-slate-100 uppercase">
                        {{ $displayName ?: $user->username }}
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Manage your staff profile details, display name, and localization settings.
                    </p>
                </div>
            </div>
            
            @php
                $adminRole = auth()->user()?->roles?->pluck('name')?->first() ?? 'Staff';
                $roleColors = [
                    'SUPER_ADMIN'         => 'bg-red-900/40 text-red-300 border-red-700/30',
                    'ADMIN'               => 'bg-indigo-900/40 text-indigo-300 border-indigo-700/30',
                    'MODERATOR'           => 'bg-purple-900/40 text-purple-300 border-purple-700/30',
                    'FINANCE_OPERATOR'    => 'bg-emerald-900/40 text-emerald-300 border-emerald-700/30',
                    'KYC_REVIEWER'        => 'bg-sky-900/40 text-sky-300 border-sky-700/30',
                    'SUPPORT_AGENT'       => 'bg-amber-900/40 text-amber-300 border-amber-700/30',
                    'TOURNAMENT_ORGANIZER'=> 'bg-orange-900/40 text-orange-300 border-orange-700/30',
                ];
                $roleClass = $roleColors[$adminRole] ?? 'bg-slate-800 text-slate-300 border-slate-700/30';
            @endphp
            <span class="text-[10px] font-bold uppercase tracking-wider {{ $roleClass }} inline-flex items-center gap-1 border rounded-full px-3 py-1.5 self-start md:self-auto">
                <i data-lucide="shield" class="w-3.5 h-3.5"></i>
                {{ str_replace('_', ' ', $adminRole) }}
            </span>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if (session()->has('message'))
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg text-sm flex items-center shadow-sm">
            <i data-lucide="check-circle" class="w-4 h-4 mr-2 shrink-0"></i>
            <span class="font-medium">{{ session('message') }}</span>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg text-sm flex items-center shadow-sm">
            <i data-lucide="alert-circle" class="w-4 h-4 mr-2 shrink-0"></i>
            <span class="font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6">
        <!-- Profile Settings Form -->
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-6 shadow-sm">
            <div class="border-b border-slate-800 pb-3 flex items-center space-x-2 mb-6">
                <i data-lucide="user-cog" class="w-4.5 h-4.5 text-indigo-400"></i>
                <h3 class="text-sm font-bold tracking-wider text-slate-200 uppercase">
                    Staff Profile Settings
                </h3>
            </div>

            <form wire:submit="updateProfile" class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Username (Read Only for Admin) -->
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Username</label>
                        <input 
                            type="text" 
                            readonly 
                            value="{{ $user->username }}" 
                            class="bg-slate-950 border border-slate-850 rounded-lg px-4 py-2.5 text-xs font-semibold text-slate-500 w-full cursor-not-allowed"
                            title="Username cannot be changed."
                        >
                    </div>

                    <!-- Email (Read Only for Admin) -->
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Email Address</label>
                        <input 
                            type="text" 
                            readonly 
                            value="{{ $user->email }}" 
                            class="bg-slate-950 border border-slate-850 rounded-lg px-4 py-2.5 text-xs font-semibold text-slate-500 w-full cursor-not-allowed"
                            title="Email address cannot be changed."
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Display Name -->
                    <div class="space-y-1.5">
                        <label for="displayName" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Display Name</label>
                        <input 
                            type="text" 
                            id="displayName" 
                            wire:model="displayName" 
                            class="bg-slate-900 border border-slate-800 hover:border-slate-700 focus:border-indigo-500 focus:outline-none rounded-lg px-4 py-2.5 text-xs font-semibold text-slate-100 w-full"
                            placeholder="Your display name"
                        >
                        @error('displayName') <span class="text-[10px] text-red-400 font-bold font-mono">{{ $message }}</span> @enderror
                    </div>

                    <!-- Country Code -->
                    <div class="space-y-1.5">
                        <label for="countryCode" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Country Code (e.g. PH, US)</label>
                        <input 
                            type="text" 
                            id="countryCode" 
                            wire:model="countryCode" 
                            maxlength="2"
                            class="bg-slate-900 border border-slate-800 hover:border-slate-700 focus:border-indigo-500 focus:outline-none rounded-lg px-4 py-2.5 text-xs font-semibold text-slate-100 w-full uppercase"
                            placeholder="PH"
                        >
                        @error('countryCode') <span class="text-[10px] text-red-400 font-bold font-mono">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Timezone -->
                <div class="space-y-1.5">
                    <label for="timezone" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Timezone Protocol</label>
                    <select 
                        id="timezone" 
                        wire:model="timezone" 
                        class="bg-slate-900 border border-slate-800 hover:border-slate-700 focus:border-indigo-500 focus:outline-none rounded-lg px-4 py-2.5 text-xs font-semibold text-slate-100 w-full"
                    >
                        <option value="">SELECT TIMEZONE ZONE</option>
                        @foreach(timezone_identifiers_list() as $tz)
                            <option value="{{ $tz }}">{{ $tz }}</option>
                        @endforeach
                    </select>
                    @error('timezone') <span class="text-[10px] text-red-400 font-bold font-mono">{{ $message }}</span> @enderror
                </div>

                <!-- Bio -->
                <div class="space-y-1.5">
                    <label for="bio" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Bio / Intel</label>
                    <textarea 
                        id="bio" 
                        wire:model="bio" 
                        rows="4"
                        class="bg-slate-900 border border-slate-800 hover:border-slate-700 focus:border-indigo-500 focus:outline-none rounded-lg px-4 py-2.5 text-xs font-semibold text-slate-100 w-full resize-none"
                        placeholder="Tell staff about yourself..."
                    ></textarea>
                    @error('bio') <span class="text-[10px] text-red-400 font-bold font-mono">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center space-x-3">
                    <button 
                        type="submit" 
                        class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2.5 px-6 rounded-lg text-xs uppercase tracking-widest cursor-pointer shadow-sm transition-all"
                    >
                        Save Profile Settings
                    </button>
                    <a href="/admin" wire:navigate class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold py-2.5 px-6 rounded-lg text-xs uppercase tracking-widest cursor-pointer shadow-sm transition-all">
                        Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
