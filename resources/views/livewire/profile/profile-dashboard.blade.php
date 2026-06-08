<div class="space-y-8 min-w-0">
    <!-- Profile Page Header Banner -->
    <div class="bg-gradient-to-r from-[#170e30] via-[#0e0a24] to-transparent border border-purple-500/20 rounded-2xl p-6 md:p-8 shadow-[0_10px_30px_rgba(0,0,0,0.5),inset_0_0_20px_rgba(168,85,247,0.05)] relative overflow-hidden">
        <!-- Glowing sci-fi elements -->
        <div class="absolute -top-20 -right-20 w-60 h-60 bg-purple-600/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute top-0 right-0 w-24 h-24 border-t-2 border-r-2 border-purple-500/20 rounded-tr-2xl pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-24 h-24 border-b-2 border-l-2 border-purple-500/20 rounded-bl-2xl pointer-events-none"></div>
        
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
            <div class="flex items-center space-x-4">
                <div class="bg-[#120a26] p-4 rounded-2xl border border-purple-500/35 text-purple-400 shadow-[0_0_15px_rgba(168,85,247,0.25)]">
                    <i data-lucide="user" class="w-8 h-8"></i>
                </div>
                <div>
                    <h2 class="text-xl md:text-3xl font-black font-orbitron tracking-wider text-white uppercase filter drop-shadow-[0_0_6px_rgba(168,85,247,0.3)]">
                        {{ $displayName ?: $user->username }}
                    </h2>
                    <p class="text-xs text-zinc-400 mt-1.5 font-medium">
                        Configure your identity protocols, verify status, and manage referral links.
                    </p>
                </div>
            </div>

            <!-- Referral Link Card -->
            <div class="bg-zinc-950/80 border border-purple-500/15 rounded-xl p-4 max-w-sm w-full shadow-[0_0_15px_rgba(0,0,0,0.4)]" x-data="{ copied: false }">
                <span class="block text-[9px] font-bold text-purple-450 uppercase tracking-widest font-orbitron mb-2">YOUR REFERRAL LINK</span>
                <div class="flex items-center space-x-2">
                    <input 
                        type="text" 
                        readonly 
                        value="{{ url('/register?ref=' . $user->id) }}" 
                        class="bg-zinc-900/40 border border-purple-500/20 focus:outline-none rounded-lg px-3 py-2 text-[10px] font-orbitron tracking-wide text-purple-300 w-full"
                        id="referral-link"
                    >
                    <button 
                        type="button" 
                        @click="
                            navigator.clipboard.writeText('{{ url('/register?ref=' . $user->id) }}');
                            copied = true;
                            setTimeout(() => copied = false, 2000);
                        "
                        class="bg-gradient-to-br from-purple-600 to-fuchsia-600 hover:from-purple-500 hover:to-fuchsia-500 border border-fuchsia-400/20 text-white p-2 rounded-lg transition-colors flex items-center justify-center shrink-0 cursor-pointer shadow-[0_0_10px_rgba(217,70,239,0.3)]"
                        title="Copy Link"
                    >
                        <i x-show="!copied" data-lucide="copy" class="w-4 h-4"></i>
                        <i x-show="copied" data-lucide="check" class="w-4 h-4 text-emerald-300" x-cloak></i>
                    </button>
                </div>
                <p x-show="copied" x-transition class="text-[9px] text-emerald-400 font-bold mt-1.5 uppercase font-orbitron tracking-wider" x-cloak>
                    Link Copied to Clipboard!
                </p>
            </div>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if (session()->has('message'))
        <div class="bg-emerald-950/20 border border-emerald-900/40 text-emerald-400 rounded-xl p-4 text-xs flex items-center space-x-2 shadow-[0_0_10px_rgba(16,185,129,0.1)]">
            <i data-lucide="check-circle" class="w-4.5 h-4.5 text-emerald-450 shrink-0"></i>
            <span class="font-medium">{{ session('message') }}</span>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-950/20 border border-red-900/40 text-red-400 rounded-xl p-4 text-xs flex items-center space-x-2 shadow-[0_0_10px_rgba(244,63,94,0.1)]">
            <i data-lucide="alert-triangle" class="w-4.5 h-4.5 text-red-450 shrink-0"></i>
            <span class="font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Profile Settings Form -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 shadow-xl space-y-6">
                <div class="border-b border-purple-500/10 pb-3 flex items-center space-x-2">
                    <i data-lucide="user-cog" class="w-4.5 h-4.5 text-purple-400"></i>
                    <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase">
                        PROFILE CONFIGURATION
                    </h3>
                </div>

                <form wire:submit="updateProfile" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Display Name -->
                        <div class="space-y-1.5">
                            <label for="displayName" class="block text-[9px] font-bold text-zinc-500 uppercase tracking-wider font-orbitron">Display Name</label>
                            <input 
                                type="text" 
                                id="displayName" 
                                wire:model="displayName" 
                                class="bg-zinc-950 border border-purple-500/20 hover:border-purple-500/40 focus:border-purple-500 focus:outline-none rounded-xl px-4 py-2.5 text-xs font-semibold text-purple-300 w-full"
                                placeholder="Your gaming tag"
                            >
                            @error('displayName') <span class="text-[10px] text-red-400 font-bold font-mono">{{ $message }}</span> @enderror
                        </div>

                        <!-- Country Code -->
                        <div class="space-y-1.5">
                            <label for="countryCode" class="block text-[9px] font-bold text-zinc-500 uppercase tracking-wider font-orbitron">Country Code (e.g. PH, US)</label>
                            <input 
                                type="text" 
                                id="countryCode" 
                                wire:model="countryCode" 
                                maxlength="2"
                                class="bg-zinc-950 border border-purple-500/20 hover:border-purple-500/40 focus:border-purple-500 focus:outline-none rounded-xl px-4 py-2.5 text-xs font-semibold text-purple-300 w-full uppercase"
                                placeholder="PH"
                            >
                            @error('countryCode') <span class="text-[10px] text-red-400 font-bold font-mono">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Timezone -->
                    <div class="space-y-1.5">
                        <label for="timezone" class="block text-[9px] font-bold text-zinc-500 uppercase tracking-wider font-orbitron">Timezone Protocol</label>
                        <select 
                            id="timezone" 
                            wire:model="timezone" 
                            class="bg-zinc-950 border border-purple-500/20 hover:border-purple-500/40 focus:border-purple-500 focus:outline-none rounded-xl px-4 py-2.5 text-xs font-semibold text-purple-300 w-full"
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
                        <label for="bio" class="block text-[9px] font-bold text-zinc-500 uppercase tracking-wider font-orbitron">Player Bio / Intel</label>
                        <textarea 
                            id="bio" 
                            wire:model="bio" 
                            rows="4"
                            class="bg-zinc-950 border border-purple-500/20 hover:border-purple-500/40 focus:border-purple-500 focus:outline-none rounded-xl px-4 py-2.5 text-xs font-semibold text-purple-300 w-full resize-none"
                            placeholder="Tell other saloons soldiers about yourself..."
                        ></textarea>
                        @error('bio') <span class="text-[10px] text-red-400 font-bold font-mono">{{ $message }}</span> @enderror
                    </div>

                    <button 
                        type="submit" 
                        class="bg-gradient-to-r from-purple-600 to-fuchsia-600 hover:from-purple-500 hover:to-fuchsia-500 text-white font-bold py-2.5 px-6 rounded-xl border border-fuchsia-400/20 text-xs uppercase tracking-widest font-orbitron cursor-pointer shadow-[0_0_15px_rgba(217,70,239,0.35)] transition-all"
                    >
                        Save Configuration
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Column: KYC & Notifications -->
        <div class="space-y-6">
            <!-- Identity verification (KYC) -->
            <div class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 shadow-xl space-y-6">
                <div class="border-b border-purple-500/10 pb-3 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="shield-check" class="w-4.5 h-4.5 text-purple-400"></i>
                        <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase">
                            IDENTITY SEAL
                        </h3>
                    </div>

                    @php
                        $kycStatus = $latestKyc ? $latestKyc->status->value : 'not_submitted';
                        $badgeColors = [
                            'not_submitted' => 'bg-zinc-950/60 text-zinc-500 border-purple-500/5 shadow-none',
                            'submitted' => 'bg-purple-950/40 text-purple-400 border-purple-900/60 shadow-[0_0_8px_rgba(168,85,247,0.15)]',
                            'under_review' => 'bg-amber-950/40 text-amber-400 border-amber-900/60 shadow-[0_0_8px_rgba(245,158,11,0.15)]',
                            'approved' => 'bg-emerald-950/40 text-emerald-400 border-emerald-900/60 shadow-[0_0_8px_rgba(16,185,129,0.15)]',
                            'rejected' => 'bg-red-950/40 text-red-400 border-red-900/60 shadow-[0_0_8px_rgba(244,63,94,0.15)]',
                        ];
                        $statusBadgeColor = $badgeColors[$kycStatus] ?? $badgeColors['not_submitted'];
                    @endphp

                    <span class="text-[8px] font-bold uppercase tracking-widest border rounded-md px-2.5 py-1 font-orbitron {{ $statusBadgeColor }}">
                        {{ str_replace('_', ' ', $kycStatus) }}
                    </span>
                </div>

                @if($kycStatus === 'approved')
                    <div class="bg-emerald-950/20 border border-emerald-900/40 text-emerald-400/90 rounded-xl p-4 text-xs space-y-2">
                        <p class="font-bold font-orbitron flex items-center gap-1.5 uppercase text-[10px] tracking-wider text-emerald-400">
                            <i data-lucide="check" class="w-4 h-4"></i> Identity Verified
                        </p>
                        <p class="leading-relaxed text-[11px]">Your identity has been fully verified. You can now withdraw funds from your wallet and participate in real-money tournaments.</p>
                    </div>
                @elseif($kycStatus === 'submitted' || $kycStatus === 'under_review')
                    <div class="bg-purple-950/20 border border-purple-900/40 text-purple-300 rounded-xl p-4 text-xs space-y-2">
                        <p class="font-bold font-orbitron flex items-center gap-1.5 uppercase text-[10px] tracking-wider text-purple-300">
                            <i data-lucide="clock" class="w-4 h-4 text-fuchsia-400"></i> Under Review
                        </p>
                        <p class="leading-relaxed text-[11px]">We are reviewing your submission. This typically takes 24-48 hours. Withdrawal actions will be enabled once approved.</p>
                    </div>
                @else
                    <!-- KYC Submit Form -->
                    @if($kycStatus === 'rejected')
                        <div class="bg-red-950/20 border border-red-900/40 text-red-400/90 rounded-xl p-4 text-xs space-y-2">
                            <p class="font-bold font-orbitron flex items-center gap-1.5 uppercase text-[10px] tracking-wider text-red-400">
                                <i data-lucide="x" class="w-4 h-4"></i> KYC Rejected
                            </p>
                            @if($latestKyc && $latestKyc->review_notes)
                                <p class="italic text-[11px]">Notes: "{{ $latestKyc->review_notes }}"</p>
                            @endif
                            <p class="leading-relaxed text-[11px]">Please review your information and submit a new document.</p>
                        </div>
                    @endif

                    <form wire:submit="submitKyc" class="space-y-4">
                        <div class="space-y-1.5">
                            <label for="documentType" class="block text-[9px] font-bold text-zinc-500 uppercase tracking-wider font-orbitron">Document Protocol</label>
                            <select 
                                id="documentType" 
                                wire:model="documentType" 
                                class="bg-zinc-950 border border-purple-500/20 hover:border-purple-500/40 focus:border-purple-500 focus:outline-none rounded-xl px-4 py-2.5 text-xs font-semibold text-purple-300 w-full"
                            >
                                <option value="id_card">ID CARD / NATIONAL ID</option>
                                <option value="passport">PASSPORT</option>
                                <option value="drivers_license">DRIVER'S LICENSE</option>
                            </select>
                            @error('documentType') <span class="text-[10px] text-red-400 font-bold font-mono">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="kycFile" class="block text-[9px] font-bold text-zinc-500 uppercase tracking-wider font-orbitron">Upload File</label>
                            <input 
                                type="file" 
                                id="kycFile" 
                                wire:model="kycFile" 
                                class="bg-zinc-950 border border-purple-500/20 rounded-xl text-xs text-purple-400 w-full focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-l-xl file:border-0 file:text-[9px] file:font-bold file:uppercase file:bg-purple-950 file:text-purple-300 hover:file:bg-purple-900 file:cursor-pointer"
                            >
                            <div class="text-[9px] text-zinc-650 font-mono mt-1">SUPPORTED: PNG, JPG, JPEG, PDF (MAX 10MB)</div>
                            @error('kycFile') <span class="text-[10px] text-red-400 font-bold font-mono">{{ $message }}</span> @enderror
                        </div>

                        <!-- Progress indicator -->
                        <div wire:loading wire:target="kycFile" class="text-[9px] text-purple-450 italic font-orbitron tracking-widest animate-pulse">
                            UPLOADING PROTOCOL ENVELOPE...
                        </div>

                        <button 
                            type="submit" 
                            wire:loading.attr="disabled"
                            class="w-full bg-gradient-to-r from-fuchsia-600 to-violet-600 hover:from-fuchsia-500 hover:to-violet-500 text-white font-bold py-2.5 rounded-xl border border-fuchsia-400/20 text-xs uppercase tracking-widest font-orbitron cursor-pointer shadow-[0_0_15px_rgba(217,70,239,0.35)] transition-all"
                        >
                            Submit Verification
                        </button>
                    </form>
                @endif
            </div>

            <!-- Notification preferences -->
            <div class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-5 md:p-6 shadow-xl space-y-6">
                <div class="border-b border-purple-500/10 pb-3 flex items-center space-x-2">
                    <i data-lucide="bell" class="w-4.5 h-4.5 text-purple-400"></i>
                    <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-150 uppercase">
                        COMMS SETTINGS
                    </h3>
                </div>

                <div class="space-y-4">
                    <!-- Email Comms -->
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <span class="block text-xs font-bold text-zinc-200 uppercase font-orbitron">Email Protocols</span>
                            <span class="block text-[10px] text-zinc-500 font-medium">Get match scores and ledger alerts.</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="emailNotifications" wire:change="updatePreferences" class="sr-only peer">
                            <div class="w-10 h-5 bg-zinc-950 border border-purple-500/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2.5px] after:left-[2px] after:bg-zinc-650 after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-purple-600 peer-checked:after:bg-white"></div>
                        </label>
                    </div>

                    <!-- In-App Comms -->
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <span class="block text-xs font-bold text-zinc-200 uppercase font-orbitron">In-App Comms</span>
                            <span class="block text-[10px] text-zinc-550 font-medium">Log notification lists inside topbar.</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="inAppNotifications" wire:change="updatePreferences" class="sr-only peer">
                            <div class="w-10 h-5 bg-zinc-950 border border-purple-500/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2.5px] after:left-[2px] after:bg-zinc-650 after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-purple-600 peer-checked:after:bg-white"></div>
                        </label>
                    </div>

                    <!-- Realtime Comms -->
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <span class="block text-xs font-bold text-zinc-200 uppercase font-orbitron">Realtime Broadcast</span>
                            <span class="block text-[10px] text-zinc-550 font-medium">Enable active duel ping triggers.</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="realtimeNotifications" wire:change="updatePreferences" class="sr-only peer">
                            <div class="w-10 h-5 bg-zinc-950 border border-purple-500/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2.5px] after:left-[2px] after:bg-zinc-650 after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-purple-600 peer-checked:after:bg-white"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
