<div class="relative min-h-[70vh] overflow-hidden flex items-center justify-center py-10 px-4 sm:px-6 lg:px-8">
    <x-auth.arena-background />
    <div class="max-w-md w-full space-y-8 bg-zinc-900/95 border border-zinc-700/80 rounded-2xl p-6 md:p-8 shadow-2xl shadow-violet-950/30 backdrop-blur-sm relative overflow-hidden">
        
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
                    <label for="identity" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider">Username or Email</label>
                    <div class="mt-1.5 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-zinc-500">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </span>
                        <input wire:model="identity" id="identity" name="identity" type="text" autocomplete="username" maxlength="255" required
                            class="block w-full pl-9 pr-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200"
                            placeholder="you@example.com or username">
                    </div>
                    @error('identity') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Password -->
                <div x-data="{ show: false }">
                    <div class="flex items-center justify-between">
                        <label for="password" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider">Password</label>
                        <a href="/reset-password" wire:navigate class="text-xs font-semibold text-violet-400 hover:text-violet-300 transition-colors">
                            Forgot Password?
                        </a>
                    </div>
                    <div class="mt-1.5 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-zinc-500 pointer-events-none">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </span>
                        <input wire:model="password" id="password" name="password" :type="show ? 'text' : 'password'" autocomplete="current-password" minlength="1" required
                            class="block w-full pl-9 pr-10 py-2.5 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200"
                            placeholder="••••••••">
                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-500 hover:text-zinc-300 focus:outline-none transition-colors" tabindex="-1" :title="show ? 'Hide password' : 'Show password'">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
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

                <div class="flex items-start gap-2 rounded-lg border border-violet-400/15 bg-violet-500/5 px-3 py-2.5 text-[11px] leading-5 text-zinc-400">
                    <i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-violet-300"></i>
                    <span>Repeated failed attempts trigger a temporary security lock. Password reset remains available above.</span>
                </div>
            </div>

            <div>
                <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="login"
                    wire:loading.class="cursor-not-allowed opacity-70"
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 disabled:cursor-not-allowed disabled:opacity-70 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-zinc-900 focus:ring-violet-500 transition-all duration-200 shadow-md shadow-violet-900/30">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i data-lucide="log-in" class="w-4 h-4 text-violet-300 group-hover:text-white transition-colors"></i>
                    </span>
                    <span wire:loading.remove wire:target="login">Sign In to Play</span>
                    <span wire:loading wire:target="login">Signing In...</span>
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
