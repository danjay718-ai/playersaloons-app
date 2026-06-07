<div class="min-h-[70vh] flex items-center justify-center py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-zinc-900 border border-zinc-800 rounded-2xl p-6 md:p-8 shadow-2xl shadow-violet-950/10 relative overflow-hidden">
        
        <div class="absolute -top-10 -right-10 w-40 h-40 bg-violet-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="text-center">
            <h2 class="text-3xl font-black font-orbitron tracking-wider bg-gradient-to-r from-violet-400 via-fuchsia-400 to-indigo-400 bg-clip-text text-transparent">
                RESET PASSWORD
            </h2>
            <p class="mt-2 text-sm text-zinc-400">
                {{ $isResetMode ? 'Enter your new secure password.' : 'Enter your email address to initiate the reset.' }}
            </p>
        </div>

        @if (!$isResetMode)
            <!-- Request Stage -->
            <form wire:submit.prevent="requestReset" class="mt-8 space-y-6">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider">Email Address</label>
                    <div class="mt-1.5 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-zinc-500">
                            <i data-lucide="mail" class="w-4 h-4"></i>
                        </span>
                        <input wire:model="email" id="email" name="email" type="email" autocomplete="email" required 
                            class="block w-full pl-9 pr-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200"
                            placeholder="you@example.com">
                    </div>
                    @error('email') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <button type="submit" 
                        class="w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-zinc-900 focus:ring-violet-500 transition-all duration-200 shadow-md shadow-violet-900/30">
                        Continue
                    </button>
                </div>
            </form>
        @else
            <!-- Reset Stage -->
            <form wire:submit.prevent="resetPassword" class="mt-8 space-y-6">
                @csrf
                <div class="space-y-4">
                    <div>
                        <span class="text-xs text-zinc-500 font-medium">Resetting password for:</span>
                        <span class="text-xs font-bold text-violet-400 block mt-0.5">{{ $email }}</span>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider">New Password</label>
                        <div class="mt-1.5 relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-zinc-500">
                                <i data-lucide="lock" class="w-4 h-4"></i>
                            </span>
                            <input wire:model="password" id="password" name="password" type="password" required 
                                class="block w-full pl-9 pr-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200"
                                placeholder="••••••••">
                        </div>
                        @error('password') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirmation" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider">Confirm New Password</label>
                        <div class="mt-1.5 relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-zinc-500">
                                <i data-lucide="lock" class="w-4 h-4"></i>
                            </span>
                            <input wire:model="password_confirmation" id="password_confirmation" name="password_confirmation" type="password" required 
                                class="block w-full pl-9 pr-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200"
                                placeholder="••••••••">
                        </div>
                    </div>
                </div>

                <div>
                    <button type="submit" 
                        class="w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-zinc-900 focus:ring-violet-500 transition-all duration-200 shadow-md shadow-violet-900/30">
                        Reset Password
                    </button>
                </div>
            </form>
        @endif

        <div class="text-center mt-6">
            <a href="/login" wire:navigate class="text-xs text-zinc-500 hover:text-zinc-350 transition-colors">
                Back to Sign In
            </a>
        </div>
    </div>
</div>
