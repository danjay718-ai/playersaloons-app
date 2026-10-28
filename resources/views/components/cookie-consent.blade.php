@php
    $cookieSettings = \App\Modules\Operations\Models\SystemSetting::query()->where('key', 'like', 'cookie.%')->pluck('value', 'key');
    $enabled = filter_var($cookieSettings['cookie.consent_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $title = $cookieSettings['cookie.title'] ?? __('Cookie & Privacy Preferences');
    $message = $cookieSettings['cookie.message'] ?? __('We use cookies and similar technologies to enhance your gaming experience, secure your account, prevent fraud, and analyze site performance.');
    $policyUrl = $cookieSettings['cookie.policy_url'] ?? '/policies/cookie-policy';
    $allowAnalytics = filter_var($cookieSettings['cookie.analytics_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $allowMarketing = filter_var($cookieSettings['cookie.marketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $essentialNote = $cookieSettings['cookie.essential_note'] ?? __('Strictly necessary cookies required for session security and anti-cheat cannot be disabled.');
@endphp

@if($enabled)
<div x-data="{
        showBanner: false,
        showCustomize: false,
        analytics: true,
        marketing: false,
        init() {
            try {
                const saved = localStorage.getItem('gr_cookie_consent');
                if (!saved) {
                    setTimeout(() => { this.showBanner = true; }, 600);
                } else {
                    const parsed = JSON.parse(saved);
                    this.analytics = !!parsed.analytics;
                    this.marketing = !!parsed.marketing;
                }
            } catch (e) {
                this.showBanner = true;
            }
        },
        save(consent) {
            try {
                localStorage.setItem('gr_cookie_consent', JSON.stringify(consent));
                document.cookie = 'gr_cookie_consent=' + encodeURIComponent(JSON.stringify(consent)) + '; max-age=31536000; path=/; SameSite=Lax';
                window.dispatchEvent(new CustomEvent('cookie-consent-updated', { detail: consent }));
            } catch (e) {}
            this.showBanner = false;
        },
        acceptAll() {
            this.save({ essential: true, analytics: true, marketing: true, timestamp: Date.now() });
        },
        rejectNonEssential() {
            this.save({ essential: true, analytics: false, marketing: false, timestamp: Date.now() });
        },
        saveCustom() {
            this.save({ essential: true, analytics: this.analytics, marketing: this.marketing, timestamp: Date.now() });
        }
    }"
    x-show="showBanner"
    x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-8 scale-95"
    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
    x-transition:leave-end="opacity-0 translate-y-8 scale-95"
    class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 sm:max-w-lg z-[999] bg-[#070c16]/95 border border-cyan-400/20 backdrop-blur-xl rounded-2xl shadow-[0_0_40px_rgba(0,0,0,0.85)] p-5 text-white"
    role="dialog"
    aria-labelledby="cookie-title">

    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-start gap-3">
            <div class="shrink-0 w-9 h-9 rounded-xl bg-amber-400/10 border border-amber-400/30 flex items-center justify-center text-amber-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2a10 10 0 1010 10A10 10 0 0012 2zm-1 5a1 1 0 112 0 1 1 0 01-2 0zm-4 4a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm8 2a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm-5 4a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 id="cookie-title" class="font-orbitron text-sm font-bold tracking-wide text-white">
                    {{ $title }}
                </h3>
                <p class="mt-1 text-xs text-zinc-300 leading-relaxed">
                    {{ $message }}
                    <a href="{{ $policyUrl }}" class="text-[#c6ff3d] hover:underline font-semibold ml-1">
                        {{ __('Cookie Policy') }} ↗
                    </a>
                </p>
            </div>
        </div>

        <!-- Custom Preferences Drawer -->
        <div x-show="showCustomize" x-collapse class="space-y-3 pt-3 border-t border-white/10 text-xs">
            <!-- Essential -->
            <div class="flex items-center justify-between p-2.5 rounded-xl bg-white/5 border border-white/5">
                <div>
                    <span class="font-bold text-white block">{{ __('Strictly Necessary') }}</span>
                    <span class="text-[11px] text-zinc-400 block">{{ $essentialNote }}</span>
                </div>
                <span class="text-[10px] font-black uppercase tracking-wider text-[#c6ff3d] bg-[#c6ff3d]/10 px-2 py-0.5 rounded border border-[#c6ff3d]/20 shrink-0">
                    {{ __('Always Active') }}
                </span>
            </div>

            @if($allowAnalytics)
                <!-- Analytics -->
                <label class="flex items-center justify-between p-2.5 rounded-xl bg-white/5 border border-white/5 cursor-pointer hover:border-white/10 transition-colors">
                    <div>
                        <span class="font-bold text-white block">{{ __('Analytics & Performance') }}</span>
                        <span class="text-[11px] text-zinc-400 block">{{ __('Help us optimize tournament brackets, reduce latency, and track feature usage.') }}</span>
                    </div>
                    <input type="checkbox" x-model="analytics" class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-cyan-400 focus:ring-0 shrink-0 ml-3">
                </label>
            @endif

            @if($allowMarketing)
                <!-- Marketing -->
                <label class="flex items-center justify-between p-2.5 rounded-xl bg-white/5 border border-white/5 cursor-pointer hover:border-white/10 transition-colors">
                    <div>
                        <span class="font-bold text-white block">{{ __('Marketing & Promotions') }}</span>
                        <span class="text-[11px] text-zinc-400 block">{{ __('Allow tournament sponsors and promotional campaign attribution.') }}</span>
                    </div>
                    <input type="checkbox" x-model="marketing" class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-cyan-400 focus:ring-0 shrink-0 ml-3">
                </label>
            @endif
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2 pt-1">
            <template x-if="!showCustomize">
                <div class="flex flex-wrap items-center gap-2 w-full">
                    <button type="button"
                            @click="acceptAll()"
                            class="flex-1 px-4 py-2 rounded-xl bg-[#c6ff3d] hover:bg-[#b0f025] text-black font-black uppercase text-xs tracking-wider transition-colors shadow-[0_0_15px_rgba(198,255,61,0.25)]">
                        {{ __('Accept All') }}
                    </button>
                    <button type="button"
                            @click="rejectNonEssential()"
                            class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-zinc-300 hover:text-white text-xs font-semibold transition-colors">
                        {{ __('Essential Only') }}
                    </button>
                    <button type="button"
                            @click="showCustomize = true"
                            class="px-3 py-2 rounded-xl text-zinc-400 hover:text-cyan-400 text-xs font-semibold transition-colors">
                        {{ __('Customize') }}
                    </button>
                </div>
            </template>

            <template x-if="showCustomize">
                <div class="flex items-center justify-between gap-2 w-full">
                    <button type="button"
                            @click="showCustomize = false"
                            class="px-3 py-2 rounded-xl text-zinc-400 hover:text-white text-xs font-semibold transition-colors">
                        ← {{ __('Back') }}
                    </button>
                    <button type="button"
                            @click="saveCustom()"
                            class="px-4 py-2 rounded-xl bg-cyan-400 hover:bg-cyan-300 text-black font-black uppercase text-xs tracking-wider transition-colors shadow-[0_0_15px_rgba(34,230,255,0.25)]">
                        {{ __('Save Preferences') }}
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>
@endif
