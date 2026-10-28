@php
    $footer = \App\Modules\CMS\Models\LandingSection::query()->where('key', 'footer')->where('is_active', true)->with('activeItems')->first();
    $footerSettings = \App\Modules\Operations\Models\SystemSetting::query()->where('key', 'like', 'footer.%')->pluck('value', 'key');
    $tagline = $footerSettings['footer.tagline'] ?? 'The Premier Competitive Gaming & Tournament Saloon';
    $description = $footerSettings['footer.description'] ?? 'Compete in high-stakes daily tournaments and 1v1 challenges. Transparent brackets, automated payouts, and verified fair play.';
    $disclaimer = $footerSettings['footer.disclaimer'] ?? 'GamersRival is an independent esports competition platform. Game titles, artwork, and trademarks belong to their respective publishers and owners.';
    $copyright = $footerSettings['footer.copyright'] ?? ($footer?->body ?? 'All rights reserved. Operated by GamersRival Systems.');
    $discord = $footerSettings['footer.discord'] ?? 'https://discord.gg/gamersrival';
    $twitter = $footerSettings['footer.twitter'] ?? 'https://x.com/gamersrival';
    $twitch = $footerSettings['footer.twitch'] ?? '';
    $youtube = $footerSettings['footer.youtube'] ?? '';
@endphp
<footer class="gr-footer">
    <div class="border-t border-white/5 bg-[#03060c] text-zinc-400 pt-12 pb-8">
        <div class="gr-wrap max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
        <!-- Main Footer Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8 lg:gap-12">
            <!-- Brand & Mission Column -->
            <div class="lg:col-span-5 space-y-4">
                <a href="/" class="gr-footer-brand inline-flex items-center gap-2 group" aria-label="{{ __('GamersRival home') }}">
                    <span class="font-orbitron font-black text-xl tracking-tight text-white group-hover:text-cyan-400 transition-colors">
                        GAMERS<span class="text-[#c6ff3d]">RIVAL</span>
                    </span>
                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                        V2 Saloon
                    </span>
                </a>
                @if($tagline)
                    <p class="text-xs font-bold uppercase tracking-wider text-zinc-300">
                        {{ __($tagline) }}
                    </p>
                @endif
                @if($description)
                    <p class="text-xs text-zinc-400 leading-relaxed max-w-md">
                        {{ __($description) }}
                    </p>
                @endif

                <!-- Social Links -->
                <div class="flex items-center gap-3 pt-2">
                    @if($discord)
                        <a href="{{ $discord }}" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded-lg bg-white/5 hover:bg-[#c6ff3d]/10 border border-white/10 hover:border-[#c6ff3d]/30 text-zinc-300 hover:text-[#c6ff3d] flex items-center justify-center transition-colors text-xs" aria-label="Discord">
                            <span class="font-black text-[10px]">DC</span>
                        </a>
                    @endif
                    @if($twitter)
                        <a href="{{ $twitter }}" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded-lg bg-white/5 hover:bg-cyan-500/10 border border-white/10 hover:border-cyan-400/30 text-zinc-300 hover:text-cyan-400 flex items-center justify-center transition-colors text-xs" aria-label="X (Twitter)">
                            <span class="font-black text-[10px]">𝕏</span>
                        </a>
                    @endif
                    @if($twitch)
                        <a href="{{ $twitch }}" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded-lg bg-white/5 hover:bg-purple-500/10 border border-white/10 hover:border-purple-400/30 text-zinc-300 hover:text-purple-400 flex items-center justify-center transition-colors text-xs" aria-label="Twitch">
                            <span class="font-black text-[10px]">TV</span>
                        </a>
                    @endif
                    @if($youtube)
                        <a href="{{ $youtube }}" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded-lg bg-white/5 hover:bg-rose-500/10 border border-white/10 hover:border-rose-400/30 text-zinc-300 hover:text-rose-400 flex items-center justify-center transition-colors text-xs" aria-label="YouTube">
                            <span class="font-black text-[10px]">YT</span>
                        </a>
                    @endif
                </div>
            </div>

            <!-- Quick Competition Links -->
            <div class="lg:col-span-3 space-y-3">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-[#c6ff3d]">
                    {{ __('Competition') }}
                </p>
                <nav class="flex flex-col space-y-2 text-xs">
                    <a href="/tournaments" class="hover:text-white transition-colors flex items-center gap-1.5">
                        <span class="text-cyan-400 font-mono">/</span> {{ __('Tournaments') }}
                    </a>
                    <a href="/tournaments" class="hover:text-white transition-colors flex items-center gap-1.5">
                        <span class="text-cyan-400 font-mono">/</span> {{ __('Head to Head Duels') }}
                    </a>
                    <a href="/#games" class="hover:text-white transition-colors flex items-center gap-1.5">
                        <span class="text-cyan-400 font-mono">/</span> {{ __('Supported Games') }}
                    </a>
                    <a href="/#how" class="hover:text-white transition-colors flex items-center gap-1.5">
                        <span class="text-cyan-400 font-mono">/</span> {{ __('How it Works') }}
                    </a>
                </nav>
            </div>

            <!-- Policies & Navigation Links (Dynamic from CMS) -->
            <div class="lg:col-span-4 space-y-3">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-cyan-400">
                    {{ __('Policies & Support') }}
                </p>
                <nav class="grid grid-cols-2 gap-2 text-xs" aria-label="{{ __('Footer navigation') }}">
                    @forelse($footer?->activeItems ?? [] as $item)
                        <a href="{{ $item->url ?: route('policies.index') }}" class="hover:text-white transition-colors flex items-center gap-1.5 truncate">
                            <span class="text-zinc-500 font-mono">·</span> {{ __($item->label ?: $item->title) }}
                        </a>
                    @empty
                        <a href="{{ route('policies.index') }}" class="hover:text-white transition-colors flex items-center gap-1.5">
                            <span class="text-zinc-500 font-mono">·</span> {{ __('Policies') }}
                        </a>
                    @endforelse
                    <a href="{{ route('about') }}" class="hover:text-white transition-colors flex items-center gap-1.5">
                        <span class="text-zinc-500 font-mono">·</span> {{ __('About Us') }}
                    </a>
                    <a href="{{ route('contact') }}" class="hover:text-white transition-colors flex items-center gap-1.5">
                        <span class="text-zinc-500 font-mono">·</span> {{ __('Contact Support') }}
                    </a>
                </nav>
            </div>
        </div>

        @if($disclaimer)
            <!-- Compliance / Disclaimer Notice -->
            <div class="pt-6 border-t border-white/5">
                <p class="text-[11px] leading-relaxed text-zinc-500 max-w-4xl">
                    {{ __($disclaimer) }}
                </p>
            </div>
        @endif

        <!-- Bottom Bar -->
        <div class="gr-footer-bottom pt-6 border-t border-white/5 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-zinc-500">
            <p>&copy; {{ date('Y') }} {{ __($copyright) }}</p>

            <div class="gr-footer-tools flex items-center gap-3">
                <button type="button" class="pwa-install-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/10 bg-white/5 hover:border-white/20 text-zinc-300 hover:text-white text-xs font-semibold transition-colors" data-pwa-install-desktop>
                    <svg class="w-3.5 h-3.5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span data-pwa-install-label>{{ __('Install App') }}</span>
                </button>
                <x-localization.language-switcher variant="public" align="left" />
            </div>
        </div>
    </div>
</footer>
