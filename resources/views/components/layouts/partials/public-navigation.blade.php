<header class="sticky top-0 z-50 border-b border-zinc-900/50 bg-zinc-950/75 px-2 py-3 backdrop-blur-xl sm:px-6 lg:px-8">
    <div class="mx-auto grid max-w-7xl grid-cols-[auto_minmax(0,1fr)] items-center gap-2 sm:gap-3 md:grid-cols-[1fr_auto_1fr]">
        <a href="/" class="flex min-w-0 items-center group md:col-start-1 md:row-start-1">
            <img src="/playersaloons_logo.webp" alt="PlayerSaloons Logo" class="h-6 w-auto max-w-[76px] object-contain transition-transform duration-500 group-hover:scale-105 sm:h-11 sm:max-w-none">
        </a>

        <nav class="hidden items-center gap-8 md:col-start-2 md:row-start-1 md:flex">
            <a href="/tournaments" class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] {{ request()->is('tournaments*') ? 'text-cyan-400' : 'text-zinc-500 hover:text-white' }} transition-colors duration-300">
                <i data-lucide="trophy" class="h-4 w-4"></i>
                <span>Tournaments</span>
            </a>
            @if(!auth()->check() || !auth()->user()->hasAnyRole(['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER']))
                <a href="/teams" class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] {{ request()->is('teams*') ? 'text-fuchsia-400' : 'text-zinc-500 hover:text-white' }} transition-colors duration-300">
                    <i data-lucide="users" class="h-4 w-4"></i>
                    <span>Teams</span>
                </a>
            @endif
            @auth
                <a href="/dashboard" class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] {{ request()->is('dashboard*') ? 'text-indigo-400' : 'text-zinc-500 hover:text-white' }} transition-colors duration-300">
                    <i data-lucide="layout-dashboard" class="h-4 w-4"></i>
                    <span>Dashboard</span>
                </a>
            @endauth
        </nav>

        <div class="flex min-w-0 items-center justify-end gap-1 sm:gap-2 md:col-start-3 md:row-start-1 md:gap-4">
            @auth
                <a href="/dashboard" class="md:hidden rounded-full border border-zinc-800 bg-zinc-900/50 px-2.5 py-2 text-[9px] font-black uppercase tracking-wider text-zinc-300 transition-colors hover:border-cyan-500/50 hover:text-cyan-300 sm:px-3 sm:text-[10px]">
                    Dashboard
                </a>
                <a href="{{ auth()->user()?->hasAnyRole(['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER']) ? '/admin/profile' : '/profile' }}" class="hidden items-center gap-2 rounded-full border border-zinc-800 bg-zinc-900/50 py-1.5 pl-1.5 pr-4 transition-all duration-300 hover:bg-zinc-800 md:flex">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-zinc-950 text-zinc-500">
                        <i data-lucide="user" class="h-4 w-4"></i>
                    </span>
                    <span class="text-[10px] font-black uppercase tracking-widest text-zinc-300">
                        {{ auth()->user()->profile?->display_name ?? auth()->user()->username }}
                    </span>
                </a>
            @else
                <a href="/login" class="shrink-0 rounded-full border border-transparent px-1 py-2 text-[7px] font-black uppercase tracking-normal text-zinc-300 transition-colors hover:text-white sm:px-3 sm:text-[10px] sm:tracking-wider md:text-xs">
                    Sign In
                </a>
                <a href="/register" class="shrink-0 rounded-full bg-white px-1.5 py-2 text-[7px] font-black uppercase tracking-normal text-black shadow-[0_0_20px_rgba(255,255,255,0.1)] transition-all duration-300 hover:bg-cyan-400 hover:shadow-[0_0_25px_rgba(34,211,238,0.4)] sm:px-3.5 sm:text-[10px] sm:tracking-wider md:px-6 md:text-xs">
                    Join Now
                </a>
            @endauth

            <button type="button" class="pwa-install-btn hidden shrink-0 items-center justify-center gap-2 rounded-full border border-fuchsia-500/50 bg-fuchsia-600/20 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-fuchsia-300 shadow-[0_0_15px_rgba(192,38,211,0.2)] transition-all hover:bg-fuchsia-600/40 disabled:cursor-not-allowed disabled:opacity-50" data-pwa-install-desktop aria-label="Install PlayerSaloons app" disabled>
                <i data-lucide="download" class="h-4 w-4"></i>
                <span>Install</span>
            </button>

            <button type="button" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-zinc-800 bg-zinc-900/70 text-zinc-300 transition-colors hover:border-cyan-500/50 hover:text-white sm:h-9 sm:w-9 md:hidden" data-public-menu-button aria-label="Open navigation menu" aria-expanded="false">
                <i data-lucide="menu" class="h-4 w-4" data-menu-icon-open></i>
                <i data-lucide="x" class="hidden h-4 w-4" data-menu-icon-close></i>
            </button>
        </div>
    </div>

    <div class="mx-auto hidden max-w-7xl pt-3 md:hidden" data-public-mobile-menu>
        <div class="space-y-2 rounded-2xl border border-zinc-800/80 bg-zinc-950/95 p-3 shadow-2xl shadow-black/50">
            <a href="/tournaments" class="flex items-center justify-between rounded-xl border border-zinc-900 bg-zinc-900/50 px-4 py-3 text-[10px] font-black uppercase tracking-widest {{ request()->is('tournaments*') ? 'text-cyan-300' : 'text-zinc-300' }}">
                <span>Tournaments</span>
                <i data-lucide="trophy" class="h-4 w-4"></i>
            </a>
            @if(!auth()->check() || !auth()->user()->hasAnyRole(['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER']))
                <a href="/teams" class="flex items-center justify-between rounded-xl border border-zinc-900 bg-zinc-900/50 px-4 py-3 text-[10px] font-black uppercase tracking-widest {{ request()->is('teams*') ? 'text-fuchsia-300' : 'text-zinc-300' }}">
                    <span>Teams</span>
                    <i data-lucide="users" class="h-4 w-4"></i>
                </a>
            @endif
            @auth
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-between rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-red-300">
                        <span>Logout</span>
                        <i data-lucide="log-out" class="h-4 w-4"></i>
                    </button>
                </form>
            @endauth
            <button type="button" class="pwa-install-btn hidden w-full items-center justify-center gap-2 rounded-xl border border-fuchsia-500/50 bg-fuchsia-600/20 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-fuchsia-300 transition-all hover:bg-fuchsia-600/40 disabled:cursor-not-allowed disabled:opacity-50" data-pwa-install-mobile aria-label="Install PlayerSaloons app" disabled>
                <i data-lucide="download" class="h-4 w-4"></i>
                <span>Install App</span>
            </button>
        </div>
    </div>
</header>
