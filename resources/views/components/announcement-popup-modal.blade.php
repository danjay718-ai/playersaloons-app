@props([
    'surface' => \App\Modules\Community\Models\BroadcastMessage::SURFACE_BOTH,
])

@php
    $popups = \App\Modules\Community\Models\BroadcastMessage::activePopupsPayload($surface);
@endphp

@if($popups !== [])
<div
    wire:ignore
    data-announcement-popup-root
    data-surface="{{ $surface }}"
    x-data="{
        storageKey: 'gr_dismissed_announcements_v1',
        allItems: @js($popups),
        items: [],
        index: 0,
        open: false,
        get current() {
            return this.items[this.index] || null;
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
        }
    }"
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
        class="fixed inset-0 bg-black/75 backdrop-blur-sm"
        aria-hidden="true"
    ></div>

    {{-- Modal panel --}}
    <template x-if="open && current">
        <div
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            :class="current.is_video_only ? 'max-w-2xl' : 'max-w-lg'"
            class="relative z-10 flex max-h-[88dvh] w-full flex-col overflow-hidden rounded-2xl border border-cyan-400/25 bg-[#0b1322] text-zinc-100 shadow-[0_24px_70px_rgba(0,0,0,0.75)]"
        >
            {{-- Header --}}
            <div class="flex items-start justify-between gap-3 border-b border-white/10 px-4 py-3.5 sm:px-6 sm:py-4">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#c6ff3d]/35 bg-[#c6ff3d]/10 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-widest text-[#c6ff3d]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#c6ff3d]" aria-hidden="true"></span>
                            <span x-text="current.badge_label"></span>
                        </span>
                        <template x-if="items.length > 1">
                            <span class="text-[11px] font-semibold tabular-nums text-zinc-400" x-text="(index + 1) + ' / ' + items.length"></span>
                        </template>
                    </div>
                    <h2
                        :id="'announcement-modal-title-' + current.uuid"
                        class="mt-2 break-words font-orbitron text-base font-black leading-snug text-white sm:text-xl"
                        x-text="current.title"
                    ></h2>
                </div>

                <button
                    type="button"
                    @click="dismissAll()"
                    aria-label="{{ __('Close announcement') }}"
                    class="-mr-1 -mt-1 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-zinc-300 transition hover:border-white/25 hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#c6ff3d]"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                    </svg>
                </button>
            </div>

            {{-- Scrollable Body --}}
            <div class="flex-1 overflow-y-auto overscroll-contain">
                {{-- Video media --}}
                <template x-if="current.media_type === 'video' && (current.embed_url || current.media_url)">
                    <div class="w-full bg-black" :class="current.is_video_only ? '' : 'border-b border-white/10'">
                        <template x-if="current.embed_url">
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
                        <template x-if="!current.embed_url && current.media_url">
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
                </template>

                {{-- Image media --}}
                <template x-if="current.media_type === 'image' && current.media_url">
                    <div class="w-full border-b border-white/10 bg-zinc-950">
                        <img
                            :src="current.media_url"
                            :alt="current.title"
                            class="aspect-[16/9] max-h-64 w-full object-cover"
                            loading="eager"
                        >
                    </div>
                </template>

                {{-- Text body (shown for non-video-only or when message is provided) --}}
                <template x-if="!current.is_video_only && current.message">
                    <div class="px-4 py-4 sm:px-6 sm:py-5">
                        <p class="whitespace-pre-line break-words text-sm leading-relaxed text-zinc-200" x-text="current.message"></p>
                        <template x-if="current.published_at">
                            <p class="mt-3 text-[11px] font-medium text-zinc-500" x-text="current.published_at"></p>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Footer Actions --}}
            <div class="flex flex-col-reverse gap-2.5 border-t border-white/10 bg-[#080e1a] px-4 py-3.5 pb-[max(0.875rem,env(safe-area-inset-bottom))] sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:py-4">
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
                                    <span
                                        class="h-1.5 rounded-full transition-all"
                                        :class="dotIndex === index ? 'w-4 bg-[#c6ff3d]' : 'w-1.5 bg-zinc-600'"
                                    ></span>
                                </template>
                            </div>
                        </div>
                    </template>
                    <button
                        type="button"
                        @click="index < items.length - 1 ? next() : dismissAll()"
                        class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-xl border border-white/15 bg-white/5 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-zinc-200 transition hover:border-white/30 hover:bg-white/10 hover:text-white active:scale-[0.99] sm:flex-initial"
                        x-text="index < items.length - 1 ? @js(__('Next')) : @js(__('Got it'))"
                    ></button>
                </div>

                <template x-if="current.cta_url">
                    <a
                        :href="current.cta_url"
                        @click="dismissCurrentAndFollow()"
                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-[#c6ff3d] px-5 py-2.5 text-xs font-black uppercase tracking-wider text-[#080f16] transition hover:brightness-105 active:scale-[0.99] sm:w-auto"
                    >
                        <span x-text="current.cta_label || @js(__('Learn More'))"></span>
                        <span aria-hidden="true">→</span>
                    </a>
                </template>
            </div>
        </div>
    </template>
</div>
@endif
