<div class="min-h-[70vh] flex items-center justify-center py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-zinc-900 border border-zinc-800 rounded-2xl p-6 md:p-8 shadow-2xl shadow-violet-950/10 relative overflow-hidden">
        
        <!-- Decorative subtle background gradients -->
        <div class="absolute -top-10 -right-10 w-40 h-40 bg-violet-600/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="text-center">
            <h2 class="text-3xl font-black font-orbitron tracking-wider bg-gradient-to-r from-violet-400 via-fuchsia-400 to-indigo-400 bg-clip-text text-transparent">
                CREATE ACCOUNT
            </h2>
            <p class="mt-2 text-sm text-zinc-400">
                Join PlayerSaloons to register for tournaments and compete for prizes.
            </p>
        </div>

        <form wire:submit.prevent="register" class="mt-8 space-y-6">
            @csrf
            
            <div class="space-y-4">
                <!-- Username -->
                <div>
                    <label for="username" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider">Username</label>
                    <div class="mt-1.5 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-zinc-500">
                            <i data-lucide="at-sign" class="w-4 h-4"></i>
                        </span>
                        <input wire:model="username" id="username" name="username" type="text" autocomplete="username" required 
                            class="block w-full pl-9 pr-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200"
                            placeholder="username">
                    </div>
                    @error('username') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Display Name -->
                <div>
                    <label for="display_name" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider">Display Name (Optional)</label>
                    <div class="mt-1.5 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-zinc-500">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </span>
                        <input wire:model="display_name" id="display_name" name="display_name" type="text" 
                            class="block w-full pl-9 pr-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200"
                            placeholder="John Doe">
                    </div>
                    @error('display_name') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Email Address -->
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

                <!-- Password -->
                <div>
                    <label for="password" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider">Password</label>
                    <div class="mt-1.5 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-zinc-500">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </span>
                        <input wire:model="password" id="password" name="password" type="password" autocomplete="new-password" required 
                            class="block w-full pl-9 pr-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200"
                            placeholder="••••••••">
                    </div>
                    @error('password') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider">Confirm Password</label>
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
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-zinc-900 focus:ring-violet-500 transition-all duration-200 shadow-md shadow-violet-900/30">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i data-lucide="user-plus" class="w-4 h-4 text-violet-300 group-hover:text-white transition-colors"></i>
                    </span>
                    Register Account
                </button>
            </div>
        </form>

        <div class="text-center mt-6">
            <span class="text-xs text-zinc-500">Already have an account?</span>
            <a href="/login" wire:navigate class="text-xs font-bold text-violet-400 hover:text-violet-300 ml-1 transition-colors">
                Sign In
            </a>
        </div>
    </div>
</div>
