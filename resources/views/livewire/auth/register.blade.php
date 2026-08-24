<div class="relative min-h-[calc(100vh-6rem)] overflow-hidden bg-zinc-950/70 px-4 py-8 sm:px-6 lg:px-8">
    <x-auth.arena-background />
    <div
        class="relative mx-auto grid w-full max-w-5xl overflow-hidden rounded-xl border border-zinc-700/80 bg-zinc-900/95 shadow-2xl shadow-violet-950/30 backdrop-blur-sm lg:grid-cols-[0.9fr_1.1fr]"
        x-data="{
            username: @entangle('username'),
            fullName: @entangle('full_name'),
            countryCode: @entangle('countryCode'),
            email: @entangle('email'),
            password: @entangle('password'),
            passwordConfirmation: @entangle('password_confirmation'),
            acceptedPolicies: @entangle('accepted_policies'),
            ageConfirmed: @entangle('age_confirmed'),
            showPassword: false,
            showPasswordConfirmation: false,
            get passwordChecks() {
                return {
                    length: this.password.length >= 8,
                    lower: /[a-z]/.test(this.password),
                    upper: /[A-Z]/.test(this.password),
                    number: /[0-9]/.test(this.password),
                };
            },
            get passwordScore() { return Object.values(this.passwordChecks).filter(Boolean).length; },
            get passwordStrong() { return this.passwordScore === 4; },
            get strengthLabel() {
                if (!this.password) return 'Enter a password';
                if (this.passwordScore <= 1) return 'Weak';
                if (this.passwordScore <= 3) return 'Almost there';
                return 'Strong password';
            },
            get canSubmit() {
                return /^[A-Za-z0-9_-]{3,30}$/.test(this.username)
                    && this.fullName.trim().length >= 2
                    && !/[0-9<>]/.test(this.fullName)
                    && this.countryCode !== ''
                    && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email)
                    && this.passwordStrong
                    && this.password === this.passwordConfirmation
                    && this.acceptedPolicies
                    && this.ageConfirmed;
            }
        }"
    >
        <section class="flex flex-col justify-between border-b border-zinc-800 bg-zinc-950 p-6 lg:border-b-0 lg:border-r lg:p-8">
            <div>
                <div class="flex items-center gap-3">
                    <div class="grid h-11 w-11 place-items-center rounded-lg border border-violet-400/30 bg-violet-500/10 text-violet-200">
                        <i data-lucide="swords" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.24em] text-violet-300">PlayerSaloons</p>
                        <h1 class="font-orbitron text-2xl font-black uppercase tracking-wide text-white">Join Now</h1>
                    </div>
                </div>

                <p class="mt-5 text-sm leading-6 text-zinc-400">
                    Create your player account, verify eligibility, and start competing in tournaments and head-to-head duels.
                </p>
            </div>

            <div class="mt-8 grid gap-3 text-sm text-zinc-300">
                <div class="flex items-center gap-3 rounded-lg border border-zinc-800 bg-zinc-900/70 px-4 py-3">
                    <i data-lucide="shield-check" class="h-4 w-4 shrink-0 text-emerald-300"></i>
                    <span>Account, KYC, wallet, and tournament access in one profile.</span>
                </div>
                <div class="flex items-center gap-3 rounded-lg border border-zinc-800 bg-zinc-900/70 px-4 py-3">
                    <i data-lucide="bell" class="h-4 w-4 shrink-0 text-sky-300"></i>
                    <span>Optional updates for tournaments, platform news, and releases.</span>
                </div>
                <div class="flex items-center gap-3 rounded-lg border border-zinc-800 bg-zinc-900/70 px-4 py-3">
                    <i data-lucide="file-check-2" class="h-4 w-4 shrink-0 text-amber-300"></i>
                    <span>Policy acceptance and age confirmation are required before account creation.</span>
                </div>
            </div>
        </section>

        <section class="p-6 sm:p-8">
            <div class="mb-6">
                <p class="text-xs font-bold uppercase tracking-[0.24em] text-zinc-500">Create Account</p>
                <h2 class="mt-1 text-2xl font-black text-white">Set up your player login</h2>
            </div>

            <form wire:submit.prevent="register" class="space-y-5">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="username" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Username</label>
                        <div class="mt-1.5 relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-500">
                                <i data-lucide="at-sign" class="h-4 w-4"></i>
                            </span>
                            <input x-model="username" id="username" name="username" type="text" autocomplete="username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_-]+"
                                class="block w-full rounded-lg border border-zinc-800 bg-zinc-950 py-2.5 pl-9 pr-3 text-sm text-zinc-200 placeholder-zinc-600 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                placeholder="player_name">
                        </div>
                        @error('username') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="full_name" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Full Legal Name</label>
                        <div class="mt-1.5 relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-500">
                                <i data-lucide="user" class="h-4 w-4"></i>
                            </span>
                            <input x-model="fullName" id="full_name" name="full_name" type="text" autocomplete="name" required minlength="2" maxlength="150"
                                class="block w-full rounded-lg border border-zinc-800 bg-zinc-950 py-2.5 pl-9 pr-3 text-sm text-zinc-200 placeholder-zinc-600 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                placeholder="As shown on your ID">
                        </div>
                        <p class="mt-1 text-[11px] text-zinc-500">Used privately for identity verification.</p>
                        @error('full_name') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label for="countryCode" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Country</label>
                    <div class="mt-1.5 relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-500 pointer-events-none">
                            <i data-lucide="globe" class="h-4 w-4"></i>
                        </span>
                        <select x-model="countryCode" id="countryCode" name="countryCode" required
                            class="block w-full rounded-lg border border-zinc-800 bg-zinc-950 py-2.5 pl-9 pr-3 text-sm text-zinc-200 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 appearance-none">
                            <option value="">Select your country</option>
                            @foreach($countries as $code => $name)
                                <option value="{{ $code }}">{{ $name }} ({{ $code }})</option>
                            @endforeach
                        </select>
                    </div>
                    @error('countryCode') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Email Address</label>
                    <div class="mt-1.5 relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-500">
                            <i data-lucide="mail" class="h-4 w-4"></i>
                        </span>
                        <input x-model="email" id="email" name="email" type="email" autocomplete="email" required maxlength="255"
                            class="block w-full rounded-lg border border-zinc-800 bg-zinc-950 py-2.5 pl-9 pr-3 text-sm text-zinc-200 placeholder-zinc-600 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                            placeholder="you@example.com">
                    </div>
                    @error('email') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Password</label>
                        <div class="mt-1.5 relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-500 pointer-events-none">
                                <i data-lucide="lock" class="h-4 w-4"></i>
                            </span>
                            <input x-model="password" id="password" name="password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" required minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}"
                                class="block w-full rounded-lg border border-zinc-800 bg-zinc-950 py-2.5 pl-9 pr-10 text-sm text-zinc-200 placeholder-zinc-600 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                placeholder="8+ characters">
                            <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-500 hover:text-zinc-300 focus:outline-none transition-colors" tabindex="-1" :title="showPassword ? 'Hide password' : 'Show password'">
                                <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                            </button>
                        </div>
                        <div class="mt-2 flex gap-1" aria-hidden="true">
                            <template x-for="step in 4" :key="step">
                                <span class="h-1 flex-1 rounded-full transition-colors" :class="step <= passwordScore ? (passwordStrong ? 'bg-emerald-400' : 'bg-amber-400') : 'bg-zinc-800'"></span>
                            </template>
                        </div>
                        <p class="mt-1 text-xs font-semibold" :class="passwordStrong ? 'text-emerald-300' : (password ? 'text-amber-300' : 'text-zinc-500')" x-text="strengthLabel" aria-live="polite"></p>
                        @error('password') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Confirm Password</label>
                        <div class="mt-1.5 relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-500 pointer-events-none">
                                <i data-lucide="lock-keyhole" class="h-4 w-4"></i>
                            </span>
                            <input x-model="passwordConfirmation" id="password_confirmation" name="password_confirmation" :type="showPasswordConfirmation ? 'text' : 'password'" autocomplete="new-password" required minlength="8"
                                class="block w-full rounded-lg border border-zinc-800 bg-zinc-950 py-2.5 pl-9 pr-10 text-sm text-zinc-200 placeholder-zinc-600 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                placeholder="Repeat password">
                            <button type="button" @click="showPasswordConfirmation = !showPasswordConfirmation" class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-500 hover:text-zinc-300 focus:outline-none transition-colors" tabindex="-1" :title="showPasswordConfirmation ? 'Hide password' : 'Show password'">
                                <svg x-show="!showPasswordConfirmation" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showPasswordConfirmation" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                            </button>
                        </div>
                        <p x-show="passwordConfirmation" class="mt-1 text-xs font-semibold" :class="password === passwordConfirmation ? 'text-emerald-300' : 'text-red-300'" x-text="password === passwordConfirmation ? 'Passwords match' : 'Passwords do not match'" aria-live="polite"></p>
                        @error('password_confirmation') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 rounded-lg border border-zinc-800 bg-zinc-950/70 p-3 text-[11px] sm:grid-cols-4">
                    <span :class="passwordChecks.length ? 'text-emerald-300' : 'text-zinc-500'">8+ characters</span>
                    <span :class="passwordChecks.lower ? 'text-emerald-300' : 'text-zinc-500'">Lowercase</span>
                    <span :class="passwordChecks.upper ? 'text-emerald-300' : 'text-zinc-500'">Uppercase</span>
                    <span :class="passwordChecks.number ? 'text-emerald-300' : 'text-zinc-500'">Number</span>
                </div>

                <div class="space-y-3 rounded-lg border border-zinc-800 bg-zinc-950 p-4">
                    <label class="flex items-start gap-3 text-sm text-zinc-300">
                        <input x-model="acceptedPolicies" type="checkbox"
                            class="mt-1 h-4 w-4 rounded border-zinc-700 bg-zinc-900 text-violet-500 focus:ring-violet-500">
                        <span>
                            I accept the
                            <a href="/policies/cookie-policy" target="_blank" class="font-semibold text-violet-300 hover:text-violet-200">Cookie Policy</a>,
                            <a href="/policies/terms-and-conditions" target="_blank" class="font-semibold text-violet-300 hover:text-violet-200">Terms & Conditions</a>
                            and
                            <a href="/policies/privacy-policy" target="_blank" class="font-semibold text-violet-300 hover:text-violet-200">Privacy Policy</a>.
                        </span>
                    </label>
                    @error('accepted_policies') <span class="block text-xs text-red-400">{{ $message }}</span> @enderror

                    <label class="flex items-start gap-3 text-sm text-zinc-300">
                        <input x-model="ageConfirmed" type="checkbox"
                            class="mt-1 h-4 w-4 rounded border-zinc-700 bg-zinc-900 text-violet-500 focus:ring-violet-500">
                        <span>I am 18 years or older.</span>
                    </label>
                    @error('age_confirmed') <span class="block text-xs text-red-400">{{ $message }}</span> @enderror

                    <label class="flex items-start gap-3 text-sm text-zinc-400">
                        <input wire:model="newsletter_subscribed" type="checkbox"
                            class="mt-1 h-4 w-4 rounded border-zinc-700 bg-zinc-900 text-violet-500 focus:ring-violet-500">
                        <span>Subscribe to newsletters, tournament announcements, and platform updates.</span>
                    </label>
                </div>

                <button type="submit"
                    :disabled="!canSubmit"
                    wire:loading.attr="disabled"
                    wire:target="register"
                    wire:loading.class="cursor-not-allowed opacity-70"
                    :class="canSubmit ? 'bg-gradient-to-r from-violet-600 to-indigo-600 text-white shadow-md shadow-violet-900/30 hover:from-violet-500 hover:to-indigo-500' : 'cursor-not-allowed border border-zinc-800 bg-zinc-800 text-zinc-500'"
                    class="group relative flex w-full justify-center rounded-lg px-4 py-3 text-sm font-bold transition disabled:cursor-not-allowed disabled:opacity-70 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 focus:ring-offset-zinc-900">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i data-lucide="user-plus" class="h-4 w-4"></i>
                    </span>
                    <span wire:loading.remove wire:target="register">Create Account</span>
                    <span wire:loading wire:target="register">Creating Account...</span>
                </button>
            </form>

            <div class="mt-6 text-center">
                <span class="text-xs text-zinc-500">Already have an account?</span>
                <a href="/login" wire:navigate class="ml-1 text-xs font-bold text-violet-400 transition hover:text-violet-300">
                    Sign In
                </a>
            </div>
        </section>
    </div>
</div>
