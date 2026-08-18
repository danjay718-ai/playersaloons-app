<div class="min-h-[calc(100vh-6rem)] bg-zinc-950 px-4 py-8 sm:px-6 lg:px-8">
    <div
        class="mx-auto grid w-full max-w-5xl overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow-2xl shadow-black/30 lg:grid-cols-[0.9fr_1.1fr]"
        x-data="{
            username: @entangle('username').live,
            email: @entangle('email').live,
            password: @entangle('password').live,
            passwordConfirmation: @entangle('password_confirmation').live,
            acceptedPolicies: @entangle('accepted_policies').live,
            ageConfirmed: @entangle('age_confirmed').live,
            get canSubmit() {
                return this.username.trim() !== ''
                    && this.email.trim() !== ''
                    && this.password !== ''
                    && this.passwordConfirmation !== ''
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
                            <input x-model="username" id="username" name="username" type="text" autocomplete="username" required
                                class="block w-full rounded-lg border border-zinc-800 bg-zinc-950 py-2.5 pl-9 pr-3 text-sm text-zinc-200 placeholder-zinc-600 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                placeholder="player_name">
                        </div>
                        @error('username') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="display_name" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Display Name</label>
                        <div class="mt-1.5 relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-500">
                                <i data-lucide="user" class="h-4 w-4"></i>
                            </span>
                            <input wire:model="display_name" id="display_name" name="display_name" type="text"
                                class="block w-full rounded-lg border border-zinc-800 bg-zinc-950 py-2.5 pl-9 pr-3 text-sm text-zinc-200 placeholder-zinc-600 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                placeholder="Optional">
                        </div>
                        @error('display_name') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label for="countryCode" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Country <span class="text-zinc-600 font-normal normal-case">(optional)</span></label>
                    <div class="mt-1.5 relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-500 pointer-events-none">
                            <i data-lucide="globe" class="h-4 w-4"></i>
                        </span>
                        <select wire:model="countryCode" id="countryCode" name="countryCode"
                            class="block w-full rounded-lg border border-zinc-800 bg-zinc-950 py-2.5 pl-9 pr-3 text-sm text-zinc-200 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 appearance-none">
                            <option value="">Select your country</option>
                            @foreach(config('countries') as $code => $name)
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
                        <input x-model="email" id="email" name="email" type="email" autocomplete="email" required
                            class="block w-full rounded-lg border border-zinc-800 bg-zinc-950 py-2.5 pl-9 pr-3 text-sm text-zinc-200 placeholder-zinc-600 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                            placeholder="you@example.com">
                    </div>
                    @error('email') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Password</label>
                        <div class="mt-1.5 relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-500">
                                <i data-lucide="lock" class="h-4 w-4"></i>
                            </span>
                            <input x-model="password" id="password" name="password" type="password" autocomplete="new-password" required
                                class="block w-full rounded-lg border border-zinc-800 bg-zinc-950 py-2.5 pl-9 pr-3 text-sm text-zinc-200 placeholder-zinc-600 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                placeholder="Minimum 8 characters">
                        </div>
                        @error('password') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Confirm Password</label>
                        <div class="mt-1.5 relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-500">
                                <i data-lucide="lock-keyhole" class="h-4 w-4"></i>
                            </span>
                            <input x-model="passwordConfirmation" id="password_confirmation" name="password_confirmation" type="password" required
                                class="block w-full rounded-lg border border-zinc-800 bg-zinc-950 py-2.5 pl-9 pr-3 text-sm text-zinc-200 placeholder-zinc-600 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                placeholder="Repeat password">
                        </div>
                    </div>
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
