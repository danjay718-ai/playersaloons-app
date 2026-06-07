<div class="min-h-[70vh] flex items-center justify-center py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-zinc-900 border border-zinc-800 rounded-2xl p-6 md:p-8 shadow-2xl shadow-violet-950/10 relative overflow-hidden">
        
        <!-- Decorative subtle background gradients -->
        <div class="absolute -top-10 -right-10 w-40 h-40 bg-violet-600/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="text-center">
            <h2 class="text-3xl font-black font-orbitron tracking-wider bg-gradient-to-r from-violet-400 via-fuchsia-400 to-indigo-400 bg-clip-text text-transparent">
                SIGN IN
            </h2>
            <p class="mt-2 text-sm text-zinc-400">
                Welcome back, player! Enter your details to log in.
            </p>
        </div>

        <form wire:submit.prevent="login" class="mt-8 space-y-6">
            @csrf
            
            <div class="space-y-4">
                <!-- Login (Email or Username) -->
                <div>
                    <label for="login" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider">Username or Email</label>
                    <div class="mt-1.5 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-zinc-500">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </span>
                        <input wire:model="login" id="login" name="login" type="text" required 
                            class="block w-full pl-9 pr-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200"
                            placeholder="you@example.com or username">
                    </div>
                    @error('login') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Password -->
                <div>
                    <div class="flex items-center justify-between">
                        <label for="password" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider">Password</label>
                        <a href="/reset-password" wire:navigate class="text-xs font-semibold text-violet-400 hover:text-violet-300 transition-colors">
                            Forgot Password?
                        </a>
                    </div>
                    <div class="mt-1.5 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-zinc-500">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </span>
                        <input wire:model="password" id="password" name="password" type="password" autocomplete="current-password" required 
                            class="block w-full pl-9 pr-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200"
                            placeholder="••••••••">
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center">
                    <input wire:model="remember" id="remember" name="remember" type="checkbox" 
                        class="h-4 w-4 bg-zinc-950 border-zinc-800 text-violet-600 focus:ring-violet-500 focus:ring-offset-zinc-900 rounded transition duration-200">
                    <label for="remember" class="ml-2 block text-xs text-zinc-400 font-medium select-none">
                        Remember me on this device
                    </label>
                </div>
            </div>

            <div>
                <button type="submit" 
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-zinc-900 focus:ring-violet-500 transition-all duration-200 shadow-md shadow-violet-900/30">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i data-lucide="log-in" class="w-4 h-4 text-violet-300 group-hover:text-white transition-colors"></i>
                    </span>
                    Sign In to Play
                </button>
            </div>
        </form>

        <div class="text-center mt-6">
            <span class="text-xs text-zinc-500">New to PlayerSaloons?</span>
            <a href="/register" wire:navigate class="text-xs font-bold text-violet-400 hover:text-violet-300 ml-1 transition-colors">
                Create Account
            </a>
        </div>
    </div>
</div>
