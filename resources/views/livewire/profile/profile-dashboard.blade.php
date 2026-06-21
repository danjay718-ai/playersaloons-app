@php
    $profile = $user?->profile;
    $avatarUrl = $profile?->avatar_url;
    $playerName = $displayName ?: ($user?->username ?? 'Player');
    $initials = strtoupper(substr($playerName, 0, 2));
    $kycStatus = $latestKyc ? $latestKyc->status->value : 'not_submitted';
    $kycVerified = $kycStatus === 'approved';
    $emailVerified = $user?->email_verified_at !== null;
    $canSubmitKyc = in_array($kycStatus, ['not_submitted', 'rejected'], true);
    $kycBadge = $kycVerified ? 'VERIFIED' : 'NOT VERIFIED';
    $kycBadgeClasses = $kycVerified
        ? 'border-emerald-400/40 bg-emerald-500/10 text-emerald-300'
        : 'border-amber-400/40 bg-amber-500/10 text-amber-300';
    $emailBadgeClasses = $emailVerified
        ? 'border-cyan-400/40 bg-cyan-500/10 text-cyan-250'
        : 'border-red-400/40 bg-red-500/10 text-red-300';
@endphp

<div
    class="space-y-6 min-w-0"
    x-data="{ activeTab: 'profile', showKycDrawer: false }"
    @profile-kyc-submitted.window="showKycDrawer = false"
