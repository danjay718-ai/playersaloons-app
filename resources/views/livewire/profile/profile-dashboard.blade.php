<div class="space-y-8">
    <!-- Profile Page Header -->
    <div class="bg-gradient-to-r from-zinc-900 via-zinc-900 to-violet-950/10 border border-zinc-850 rounded-2xl p-6 md:p-8 shadow-xl relative overflow-hidden">
        <div class="absolute -top-20 -right-20 w-60 h-60 bg-violet-600/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center space-x-4">
                <div class="bg-zinc-950 p-4 rounded-2xl border border-zinc-800 text-violet-400">
                    <i data-lucide="user" class="w-10 h-10"></i>
                </div>
                <div>
                    <h1 class="text-2xl md:text-4xl font-black font-orbitron tracking-wider text-white uppercase">
                        {{ $displayName ?: $user->username }}
                    </h1>
                    <p class="text-xs text-zinc-400 mt-1">
                        Manage your account settings, verify your identity, and share your referral link.
                    </p>
                </div>
            </div>

            <!-- Referral Link Card -->
            <div class="bg-zinc-950 border border-zinc-850 rounded-xl p-4 max-w-sm w-full" x-data="{ copied: false }">
                <span class="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-2">Your Referral Link</span>
                <div class="flex items-center space-x-2">
                    <input 
                        type="text" 
                        readonly 
                        value="{{ url('/register?ref=' . $user->id) }}" 
                        class="bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-1.5 text-xs text-zinc-300 w-full focus:outline-none"
                        id="referral-link"
                    >
                    <button 
                        type="button" 
                        @click="
                            navigator.clipboard.writeText('{{ url('/register?ref=' . $user->id) }}');
                            copied = true;
                            setTimeout(() => copied = false, 2000);
                        "
                        class="bg-violet-600 hover:bg-violet-500 text-white p-2 rounded-lg transition-colors flex items-center justify-center shrink-0"
                        title="Copy Link"
                    >
                        <i x-show="!copied" data-lucide="copy" class="w-4 h-4"></i>
                        <i x-show="copied" data-lucide="check" class="w-4 h-4 text-emerald-300" style="display: none;"></i>
                    </button>
                </div>
                <p x-show="copied" x-transition class="text-[10px] text-emerald-400 font-semibold mt-1.5" style="display: none;">
                    Referral link copied to clipboard!
                </p>
            </div>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if (session()->has('message'))
        <div class="bg-emerald-950/40 border border-emerald-800/60 text-emerald-300 rounded-xl p-4 text-sm flex items-center space-x-2">
            <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0"></i>
            <span>{{ session('message') }}</span>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-950/40 border border-red-800/60 text-red-300 rounded-xl p-4 text-sm flex items-center space-x-2">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-red-400 shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Profile Details Form (Left column, spanning 2 grid spaces on large screens) -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 shadow-lg shadow-black/20 space-y-6">
                <div class="border-b border-zinc-850 pb-3 flex items-center space-x-2">
                    <i data-lucide="user-cog" class="w-5 h-5 text-violet-400"></i>
                    <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase">
                        PROFILE SETTINGS
                    </h2>
                </div>

                <form wire:submit="updateProfile" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Display Name -->
                        <div class="space-y-1.5">
                            <label for="displayName" class="block text-xs font-bold text-zinc-400 uppercase tracking-wider">Display Name</label>
                            <input 
                                type="text" 
                                id="displayName" 
                                wire:model="displayName" 
                                class="bg-zinc-950 border border-zinc-800 focus:border-violet-500 rounded-lg px-4 py-2.5 text-sm text-zinc-100 w-full focus:outline-none transition-colors"
                                placeholder="Your display name"
                            >
                            @error('displayName') <span class="text-xs text-red-400 font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <!-- Country Code (ISO 2-letter) -->
                        <div class="space-y-1.5">
                            <label for="countryCode" class="block text-xs font-bold text-zinc-400 uppercase tracking-wider">Country Code (e.g. US, PH)</label>
                            <input 
                                type="text" 
                                id="countryCode" 
                                wire:model="countryCode" 
                                maxlength="2"
                                class="bg-zinc-950 border border-zinc-800 focus:border-violet-500 rounded-lg px-4 py-2.5 text-sm text-zinc-100 w-full focus:outline-none transition-colors uppercase"
                                placeholder="PH"
                            >
                            @error('countryCode') <span class="text-xs text-red-400 font-semibold">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Timezone -->
                    <div class="space-y-1.5">
                        <label for="timezone" class="block text-xs font-bold text-zinc-400 uppercase tracking-wider">Timezone</label>
                        <select 
                            id="timezone" 
                            wire:model="timezone" 
                            class="bg-zinc-950 border border-zinc-800 focus:border-violet-500 rounded-lg px-4 py-2.5 text-sm text-zinc-100 w-full focus:outline-none transition-colors"
                        >
                            <option value="">Select Timezone</option>
                            @foreach(timezone_identifiers_list() as $tz)
                                <option value="{{ $tz }}">{{ $tz }}</option>
                            @endforeach
                        </select>
                        @error('timezone') <span class="text-xs text-red-400 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <!-- Bio -->
                    <div class="space-y-1.5">
                        <label for="bio" class="block text-xs font-bold text-zinc-400 uppercase tracking-wider">Bio</label>
                        <textarea 
                            id="bio" 
                            wire:model="bio" 
                            rows="4"
                            class="bg-zinc-950 border border-zinc-800 focus:border-violet-500 rounded-lg px-4 py-2.5 text-sm text-zinc-100 w-full focus:outline-none transition-colors resize-none"
                            placeholder="Tell us about yourself..."
                        ></textarea>
                        @error('bio') <span class="text-xs text-red-400 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <button 
                        type="submit" 
                        class="bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-bold py-2.5 px-6 rounded-lg transition-all text-xs uppercase tracking-wider shadow-lg shadow-violet-900/10 cursor-pointer"
                    >
                        Save Profile
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Column (KYC Submission & Notification Preferences) -->
        <div class="space-y-8">
            <!-- Identity Verification (KYC) -->
            <div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 shadow-lg shadow-black/20 space-y-6">
                <div class="border-b border-zinc-850 pb-3 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="shield-check" class="w-5 h-5 text-fuchsia-400"></i>
                        <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase">
                            KYC VERIFICATION
                        </h2>
                    </div>

                    @php
                        $kycStatus = $latestKyc ? $latestKyc->status->value : 'not_submitted';
                        $badgeColors = [
                            'not_submitted' => 'bg-zinc-950/60 text-zinc-400 border-zinc-850',
                            'submitted' => 'bg-blue-950/40 text-blue-400 border-blue-900/60',
                            'under_review' => 'bg-amber-950/40 text-amber-400 border-amber-900/60',
                            'approved' => 'bg-emerald-950/40 text-emerald-400 border-emerald-900/60',
                            'rejected' => 'bg-red-950/40 text-red-400 border-red-900/60',
                        ];
                        $statusBadgeColor = $badgeColors[$kycStatus] ?? $badgeColors['not_submitted'];
                    @endphp

                    <span class="text-[9px] font-bold uppercase tracking-wider border rounded-md px-2.5 py-1 {{ $statusBadgeColor }}">
                        {{ str_replace('_', ' ', $kycStatus) }}
                    </span>
                </div>

                @if($kycStatus === 'approved')
                    <div class="bg-emerald-950/20 border border-emerald-900/40 text-emerald-400/90 rounded-xl p-4 text-xs space-y-2">
                        <p class="font-bold flex items-center gap-1.5">
                            <i data-lucide="check" class="w-4 h-4"></i> Identity Verified
                        </p>
                        <p>Your identity has been fully verified. You can now withdraw funds from your wallet and participate in real-money tournaments.</p>
                    </div>
                @elseif($kycStatus === 'submitted' || $kycStatus === 'under_review')
                    <div class="bg-amber-950/20 border border-amber-900/40 text-amber-400/90 rounded-xl p-4 text-xs space-y-2">
                        <p class="font-bold flex items-center gap-1.5">
                            <i data-lucide="clock" class="w-4 h-4"></i> Under Review
                        </p>
                        <p>We are reviewing your submission. This typically takes 24-48 hours. Withdrawal actions will be enabled once approved.</p>
                    </div>
                @else
                    <!-- KYC Submit Form -->
                    @if($kycStatus === 'rejected')
                        <div class="bg-red-950/20 border border-red-900/40 text-red-400/90 rounded-xl p-4 text-xs space-y-2">
                            <p class="font-bold flex items-center gap-1.5">
                                <i data-lucide="x" class="w-4 h-4"></i> KYC Rejected
                            </p>
                            @if($latestKyc && $latestKyc->review_notes)
                                <p class="italic">Notes: "{{ $latestKyc->review_notes }}"</p>
                            @endif
                            <p>Please review your information and submit a new document.</p>
                        </div>
                    @endif

                    <form wire:submit="submitKyc" class="space-y-4">
                        <div class="space-y-1.5">
                            <label for="documentType" class="block text-xs font-bold text-zinc-400 uppercase tracking-wider">Document Type</label>
                            <select 
                                id="documentType" 
                                wire:model="documentType" 
                                class="bg-zinc-950 border border-zinc-800 focus:border-violet-500 rounded-lg px-4 py-2.5 text-sm text-zinc-100 w-full focus:outline-none transition-colors"
                            >
                                <option value="id_card">ID Card / National ID</option>
                                <option value="passport">Passport</option>
                                <option value="drivers_license">Driver's License</option>
                            </select>
                            @error('documentType') <span class="text-xs text-red-400 font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="kycFile" class="block text-xs font-bold text-zinc-400 uppercase tracking-wider">Document Upload</label>
                            <input 
                                type="file" 
                                id="kycFile" 
                                wire:model="kycFile" 
                                class="bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-400 w-full focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-l-lg file:border-0 file:text-xs file:font-bold file:uppercase file:bg-zinc-800 file:text-zinc-300 hover:file:bg-zinc-700 file:cursor-pointer"
                            >
                            <div class="text-[10px] text-zinc-500 mt-1">Supported: PNG, JPG, JPEG, PDF (Max 10MB)</div>
                            @error('kycFile') <span class="text-xs text-red-400 font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <!-- Progress indicator for upload -->
                        <div wire:loading wire:target="kycFile" class="text-xs text-zinc-400 italic">
                            Uploading document...
                        </div>

                        <button 
                            type="submit" 
                            wire:loading.attr="disabled"
                            class="w-full bg-gradient-to-r from-fuchsia-600 to-violet-600 hover:from-fuchsia-500 hover:to-violet-500 text-white font-bold py-2.5 rounded-lg transition-all text-xs uppercase tracking-wider cursor-pointer"
                        >
                            Submit Verification
                        </button>
                    </form>
                @endif
            </div>

            <!-- Notification Preferences -->
            <div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 shadow-lg shadow-black/20 space-y-6">
                <div class="border-b border-zinc-850 pb-3 flex items-center space-x-2">
                    <i data-lucide="bell" class="w-5 h-5 text-indigo-400"></i>
                    <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase">
                        NOTIFICATION PREFERENCES
                    </h2>
                </div>

                <div class="space-y-4">
                    <!-- Email Toggles -->
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="block text-sm font-bold text-zinc-200">Email Notifications</span>
                            <span class="block text-xs text-zinc-500">Get match and wallet updates via email.</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="emailNotifications" wire:change="updatePreferences" class="sr-only peer">
                            <div class="w-11 h-6 bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-zinc-400 after:border-zinc-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 peer-checked:after:bg-white"></div>
                        </label>
                    </div>

                    <!-- In-App Toggles -->
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="block text-sm font-bold text-zinc-200">In-App Notifications</span>
                            <span class="block text-xs text-zinc-500">Log notification history inside the dashboard.</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="inAppNotifications" wire:change="updatePreferences" class="sr-only peer">
                            <div class="w-11 h-6 bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-zinc-400 after:border-zinc-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 peer-checked:after:bg-white"></div>
                        </label>
                    </div>

                    <!-- Realtime Toggles -->
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="block text-sm font-bold text-zinc-200">Real-Time Broadcasts</span>
                            <span class="block text-xs text-zinc-500">Receive instant push notifications in active sessions.</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="realtimeNotifications" wire:change="updatePreferences" class="sr-only peer">
                            <div class="w-11 h-6 bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-zinc-400 after:border-zinc-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 peer-checked:after:bg-white"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
