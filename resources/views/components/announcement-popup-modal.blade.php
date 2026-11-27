@props([
    'surface' => \App\Modules\Community\Models\BroadcastMessage::SURFACE_BOTH,
])

@php
    $popups = \App\Modules\Community\Models\BroadcastMessage::activePopupsPayload($surface);
@endphp

@if($popups !== [])
@once
<script>
window.__grAnnouncementPopup = window.__grAnnouncementPopup || function (items) {
    return {
        storageKey: 'gr_dismissed_announcements_v1',
        allItems: Array.isArray(items) ? items : [],
        items: [],
        index: 0,
        open: false,
        get current() {
            return this.items[this.index] || null;
        },
        get hasMedia() {
            const c = this.current;
            return !!(c && c.media_type !== 'none' && (c.embed_url || c.media_url));
        },
        get isSplitPromo() {
            const c = this.current;
            return this.hasMedia && c && !c.is_media_only && String(c.message || '').trim() !== '';
        },
        get isFullMedia() {
            const c = this.current;
            return this.hasMedia && (!c || c.is_media_only || String(c.message || '').trim() === '');
        },
        readDismissed() {
            try {
                const raw = window.localStorage.getItem(this.storageKey);
                const parsed = raw ? JSON.parse(raw) : [];
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        },
        writeDismissed(uuids) {
            try {
                const merged = Array.from(new Set([...this.readDismissed(), ...uuids])).slice(-50);
                window.localStorage.setItem(this.storageKey, JSON.stringify(merged));
            } catch (e) {}
        },
        stopVideos() {
            this.$el.querySelectorAll('video').forEach((video) => {
                try { video.pause(); } catch (e) {}
            });
        },
        init() {
            const dismissed = this.readDismissed();
            this.items = this.allItems.filter((item) => !dismissed.includes(item.uuid));
            if (this.items.length > 0) {
                this.index = 0;
                this.open = true;
            }
        },
        next() {
            if (!this.current) return;
            this.stopVideos();
            this.writeDismissed([this.current.uuid]);
            if (this.index < this.items.length - 1) {
                this.index++;
            } else {
                this.open = false;
            }
        },
        prev() {
            if (this.index > 0) {
                this.stopVideos();
                this.index--;
            }
        },
        dismissAll() {
            this.stopVideos();
            this.writeDismissed(this.items.map((item) => item.uuid));
            this.open = false;
        },
        dismissCurrentAndFollow() {
            if (this.current) {
                this.writeDismissed([this.current.uuid]);
            }
            this.stopVideos();
            this.open = false;
        },
    };
};
</script>
@endonce
<div
    wire:ignore
    data-announcement-popup-root
    data-surface="{{ $surface }}"
    x-data="window.__grAnnouncementPopup(@js($popups))"
    x-show="open && current"
    x-cloak
    @keydown.escape.window="if (open) dismissAll()"
    class="fixed inset-0 z-[1100] flex items-center justify-center p-3 sm:p-6"
    role="dialog"
    aria-modal="true"
    :aria-labelledby="current ? 'announcement-modal-title-' + current.uuid : null"
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="dismissAll()"
        class="fixed inset-0 bg-black/80 backdrop-blur-md"
        aria-hidden="true"
    ></div>

    {{-- Promotional Showcase Card --}}
    <template x-if="open && current">
        <div
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            :class="isSplitPromo ? 'max-w-4xl' : (isFullMedia ? 'max-w-3xl' : 'max-w-lg')"
            class="relative z-10 flex max-h-[90dvh] w-full flex-col overflow-hidden rounded-3xl border border-cyan-400/30 bg-[#080f1d] text-zinc-100 shadow-[0_32px_90px_rgba(0,0,0,0.85)]"
        >
            {{-- Top Promotional Accent Bar --}}
            <div class="h-1 w-full shrink-0 bg-gradient-to-r from-[#c6ff3d] via-cyan-400 to-[#c6ff3d]" aria-hidden="true"></div>

            {{-- Floating Close Button (always reachable top-right) --}}
            <button
                type="button"
                @click="dismissAll()"
                aria-label="{{ __('Close announcement') }}"
                class="absolute right-3 top-3.5 z-20 inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/15 bg-black/65 text-zinc-200 backdrop-blur-md transition hover:border-white/35 hover:bg-black/85 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#c6ff3d]"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                    <path d="M18 6 6 18" />
                    <path d="m6 6 12 12" />
                </svg>
            </button>

            {{-- MODE 1: Split 2-Column Large Promotional Layout (Desktop: Left Visual / Right Copy; Mobile: Stacked Hero) --}}
            <template x-if="isSplitPromo">
                <div class="min-h-0 flex-1 overflow-y-auto md:grid md:grid-cols-12 md:overflow-hidden">
                    {{-- Left Visual Showcase --}}
                    <div class="relative flex items-center justify-center overflow-hidden bg-black md:col-span-7 md:min-h-[420px]">
                        <template x-if="current.media_type === 'image' && current.media_url">
                            <img
                                :src="current.media_url"
                                :alt="current.title"
                                class="aspect-[16/10] w-full object-cover md:aspect-auto md:h-full"
                                loading="eager"
                            >
                        </template>
                        <template x-if="current.media_type === 'video' && current.embed_url">
                            <div class="aspect-video w-full">
                                <iframe
                                    :src="current.embed_url"
                                    :title="current.title"
                                    class="h-full w-full border-0"
                                    loading="lazy"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            </div>
                        </template>
                        <template x-if="current.media_type === 'video' && !current.embed_url && current.media_url">
                            <div class="aspect-video w-full">
                                <video
                                    :src="current.media_url"
                                    controls
                                    playsinline
                                    preload="metadata"
                                    class="h-full w-full bg-black object-contain"
                                ></video>
                            </div>
                        </template>

                        {{-- Subtle visual vignette & promo badge overlay on image --}}
                        <template x-if="current.media_type === 'image'">
                            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#080f1d]/70 via-transparent to-black/25 md:bg-gradient-to-r md:from-transparent md:via-transparent md:to-[#080f1d]/40"></div>
                        </template>
                        <div class="pointer-events-none absolute left-4 top-4 z-10 flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#c6ff3d]/50 bg-black/75 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-[#c6ff3d] backdrop-blur-md">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#c6ff3d]" aria-hidden="true"></span>
                                <span x-text="current.badge_label"></span>
                            </span>
                        </div>
                    </div>

                    {{-- Right Editorial Copy & Actions --}}
                    <div class="flex flex-col justify-between bg-[radial-gradient(circle_at_top_right,rgba(34,230,255,0.1),transparent_55%),#080f1d] p-5 sm:p-6 md:col-span-5 md:overflow-y-auto lg:p-7">
                        <div class="space-y-3 md:pr-8">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-black uppercase tracking-[0.22em] text-cyan-300">{{ __('Featured Announcement') }}</span>
                                <template x-if="items.length > 1">
                                    <span class="rounded-full border border-white/10 bg-white/5 px-2 py-0.5 text-[10px] font-bold tabular-nums text-zinc-300" x-text="(index + 1) + ' / ' + items.length"></span>
                                </template>
                            </div>

                            <h2
                                :id="'announcement-modal-title-' + current.uuid"
                                class="break-words font-orbitron text-lg font-black uppercase leading-tight text-white sm:text-xl lg:text-2xl"
                                x-text="current.title"
                            ></h2>

                            <p class="whitespace-pre-line break-words text-sm leading-relaxed text-zinc-200" x-text="current.message"></p>

                            <template x-if="current.published_at">
                                <p class="text-[11px] font-medium text-zinc-500" x-text="current.published_at"></p>
                            </template>
                        </div>

                        <div class="mt-6 space-y-2.5 border-t border-white/10 pt-4 pb-[max(0.25rem,env(safe-area-inset-bottom))]">
                            <template x-if="current.cta_url">
                                <a
                                    :href="current.cta_url"
                                    @click="dismissCurrentAndFollow()"
                                    class="inline-flex min-h-[46px] w-full items-center justify-center gap-2 rounded-xl bg-[#c6ff3d] px-5 py-3 font-orbitron text-xs font-black uppercase tracking-wider text-[#080f16] shadow-[0_10px_28px_rgba(198,255,61,0.25)] transition hover:brightness-105 active:scale-[0.99]"
                                >
                                    <span x-text="current.cta_label || @js(__('Explore Now'))"></span>
                                    <span aria-hidden="true">→</span>
                                </a>
                            </template>

                            <div class="flex items-center justify-between gap-2">
                                <template x-if="items.length > 1">
                                    <div class="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="prev()"
                                            :disabled="index === 0"
                                            class="inline-flex min-h-[40px] items-center justify-center rounded-xl border border-white/10 bg-white/5 px-3 text-xs font-semibold text-zinc-200 transition hover:bg-white/10 disabled:opacity-40"
                                        >{{ __('Back') }}</button>
                                        <div class="flex items-center gap-1 px-1" aria-hidden="true">
                                            <template x-for="(dot, dotIndex) in items" :key="dot.uuid">
                                                <span class="h-1.5 rounded-full transition-all" :class="dotIndex === index ? 'w-4 bg-[#c6ff3d]' : 'w-1.5 bg-zinc-600'"></span>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                                <button
                                    type="button"
                                    @click="index < items.length - 1 ? next() : dismissAll()"
                                    class="inline-flex min-h-[42px] flex-1 items-center justify-center rounded-xl border border-white/15 bg-white/5 px-4 py-2 text-xs font-bold uppercase tracking-wider text-zinc-200 transition hover:border-white/30 hover:bg-white/10 hover:text-white"
                                    x-text="index < items.length - 1 ? @js(__('Next')) : @js(__('Got it'))"
                                ></button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- MODE 2: Full-Bleed Media-Only Showcase (Full Photo or Video + Sleek Caption Bar) --}}
            <template x-if="isFullMedia">
                <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <div class="relative min-h-0 flex-1 overflow-y-auto bg-black">
                        <template x-if="current.media_type === 'image' && current.media_url">
                            <img
                                :src="current.media_url"
                                :alt="current.title"
                                class="max-h-[62dvh] w-full object-cover"
                                loading="eager"
                            >
                        </template>
                        <template x-if="current.media_type === 'video' && current.embed_url">
                            <div class="aspect-video w-full">
                                <iframe
                                    :src="current.embed_url"
                                    :title="current.title"
                                    class="h-full w-full border-0"
                                    loading="lazy"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            </div>
                        </template>
                        <template x-if="current.media_type === 'video' && !current.embed_url && current.media_url">
                            <div class="aspect-video w-full">
                                <video
                                    :src="current.media_url"
                                    controls
                                    playsinline
                                    preload="metadata"
                                    class="h-full w-full bg-black object-contain"
                                ></video>
                            </div>
                        </template>
                    </div>

                    {{-- Caption & Action Footer --}}
                    <div class="shrink-0 space-y-3 border-t border-white/10 bg-[#080f1d] px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-6">
                        <div class="flex flex-wrap items-center justify-between gap-2 pr-8">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-[#c6ff3d]/40 bg-[#c6ff3d]/10 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-widest text-[#c6ff3d]">
                                        <span class="h-1.5 w-1.5 rounded-full bg-[#c6ff3d]" aria-hidden="true"></span>
                                        <span x-text="current.badge_label"></span>
                                    </span>
                                    <template x-if="items.length > 1">
                                        <span class="text-[11px] font-semibold tabular-nums text-zinc-400" x-text="(index + 1) + ' / ' + items.length"></span>
                                    </template>
                                </div>
                                <h2
                                    :id="'announcement-modal-title-' + current.uuid"
                                    class="mt-1.5 break-words font-orbitron text-base font-black uppercase leading-snug text-white sm:text-xl"
                                    x-text="current.title"
                                ></h2>
                            </div>
                        </div>

                        <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center justify-between gap-2 sm:justify-start">
                                <template x-if="items.length > 1">
                                    <div class="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="prev()"
                                            :disabled="index === 0"
                                            class="inline-flex min-h-[40px] items-center justify-center rounded-xl border border-white/10 bg-white/5 px-3 text-xs font-semibold text-zinc-200 transition hover:bg-white/10 disabled:opacity-40"
                                        >{{ __('Back') }}</button>
                                        <div class="flex items-center gap-1 px-1" aria-hidden="true">
                                            <template x-for="(dot, dotIndex) in items" :key="dot.uuid">
                                                <span class="h-1.5 rounded-full transition-all" :class="dotIndex === index ? 'w-4 bg-[#c6ff3d]' : 'w-1.5 bg-zinc-600'"></span>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                                <button
                                    type="button"
                                    @click="index < items.length - 1 ? next() : dismissAll()"
                                    class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-xl border border-white/15 bg-white/5 px-4 py-2 text-xs font-bold uppercase tracking-wider text-zinc-200 transition hover:border-white/30 hover:bg-white/10 hover:text-white sm:flex-initial"
                                    x-text="index < items.length - 1 ? @js(__('Next')) : @js(__('Got it'))"
                                ></button>
                            </div>

                            <template x-if="current.cta_url">
                                <a
                                    :href="current.cta_url"
                                    @click="dismissCurrentAndFollow()"
                                    class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-[#c6ff3d] px-5 py-2.5 font-orbitron text-xs font-black uppercase tracking-wider text-[#080f16] shadow-[0_8px_24px_rgba(198,255,61,0.25)] transition hover:brightness-105 active:scale-[0.99] sm:w-auto"
                                >
                                    <span x-text="current.cta_label || @js(__('Learn More'))"></span>
                                    <span aria-hidden="true">→</span>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            {{-- MODE 3: Text-Only Promotional Card --}}
            <template x-if="!hasMedia">
                <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-[radial-gradient(circle_at_top_right,rgba(198,255,61,0.08),transparent_50%),#080f1d]">
                    <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-5 pr-14 sm:px-7 sm:py-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#c6ff3d]/40 bg-[#c6ff3d]/10 px-3 py-0.5 text-[10px] font-black uppercase tracking-widest text-[#c6ff3d]">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#c6ff3d]" aria-hidden="true"></span>
                                <span x-text="current.badge_label"></span>
                            </span>
                            <template x-if="items.length > 1">
                                <span class="text-[11px] font-semibold tabular-nums text-zinc-400" x-text="(index + 1) + ' / ' + items.length"></span>
                            </template>
                        </div>
                        <h2
                            :id="'announcement-modal-title-' + current.uuid"
                            class="break-words font-orbitron text-lg font-black uppercase leading-snug text-white sm:text-2xl"
                            x-text="current.title"
                        ></h2>
                        <p class="whitespace-pre-line break-words text-sm leading-relaxed text-zinc-200" x-text="current.message"></p>
                        <template x-if="current.published_at">
                            <p class="text-[11px] font-medium text-zinc-500" x-text="current.published_at"></p>
                        </template>
                    </div>

                    <div class="flex shrink-0 flex-col-reverse gap-2.5 border-t border-white/10 bg-[#060b16] px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:flex-row sm:items-center sm:justify-between sm:px-7">
                        <div class="flex items-center justify-between gap-2 sm:justify-start">
                            <template x-if="items.length > 1">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="prev()"
                                        :disabled="index === 0"
                                        class="inline-flex min-h-[40px] items-center justify-center rounded-xl border border-white/10 bg-white/5 px-3 text-xs font-semibold text-zinc-200 transition hover:bg-white/10 disabled:opacity-40"
                                    >{{ __('Back') }}</button>
                                    <div class="flex items-center gap-1 px-1" aria-hidden="true">
                                        <template x-for="(dot, dotIndex) in items" :key="dot.uuid">
                                            <span class="h-1.5 rounded-full transition-all" :class="dotIndex === index ? 'w-4 bg-[#c6ff3d]' : 'w-1.5 bg-zinc-600'"></span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <button
                                type="button"
                                @click="index < items.length - 1 ? next() : dismissAll()"
                                class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-xl border border-white/15 bg-white/5 px-4 py-2 text-xs font-bold uppercase tracking-wider text-zinc-200 transition hover:border-white/30 hover:bg-white/10 hover:text-white sm:flex-initial"
                                x-text="index < items.length - 1 ? @js(__('Next')) : @js(__('Got it'))"
                            ></button>
                        </div>

                        <template x-if="current.cta_url">
                            <a
                                :href="current.cta_url"
                                @click="dismissCurrentAndFollow()"
                                class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-[#c6ff3d] px-5 py-2.5 font-orbitron text-xs font-black uppercase tracking-wider text-[#080f16] transition hover:brightness-105 active:scale-[0.99] sm:w-auto"
                            >
                                <span x-text="current.cta_label || @js(__('Learn More'))"></span>
                                <span aria-hidden="true">→</span>
                            </a>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
@endif