>
    <x-ui.toasts />

    <section class="relative overflow-hidden rounded-xl border border-zinc-800 bg-zinc-950 shadow-2xl">
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-emerald-400 via-cyan-400 to-fuchsia-500"></div>
        <div class="grid grid-cols-1 xl:grid-cols-[360px_1fr]">
            <aside class="border-b border-zinc-800 bg-[radial-gradient(circle_at_top_left,rgba(34,211,238,0.14),transparent_32%),linear-gradient(135deg,#111827,#09090b_68%)] p-5 sm:p-6 xl:border-b-0 xl:border-r">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-cyan-300 font-orbitron">Player Card</p>
                        <h2 class="mt-2 text-2xl font-black uppercase tracking-wide text-white font-orbitron break-words">{{ $playerName }}</h2>
                        <p class="mt-1 text-xs font-semibold text-zinc-450">{{ '@'.$user->username }}</p>
                    </div>
                    <span class="rounded-md border border-cyan-400/30 bg-cyan-400/10 px-2.5 py-1 text-[9px] font-black uppercase tracking-widest text-cyan-250 font-orbitron">
                        Lv. 01
                    </span>
                </div>

                <div class="mt-6 flex flex-col items-center">
                    <div class="relative h-40 w-40 rounded-xl border border-zinc-700 bg-zinc-900 p-2 shadow-[0_0_32px_rgba(34,211,238,0.16)]">
                        <div class="h-full w-full overflow-hidden rounded-lg border border-zinc-800 bg-zinc-950 flex items-center justify-center">
                            @if($avatarFile)
                                <img src="{{ $avatarFile->temporaryUrl() }}" alt="{{ $playerName }}" class="h-full w-full object-cover">
                            @elseif($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="{{ $playerName }}" class="h-full w-full object-cover">
                            @else
                                <span class="text-4xl font-black text-cyan-300 font-orbitron">{{ $initials }}</span>
                            @endif
                        </div>
                        <span class="absolute -bottom-2 left-1/2 -translate-x-1/2 rounded-md border border-emerald-400/40 bg-zinc-950 px-3 py-1 text-[9px] font-black uppercase tracking-widest text-emerald-300 font-orbitron">
                            Active
                        </span>
                    </div>

                    <button type="button" @click="activeTab = 'profile'" class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-cyan-400/30 bg-cyan-500/15 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-cyan-100 hover:bg-cyan-500/25 font-orbitron">
                        <i data-lucide="image-up" class="w-4 h-4"></i>
                        Edit Profile
                    </button>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3">
                    <div class="rounded-lg border border-zinc-800 bg-zinc-950/70 p-3">
                        <p class="text-[9px] font-black uppercase tracking-widest text-zinc-550 font-orbitron">Region</p>
                        <p class="mt-1 text-sm font-black text-white">{{ $countryCode ?: 'N/A' }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-800 bg-zinc-950/70 p-3">
                        <p class="text-[9px] font-black uppercase tracking-widest text-zinc-550 font-orbitron">Zone</p>
                        <p class="mt-1 truncate text-sm font-black text-white">{{ $timezone ?: 'UTC' }}</p>
                    </div>
                </div>
            </aside>

            <div class="p-5 sm:p-6">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="rounded-lg border border-zinc-800 bg-zinc-900/60 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">KYC Status</p>
                            <div class="group relative">
                                <i data-lucide="info" class="w-4 h-4 text-amber-300"></i>
                                <div class="pointer-events-none absolute right-0 top-6 z-20 hidden w-64 rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-[11px] font-semibold leading-relaxed text-zinc-300 shadow-xl group-hover:block">
                                    Identity verification is required before a player can withdraw wallet funds.
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <span class="rounded-md border px-2.5 py-1 text-[10px] font-black uppercase tracking-widest font-orbitron {{ $kycBadgeClasses }}">{{ $kycBadge }}</span>
                            <button type="button" @click="showKycDrawer = true" class="inline-flex items-center gap-1.5 rounded-lg border border-amber-400/30 bg-amber-500/10 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-amber-200 hover:bg-amber-500/20 font-orbitron">
                                <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                                {{ $kycVerified ? 'View' : 'Verify' }}
                            </button>
                        </div>
                        <p class="mt-2 text-[11px] font-semibold text-zinc-500">Current review state: {{ str_replace('_', ' ', $kycStatus) }}</p>
                    </div>

                    <div class="rounded-lg border border-zinc-800 bg-zinc-900/60 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">Verified Email</p>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <span class="rounded-md border px-2.5 py-1 text-[10px] font-black uppercase tracking-widest font-orbitron {{ $emailBadgeClasses }}">
                                {{ $emailVerified ? 'VERIFIED' : 'NEEDS VERIFY' }}
                            </span>
                            @unless($emailVerified)
                                <button type="button" wire:click="verifyEmail" class="inline-flex items-center gap-1.5 rounded-lg border border-cyan-400/30 bg-cyan-500/10 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-cyan-200 hover:bg-cyan-500/20 font-orbitron">
                                    <i data-lucide="mail-check" class="w-3.5 h-3.5"></i>
                                    Verify
                                </button>
                            @endunless
                        </div>
                        <p class="mt-2 truncate text-[11px] font-semibold text-zinc-500">{{ $user->email }}</p>
                    </div>

                    <div class="rounded-lg border border-zinc-800 bg-zinc-900/60 p-4" x-data="{ copied: false }">
                        <p class="text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">Invite Code</p>
                        <div class="mt-3 flex items-center gap-2">
                            <input type="text" readonly value="{{ url('/register?ref=' . $user->id) }}" class="min-w-0 flex-1 rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-[11px] font-semibold text-zinc-300">
                            <button type="button" title="Copy referral link" @click="navigator.clipboard.writeText('{{ url('/register?ref=' . $user->id) }}'); copied = true; setTimeout(() => copied = false, 1600)" class="grid h-9 w-9 place-items-center rounded-lg border border-fuchsia-400/30 bg-fuchsia-500/10 text-fuchsia-200 hover:bg-fuchsia-500/20">
                                <i x-show="!copied" data-lucide="copy" class="w-4 h-4"></i>
                                <i x-show="copied" data-lucide="check" class="w-4 h-4 text-emerald-300" x-cloak></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-6 rounded-lg border border-zinc-800 bg-zinc-900/50 p-2">
                    <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                        @foreach([
                            ['key' => 'profile', 'label' => 'Profile', 'icon' => 'badge'],
                            ['key' => 'account', 'label' => 'Account', 'icon' => 'id-card'],
                            ['key' => 'security', 'label' => 'Security', 'icon' => 'key-round'],
                            ['key' => 'comms', 'label' => 'Comms', 'icon' => 'bell'],
                        ] as $tab)
                            <button
                                type="button"
                                @click="activeTab = '{{ $tab['key'] }}'"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-xs font-black uppercase tracking-widest transition-colors font-orbitron"
                                :class="activeTab === '{{ $tab['key'] }}' ? 'border-cyan-400/40 bg-cyan-500/15 text-cyan-100' : 'border-transparent bg-zinc-950/50 text-zinc-500 hover:border-zinc-700 hover:text-zinc-250'"
                            >
                                <i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4"></i>
                                {{ $tab['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-zinc-800 bg-zinc-900/50 p-5">
                    <div x-show="activeTab === 'profile'" x-cloak>
                        <div class="mb-4 flex items-center gap-2 border-b border-zinc-800 pb-3">
                            <i data-lucide="badge" class="w-4 h-4 text-cyan-300"></i>
                            <h3 class="text-sm font-black uppercase tracking-widest text-white font-orbitron">Player Info</h3>
                        </div>

                        <form wire:submit="updateAvatar"
                              x-data="{ fileName: '', uploading: false, progress: 0 }"
                              class="mb-5 rounded-lg border border-zinc-800 bg-zinc-950/60 p-4">
                            <label for="avatarFile" class="block text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">Profile Picture</label>
                            <div class="mt-2 grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto]">
                                <input id="avatarFile"
                                       type="file"
                                       wire:model="avatarFile"
                                       accept="image/png,image/jpeg,image/webp"
                                       x-on:change="fileName = $event.target.files[0]?.name || ''"
                                       x-on:livewire-upload-start="uploading = true; progress = 0"
                                       x-on:livewire-upload-finish="uploading = false; progress = 100"
                                       x-on:livewire-upload-error="uploading = false"
                                       x-on:livewire-upload-progress="progress = $event.detail.progress"
                                       class="w-full rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-300 file:mr-3 file:border-0 file:bg-cyan-500/15 file:px-3 file:py-2.5 file:text-[10px] file:font-black file:uppercase file:tracking-wider file:text-cyan-250 hover:file:bg-cyan-500/25">
                                <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-2 rounded-lg border border-cyan-400/30 bg-cyan-500/15 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-cyan-100 hover:bg-cyan-500/25 disabled:opacity-60 font-orbitron">
                                    <i data-lucide="image-up" class="w-4 h-4"></i>
                                    Upload
                                </button>
                            </div>
                            <div x-show="fileName" x-cloak class="mt-3 rounded-lg border border-cyan-400/20 bg-cyan-500/10 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-cyan-200 font-orbitron">Selected file</p>
                                        <p class="mt-1 truncate text-xs font-semibold text-zinc-200" x-text="fileName"></p>
                                    </div>
                                    <span x-show="uploading" class="shrink-0 text-[10px] font-black uppercase tracking-widest text-cyan-200 font-orbitron" x-text="`${progress}%`"></span>
                                </div>
                                <div x-show="uploading" class="mt-3 h-1.5 overflow-hidden rounded-full bg-zinc-900">
                                    <div class="h-full rounded-full bg-cyan-300 transition-all" :style="`width: ${progress}%`"></div>
                                </div>
                            </div>
                            @error('avatarFile') <span class="block pt-2 text-[10px] font-bold text-red-300">{{ $message }}</span> @enderror
                        </form>

                        <form wire:submit="updateProfile" class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label for="displayName" class="block text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">Display Name</label>
                                    <input id="displayName" type="text" wire:model="displayName" class="mt-1.5 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm font-semibold text-white focus:border-cyan-400 focus:outline-none" placeholder="Gaming tag">
                                    @error('displayName') <span class="text-[10px] font-bold text-red-300">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="countryCode" class="block text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">Country Code</label>
                                    <input id="countryCode" type="text" wire:model="countryCode" maxlength="2" class="mt-1.5 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm font-semibold uppercase text-white focus:border-cyan-400 focus:outline-none" placeholder="PH">
                                    @error('countryCode') <span class="text-[10px] font-bold text-red-300">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div>
                                <label for="timezone" class="block text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">Timezone</label>
                                <select id="timezone" wire:model="timezone" class="mt-1.5 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm font-semibold text-white focus:border-cyan-400 focus:outline-none">
                                    <option value="">Select timezone</option>
                                    @foreach($timezoneOptions as $tz)
                                        <option value="{{ $tz }}">{{ $tz }}</option>
                                    @endforeach
                                </select>
                                @error('timezone') <span class="text-[10px] font-bold text-red-300">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="bio" class="block text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">Bio</label>
                                <textarea id="bio" wire:model="bio" rows="4" class="mt-1.5 w-full resize-none rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm font-semibold text-white focus:border-cyan-400 focus:outline-none" placeholder="Short player intro"></textarea>
                                @error('bio') <span class="text-[10px] font-bold text-red-300">{{ $message }}</span> @enderror
                            </div>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-emerald-400/30 bg-emerald-500/15 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-emerald-100 hover:bg-emerald-500/25 font-orbitron">
                                <i data-lucide="save" class="w-4 h-4"></i>
                                Save Info
                            </button>
                        </form>
                    </div>

                    <div x-show="activeTab === 'account'" x-cloak>
                        <div class="mb-4 flex items-center gap-2 border-b border-zinc-800 pb-3">
                            <i data-lucide="id-card" class="w-4 h-4 text-fuchsia-300"></i>
                            <h3 class="text-sm font-black uppercase tracking-widest text-white font-orbitron">Account</h3>
                        </div>
                        <form wire:submit="updateAccount" class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label for="username" class="block text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">Username</label>
                                    <input id="username" type="text" wire:model="username" class="mt-1.5 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm font-semibold text-white focus:border-fuchsia-400 focus:outline-none">
                                    @error('username') <span class="text-[10px] font-bold text-red-300">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="email" class="block text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">Email</label>
                                    <input id="email" type="email" wire:model="email" class="mt-1.5 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm font-semibold text-white focus:border-fuchsia-400 focus:outline-none">
                                    @error('email') <span class="text-[10px] font-bold text-red-300">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-fuchsia-400/30 bg-fuchsia-500/15 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-fuchsia-100 hover:bg-fuchsia-500/25 font-orbitron">
                                <i data-lucide="save" class="w-4 h-4"></i>
                                Save Account
                            </button>
                        </form>
                    </div>

                    <div x-show="activeTab === 'security'" x-cloak>
                        <div class="mb-4 flex items-center gap-2 border-b border-zinc-800 pb-3">
                            <i data-lucide="key-round" class="w-4 h-4 text-amber-300"></i>
                            <h3 class="text-sm font-black uppercase tracking-widest text-white font-orbitron">Password</h3>
                        </div>
                        <form wire:submit="updatePassword" class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <label for="currentPassword" class="block text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">Current</label>
                                    <input id="currentPassword" type="password" wire:model="currentPassword" class="mt-1.5 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm font-semibold text-white focus:border-amber-400 focus:outline-none">
                                    @error('currentPassword') <span class="text-[10px] font-bold text-red-300">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="newPassword" class="block text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">New</label>
                                    <input id="newPassword" type="password" wire:model="newPassword" class="mt-1.5 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm font-semibold text-white focus:border-amber-400 focus:outline-none">
                                    @error('newPassword') <span class="text-[10px] font-bold text-red-300">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="newPasswordConfirmation" class="block text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">Confirm</label>
                                    <input id="newPasswordConfirmation" type="password" wire:model="newPasswordConfirmation" class="mt-1.5 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm font-semibold text-white focus:border-amber-400 focus:outline-none">
                                    @error('newPasswordConfirmation') <span class="text-[10px] font-bold text-red-300">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-amber-400/30 bg-amber-500/15 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-amber-100 hover:bg-amber-500/25 font-orbitron">
                                <i data-lucide="lock-keyhole" class="w-4 h-4"></i>
                                Change Password
                            </button>
                        </form>
                    </div>

                    <div x-show="activeTab === 'comms'" x-cloak>
                        <div class="mb-4 flex items-center gap-2 border-b border-zinc-800 pb-3">
                            <i data-lucide="bell" class="w-4 h-4 text-cyan-300"></i>
                            <h3 class="text-sm font-black uppercase tracking-widest text-white font-orbitron">Comms Loadout</h3>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            @foreach([
                                ['model' => 'emailNotifications', 'enabled' => $emailNotifications, 'title' => 'Email', 'text' => 'Match, wallet, and tournament alerts.'],
                                ['model' => 'inAppNotifications', 'enabled' => $inAppNotifications, 'title' => 'In-App', 'text' => 'Notification bell updates.'],
                                ['model' => 'realtimeNotifications', 'enabled' => $realtimeNotifications, 'title' => 'Realtime', 'text' => 'Live match and broadcast pings.'],
                            ] as $toggle)
                                <label class="flex items-center justify-between gap-4 rounded-lg border border-zinc-800 bg-zinc-950/70 p-4">
                                    <span>
                                        <span class="block text-xs font-black uppercase tracking-wider text-white font-orbitron">{{ $toggle['title'] }}</span>
                                        <span class="mt-1 block text-[11px] font-semibold text-zinc-500">{{ $toggle['text'] }}</span>
                                    </span>
                                    <span class="relative inline-flex cursor-pointer items-center">
                                        <input type="checkbox" @checked($toggle['enabled']) wire:change="updateNotificationPreference('{{ $toggle['model'] }}', $event.target.checked)" class="peer sr-only">
                                        <span class="h-6 w-11 rounded-full border border-zinc-700 bg-zinc-900 after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-zinc-500 after:transition-all peer-checked:border-cyan-400/50 peer-checked:bg-cyan-500/30 peer-checked:after:translate-x-5 peer-checked:after:bg-cyan-200"></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div x-show="showKycDrawer" x-cloak class="fixed inset-0 z-50 flex justify-end bg-black/70 backdrop-blur-sm" @click.self="showKycDrawer = false">
        <aside class="h-full w-full max-w-lg overflow-y-auto border-l border-zinc-800 bg-zinc-950 p-5 shadow-2xl sm:p-6">
                <div class="flex items-start justify-between gap-4 border-b border-zinc-800 pb-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-amber-300 font-orbitron">Identity Verification</p>
                        <h3 class="mt-2 text-xl font-black uppercase tracking-wide text-white font-orbitron">{{ $kycBadge }}</h3>
                        <p class="mt-1 text-sm font-semibold text-zinc-450">Verify identity to unlock withdrawals.</p>
                    </div>
                    <button type="button" @click="showKycDrawer = false" title="Close KYC drawer" class="grid h-10 w-10 place-items-center rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-300 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="mt-5 rounded-lg border border-zinc-800 bg-zinc-900/60 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-black uppercase tracking-widest text-zinc-500 font-orbitron">Review State</span>
                        <span class="rounded-md border px-2.5 py-1 text-[10px] font-black uppercase tracking-widest font-orbitron {{ $kycBadgeClasses }}">{{ str_replace('_', ' ', $kycStatus) }}</span>
                    </div>
                    @if($latestKyc && $latestKyc->review_notes)
                        <p class="mt-3 rounded-lg border border-red-500/25 bg-red-950/20 p-3 text-xs font-semibold leading-relaxed text-red-250">{{ $latestKyc->review_notes }}</p>
                    @endif
                </div>

                @if($kycVerified)
                    <div class="mt-5 rounded-lg border border-emerald-400/30 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-250">
                        Your identity is verified. Withdrawal access is enabled for this account.
                    </div>
                @elseif(in_array($kycStatus, ['submitted', 'under_review'], true))
                    <div class="mt-5 rounded-lg border border-amber-400/30 bg-amber-500/10 p-4 text-sm font-semibold text-amber-250">
                        Your documents are under review. You can withdraw after compliance approval.
                    </div>
                @elseif($canSubmitKyc)
                    <form wire:submit="submitKyc"
                          x-data="{ fileName: '', uploading: false, progress: 0 }"
                          class="mt-5 space-y-4">
                        <div>
                            <label for="documentType" class="block text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">Document Type</label>
                            <select id="documentType" wire:model="documentType" class="mt-1.5 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm font-semibold text-white focus:border-amber-400 focus:outline-none">
                                <option value="id_card">ID Card / National ID</option>
                                <option value="passport">Passport</option>
                                <option value="drivers_license">Driver's License</option>
                            </select>
                            @error('documentType') <span class="text-[10px] font-bold text-red-300">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="kycFile" class="block text-[10px] font-black uppercase tracking-widest text-zinc-500 font-orbitron">Document File</label>
                            <input id="kycFile"
                                   type="file"
                                   wire:model="kycFile"
                                   accept="application/pdf,image/png,image/jpeg"
                                   x-on:change="fileName = $event.target.files[0]?.name || ''"
                                   x-on:livewire-upload-start="uploading = true; progress = 0"
                                   x-on:livewire-upload-finish="uploading = false; progress = 100"
                                   x-on:livewire-upload-error="uploading = false"
                                   x-on:livewire-upload-progress="progress = $event.detail.progress"
                                   class="mt-1.5 w-full rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-300 file:mr-3 file:border-0 file:bg-amber-500/15 file:px-3 file:py-2.5 file:text-[10px] file:font-black file:uppercase file:tracking-wider file:text-amber-200 hover:file:bg-amber-500/25">
                            <p class="mt-1.5 text-[10px] font-semibold uppercase tracking-wider text-zinc-600">PNG, JPG, JPEG, or PDF. Max 10MB.</p>
                            <div x-show="fileName" x-cloak class="mt-3 rounded-lg border border-amber-400/20 bg-amber-500/10 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-amber-200 font-orbitron">Selected document</p>
                                        <p class="mt-1 truncate text-xs font-semibold text-zinc-200" x-text="fileName"></p>
                                    </div>
                                    <span x-show="uploading" class="shrink-0 text-[10px] font-black uppercase tracking-widest text-amber-200 font-orbitron" x-text="`${progress}%`"></span>
                                </div>
                                <div x-show="uploading" class="mt-3 h-1.5 overflow-hidden rounded-full bg-zinc-900">
                                    <div class="h-full rounded-full bg-amber-300 transition-all" :style="`width: ${progress}%`"></div>
                                </div>
                            </div>
                            @error('kycFile') <span class="text-[10px] font-bold text-red-300">{{ $message }}</span> @enderror
                        </div>
                        <div wire:loading wire:target="kycFile" class="text-[10px] font-black uppercase tracking-widest text-amber-300 font-orbitron">Uploading document...</div>
                        <button type="submit" wire:loading.attr="disabled" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-amber-400/30 bg-amber-500/15 px-4 py-3 text-xs font-black uppercase tracking-widest text-amber-100 hover:bg-amber-500/25 disabled:opacity-60 font-orbitron">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                            Submit Verification
                        </button>
                    </form>
                @endif
        </aside>
    </div>
</div>
