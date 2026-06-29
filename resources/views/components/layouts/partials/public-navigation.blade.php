@php
    $staffRoles = ['SUPER_ADMIN','ADMIN','MODERATOR','FINANCE_OPERATOR','KYC_REVIEWER','SUPPORT_AGENT','TOURNAMENT_ORGANIZER'];
    $currentUser = auth()->user();
    $isStaff = $currentUser?->hasAnyRole($staffRoles) ?? false;
    $publicNavigationItems = \App\Modules\CMS\Models\PublicNavigationItem::query()
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get()
        ->filter(function ($item) use ($currentUser, $isStaff) {
            return match ($item->visibility) {
                'guest'          => $currentUser === null,
                'auth'           => $currentUser !== null,
                'player'         => $currentUser !== null && ! $isStaff,
                'staff'          => $currentUser !== null && $isStaff,
                'guest_or_player'=> $currentUser === null || ! $isStaff,
                default          => true,
            };
        });
@endphp

<header id="public-nav"
    class="fixed top-0 left-0 right-0 z-50 transition-all duration-500 ease-in-out nav-transparent"
    style="width:100%;max-width:100vw;overflow:visible;">

    {{-- ══════════════════════════════════════
         TOP BAR  (logo | desktop-nav | actions)
    ══════════════════════════════════════ --}}
    <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-3 sm:px-6 sm:py-4 lg:px-8">

        {{-- Logo ─────────────────────────────── --}}
        <a href="/" class="flex shrink-0 items-center group">
            <img src="/playersaloons_logo.webp" alt="PlayerSaloons Logo"
                class="h-7 w-auto object-contain transition-all duration-500 group-hover:brightness-125 sm:h-10">
        </a>

        {{-- Desktop centre nav (md+) ─────────── --}}
        <nav class="hidden md:flex items-center gap-8">
            @foreach($publicNavigationItems as $item)
                @php($isActive = $item->match_pattern ? request()->is($item->match_pattern) : url()->current() === url($item->url))
                <a href="{{ $item->url }}"
                    @if($item->opens_new_tab) target="_blank" rel="noopener" @endif
                    class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em]
                           {{ $isActive ? 'text-cyan-400' : 'text-zinc-400 hover:text-white' }}
                           transition-colors duration-300">
                    @if($item->icon)
                        <i data-lucide="{{ $item->icon }}" class="h-3.5 w-3.5"></i>
                    @endif
                    <span>{{ __($item->label) }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Right actions ─────────────────────── --}}
        <div class="flex shrink-0 items-center gap-2 sm:gap-3">

            {{-- ── Authenticated user ── --}}
            @auth
                {{-- Profile pill — desktop only --}}
                <a href="{{ $isStaff ? '/admin/profile' : '/profile' }}"
                    class="hidden md:flex items-center gap-2 rounded-full border border-zinc-700/60
                           bg-zinc-900/60 py-1.5 pl-1.5 pr-4 backdrop-blur
                           transition-all duration-300 hover:bg-zinc-800">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-zinc-950 text-zinc-400">
                        <i data-lucide="user" class="h-4 w-4"></i>
                    </span>
                    <span class="text-[10px] font-black uppercase tracking-widest text-zinc-300">
                        {{ $currentUser->profile?->display_name ?? $currentUser->username }}
                    </span>
                </a>

                {{-- Dashboard shortcut — mobile only --}}
                <a href="{{ $isStaff ? '/admin/profile' : '/dashboard' }}"
                    class="md:hidden flex items-center justify-center rounded-full border border-zinc-700/60
                           bg-zinc-900/60 px-3 py-2 text-[9px] font-black uppercase tracking-wider
                           text-zinc-300 backdrop-blur transition-all hover:border-cyan-500/50 hover:text-cyan-300">
                    {{ __('Dashboard') }}
                </a>
            @endauth

            {{-- ── Guest user ── --}}
            @guest
                {{-- Sign In — always visible --}}
                <a href="/login"
                    class="rounded-full py-2 px-3 text-[10px] font-black uppercase tracking-wider
                           text-zinc-300 transition-colors hover:text-white sm:px-4">
                    {{ __('Sign In') }}
                </a>

                {{-- Join Now — always visible --}}
                <a href="/register"
                    class="shrink-0 rounded-full bg-gradient-to-r from-cyan-500 to-violet-600
                           px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white
                           shadow-[0_0_20px_rgba(34,211,238,0.3)] transition-all duration-300
                           hover:shadow-[0_0_30px_rgba(34,211,238,0.5)] hover:brightness-110
                           sm:px-5">
                    {{ __('Join Now') }}
                </a>
            @endguest

            {{-- PWA Install — desktop only --}}
            <button type="button"
                class="pwa-install-btn hidden md:inline-flex shrink-0 items-center justify-center gap-2
                       rounded-full border border-fuchsia-500/50 bg-fuchsia-600/20 px-4 py-2
                       text-[10px] font-black uppercase tracking-widest text-fuchsia-300
                       shadow-[0_0_15px_rgba(192,38,211,0.2)] backdrop-blur transition-all
                       hover:bg-fuchsia-600/40 disabled:cursor-not-allowed disabled:opacity-50"
                data-pwa-install-desktop aria-label="{{ __('Install PlayerSaloons app') }}" disabled>
                <i data-lucide="download" class="h-3.5 w-3.5"></i>
                <span>{{ __('Install') }}</span>
            </button>

            <x-localization.language-switcher variant="public" class="hidden sm:block" />

            {{-- Burger — mobile only (md:hidden) --}}
            <button type="button"
                class="md:hidden flex h-9 w-9 shrink-0 items-center justify-center rounded-full
                       border border-zinc-700/60 bg-zinc-900/60 text-zinc-300 backdrop-blur
                       transition-all hover:border-cyan-500/50 hover:text-white"
                data-public-menu-button aria-label="{{ __('Open navigation menu') }}" aria-expanded="false">
                <i data-lucide="menu" class="h-4 w-4" data-menu-icon-open></i>
                <i data-lucide="x" class="hidden h-4 w-4" data-menu-icon-close></i>
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════
         MOBILE DROPDOWN (burger menu)
    ══════════════════════════════════════ --}}
    <div class="md:hidden hidden px-4 pb-3 pt-2 sm:px-6" data-public-mobile-menu>
        <div class="space-y-1.5 rounded-2xl border border-zinc-800/80 bg-zinc-950/95 p-3
                    shadow-2xl shadow-black/50 backdrop-blur-xl">

            {{-- Nav items --}}
            @foreach($publicNavigationItems as $item)
                @php($isActive = $item->match_pattern ? request()->is($item->match_pattern) : url()->current() === url($item->url))
                <a href="{{ $item->url }}"
                    @if($item->opens_new_tab) target="_blank" rel="noopener" @endif
                    class="flex items-center justify-between rounded-xl border border-zinc-800/60
                           bg-zinc-900/50 px-4 py-3.5 text-[10px] font-black uppercase tracking-widest
                           transition-all hover:border-cyan-500/30 hover:bg-cyan-500/5
                           {{ $isActive ? 'text-cyan-300 border-cyan-500/30' : 'text-zinc-300' }}">
                    <span>{{ __($item->label) }}</span>
                    @if($item->icon)
                        <i data-lucide="{{ $item->icon }}" class="h-4 w-4"></i>
                    @endif
                </a>
            @endforeach

            {{-- Authenticated extras --}}
            @auth
                <a href="{{ $isStaff ? '/admin/profile' : '/profile' }}"
                    class="flex items-center justify-between rounded-xl border border-zinc-800/60
                           bg-zinc-900/50 px-4 py-3.5 text-[10px] font-black uppercase tracking-widest
                           text-zinc-300 transition-all hover:border-violet-500/30 hover:bg-violet-500/5">
                    <span>{{ __('My Profile') }}</span>
                    <i data-lucide="user" class="h-4 w-4"></i>
                </a>

                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit"
                        class="flex w-full items-center justify-between rounded-xl border border-red-500/20
                               bg-red-500/10 px-4 py-3.5 text-[10px] font-black uppercase tracking-widest
                               text-red-300 transition-all hover:bg-red-500/15">
                        <span>{{ __('Logout') }}</span>
                        <i data-lucide="log-out" class="h-4 w-4"></i>
                    </button>
                </form>
            @endauth

            {{-- PWA Install — mobile only --}}
            <button type="button"
                class="pwa-install-btn hidden w-full items-center justify-center gap-2 rounded-xl
                       border border-fuchsia-500/50 bg-fuchsia-600/20 px-4 py-3.5 text-[10px]
                       font-black uppercase tracking-widest text-fuchsia-300 transition-all
                       hover:bg-fuchsia-600/40 disabled:cursor-not-allowed disabled:opacity-50"
                data-pwa-install-mobile aria-label="{{ __('Install PlayerSaloons app') }}" disabled>
                <i data-lucide="download" class="h-4 w-4"></i>
                <span>{{ __('Install App') }}</span>
            </button>

            <x-localization.language-switcher variant="public" align="left" class="w-full" />
        </div>
    </div>

</header>
