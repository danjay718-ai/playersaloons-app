{{-- Twitch-style Stream Browse Page --}}
<div
    x-data="{
        featuredIndex: 0,
        featuredStreams: @js($featuredStreams->map(fn($s) => ['id' => $s->id, 'title' => $s->title ?? 'Live Stream', 'description' => $s->description ?? '', 'thumbnail_url' => $s->thumbnail_url, 'viewer_count' => $s->viewer_count, 'total_views' => $s->total_views, 'streamer' => $s->user?->profile?->display_name ?? $s->user?->username ?? 'Player', 'game' => $s->game?->localizedName() ?? 'Gaming', 'provider' => $s->provider, 'badge' => $s->is_live ? 'LIVE' : 'VOD'])->values()),
        autoplay: null,
        showMyStream: false,
        modalStream: null,

        init() {
            if (this.featuredStreams.length > 1) {
                this.startAutoplay();
            }
        },
        startAutoplay() {
            this.autoplay = setInterval(() => { this.next(); }, 7000);
        },
        stopAutoplay() {
            if (this.autoplay) { clearInterval(this.autoplay); this.autoplay = null; }
        },
        next() {
            this.featuredIndex = (this.featuredIndex + 1) % this.featuredStreams.length;
        },
        prev() {
            this.featuredIndex = (this.featuredIndex - 1 + this.featuredStreams.length) % this.featuredStreams.length;
        },
        goTo(i) {
            this.stopAutoplay();
            this.featuredIndex = i;
        },
        currentFeatured() {
            return this.featuredStreams[this.featuredIndex] ?? null;
        },
        formatViewers(n) {
            if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
            if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
            return String(n);
        }
    }"
    class="player-streams flex flex-col gap-0 -mt-4 sm:-mt-6 md:-mt-8 -mx-4 sm:-mx-6 md:-mx-8"
>
    <x-ui.toasts />

    {{-- ═══════════════════════════════════════════════
         FEATURED STREAM HERO CAROUSEL
    ═══════════════════════════════════════════════ --}}
    @if(! $isAdminView && $featuredStreams->isNotEmpty())
        <div
            class="relative w-full overflow-hidden bg-black"
            style="min-height: 280px;"
            @mouseenter="stopAutoplay()"
            @mouseleave="startAutoplay()"
        >
            {{-- Background thumbnail blur --}}
            <div class="absolute inset-0 z-0">
                <template x-if="currentFeatured()?.thumbnail_url">
                    <img :src="currentFeatured().thumbnail_url" alt="" class="absolute inset-0 h-full w-full scale-105 object-cover opacity-60 blur-sm">
                </template>
                <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-[#05030c]"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-transparent to-transparent"></div>
            </div>

            {{-- Hero Content --}}
            <div class="relative z-10 flex flex-col md:flex-row items-stretch min-h-[280px] md:min-h-[360px] lg:min-h-[420px]">

                {{-- Left: Info Panel --}}
                <div class="flex flex-col justify-end md:justify-center w-full md:w-[42%] px-4 sm:px-6 md:px-10 py-6 md:py-10 gap-3">
                    {{-- LIVE badge --}}
                    <template x-if="currentFeatured()?.badge === 'LIVE'">
                        <div class="flex items-center gap-2">
                            <span class="flex items-center gap-1.5 bg-red-600 text-white text-[10px] font-black tracking-[0.2em] uppercase px-2.5 py-1 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                                LIVE
                            </span>
                        </div>
                    </template>
                    <template x-if="currentFeatured()?.badge !== 'LIVE'">
                        <div class="flex items-center gap-2">
                            <span class="flex items-center gap-1.5 bg-zinc-700 text-zinc-300 text-[10px] font-black tracking-[0.2em] uppercase px-2.5 py-1 rounded-full">VOD</span>
                        </div>
                    </template>

                    {{-- Game tag --}}
                    <p class="text-purple-400 text-xs font-bold uppercase tracking-wider" x-text="currentFeatured()?.game"></p>

                    {{-- Title --}}
                    <h1 class="text-2xl sm:text-3xl md:text-4xl font-black text-white leading-tight line-clamp-2" x-text="currentFeatured()?.title"></h1>

                    {{-- Streamer --}}
                    <p class="text-zinc-400 text-sm" x-text="'by ' + (currentFeatured()?.streamer ?? '')"></p>

                    {{-- Viewers --}}
                    <div class="flex items-center gap-4 text-sm">
                        <span class="flex items-center gap-1.5 text-red-400 font-bold">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                            <span x-text="formatViewers(currentFeatured()?.viewer_count ?? 0) + ' viewers'"></span>
                        </span>
                        <span class="text-zinc-500 text-xs" x-text="formatViewers(currentFeatured()?.total_views ?? 0) + ' total views'"></span>
                    </div>

                    {{-- CTA --}}
                    <div class="flex flex-wrap gap-3 mt-1">
                        <a
                            :href="'/streams/' + (currentFeatured()?.id ?? '')"
                            wire:navigate
                            class="inline-flex items-center gap-2 bg-purple-600 hover:bg-purple-500 text-white font-black text-xs uppercase tracking-widest px-5 py-3 rounded-xl transition-all duration-200 shadow-[0_0_20px_rgba(147,51,234,0.4)]"
                        >
                            <i data-lucide="play" class="w-4 h-4"></i>
                            Watch Stream
                        </a>
                    </div>
                </div>

                {{-- Right: Slider Navigation & Dots --}}
                <div class="hidden md:flex flex-col items-center justify-center w-[58%] relative">
                    {{-- Arrow buttons --}}
                    @if($featuredStreams->count() > 1)
                    <button
                        @click="prev()"
                        class="absolute left-2 z-20 w-10 h-10 rounded-full bg-black/60 hover:bg-purple-900/80 border border-white/10 flex items-center justify-center text-white transition-all duration-200 hover:border-purple-500/50"
                        aria-label="Previous"
                    >
                        <i data-lucide="chevron-left" class="w-5 h-5"></i>
                    </button>
                    <button
                        @click="next()"
                        class="absolute right-2 z-20 w-10 h-10 rounded-full bg-black/60 hover:bg-purple-900/80 border border-white/10 flex items-center justify-center text-white transition-all duration-200 hover:border-purple-500/50"
                        aria-label="Next"
                    >
                        <i data-lucide="chevron-right" class="w-5 h-5"></i>
                    </button>
                    @endif

                    {{-- Slide thumbnails strip --}}
                    <div class="flex gap-3 overflow-x-auto px-8 py-4 no-scrollbar w-full justify-center">
                        <template x-for="(s, i) in featuredStreams" :key="i">
                            <button
                                @click="goTo(i)"
                                :class="featuredIndex === i ? 'ring-2 ring-purple-500 opacity-100 scale-105' : 'opacity-50 hover:opacity-80'"
                                class="flex-shrink-0 w-24 sm:w-28 rounded-lg overflow-hidden transition-all duration-300 cursor-pointer"
                            >
                                <div class="aspect-video bg-zinc-900 relative">
                                    <template x-if="s.thumbnail_url"><img :src="s.thumbnail_url" alt="" class="absolute inset-0 h-full w-full object-cover"></template>
                                    <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-purple-900/50 to-fuchsia-900/50">
                                        <i data-lucide="tv" class="w-6 h-6 text-purple-400"></i>
                                    </div>
                                    <div class="absolute bottom-0 left-0 right-0 p-1 bg-gradient-to-t from-black/80">
                                        <p class="text-[9px] text-white font-bold truncate" x-text="s.streamer"></p>
                                    </div>
                                    <template x-if="s.badge === 'LIVE'">
                                        <span class="absolute top-1 left-1 bg-red-600 text-white text-[8px] font-black px-1.5 py-0.5 rounded-full">LIVE</span>
                                    </template>
                                </div>
                            </button>
                        </template>
                    </div>

                    {{-- Dots --}}
                    @if($featuredStreams->count() > 1)
                    <div class="flex items-center gap-1.5 mt-1">
                        <template x-for="(s, i) in featuredStreams" :key="'dot' + i">
                            <button
                                @click="goTo(i)"
                                :class="featuredIndex === i ? 'bg-purple-500 w-4' : 'bg-zinc-600 w-2 hover:bg-zinc-400'"
                                class="h-2 rounded-full transition-all duration-300"
                            ></button>
                        </template>
                    </div>
                    @endif
                </div>

                {{-- Mobile arrows --}}
                @if($featuredStreams->count() > 1)
                <div class="md:hidden absolute bottom-16 right-4 flex gap-2 z-20">
                    <button @click="prev()" class="w-8 h-8 rounded-full bg-black/70 border border-white/10 flex items-center justify-center text-white">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </button>
                    <button @click="next()" class="w-8 h-8 rounded-full bg-black/70 border border-white/10 flex items-center justify-center text-white">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </button>
                </div>
                @endif
            </div>

            {{-- Progress bar --}}
            <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-zinc-800 z-20">
                <div class="h-full bg-gradient-to-r from-purple-500 to-fuchsia-500 transition-all duration-300"
                     :style="'width: ' + (((featuredIndex + 1) / Math.max(featuredStreams.length, 1)) * 100) + '%'"></div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════
         MAIN PAGE CONTENT (padded back)
    ═══════════════════════════════════════════════ --}}
    <div class="px-4 sm:px-6 md:px-8 py-6 md:py-8 space-y-10">

        {{-- ── Admin page header ── --}}
        @if($isAdminView)
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.3em] text-indigo-400">Broadcast Center</p>
                    <h1 class="mt-2 text-3xl md:text-4xl font-extrabold tracking-tight text-white uppercase">Stream Moderation</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-400">Review player-created streams and take down abusive or invalid broadcasts.</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-900 text-slate-400 px-4 py-3 text-[10px] font-black uppercase tracking-widest">
                    {{ $playerStreams->total() }} stream{{ $playerStreams->total() === 1 ? '' : 's' }}
                </div>
            </div>
        @endif

        {{-- ── Player: My Stream setup ── --}}
        @if(! $isAdminView && auth()->user()?->hasRole('PLAYER'))
            @php
                $ownStream = $ownStreams->first();
                $streamTakenDown = $ownStreams->whereNotNull('taken_down_at')->isNotEmpty();
            @endphp

            <div x-data="{ open: @js((bool)$ownStream) }">
                <button
                    @click="open = !open"
                    class="flex items-center gap-2 mb-4 text-sm font-black uppercase tracking-widest text-zinc-400 hover:text-white transition-colors"
                >
                    <i data-lucide="settings-2" class="w-4 h-4"></i>
                    <span x-text="open ? 'Hide My Stream Settings' : 'Set Up My Stream'"></span>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="open && 'rotate-180'"></i>
                </button>

                <section
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="rounded-2xl border border-purple-500/20 bg-zinc-950/80 p-5 md:p-6 mb-4"
                    x-cloak
                >
                    <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xl font-black font-orbitron uppercase tracking-widest text-white">My Stream</h2>
                            <p class="mt-1 text-xs text-zinc-500">Add at least one public stream URL. Select the game category so viewers can find you.</p>
                        </div>
                        <label class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-zinc-400">
                            <input type="checkbox" wire:model="is_public" @disabled($streamTakenDown) class="rounded border-zinc-700 bg-zinc-900 text-purple-500 focus:ring-purple-500">
                            Public
                        </label>
                    </div>

                    @if($streamTakenDown)
                        <div class="mb-5 rounded-xl border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-200">
                            Your stream is currently taken down by admin review.
                            @if($ownStream?->takedown_reason)
                                <span class="block text-xs text-rose-200/80 mt-1">Reason: {{ $ownStream->takedown_reason }}</span>
                            @endif
                        </div>
                    @endif

                    <form wire:submit.prevent="savePlayerStream" class="space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-zinc-500">Stream Title</label>
                                <input type="text" wire:model="streamTitle" @disabled($streamTakenDown) placeholder="e.g. Ranked grind with viewers" class="w-full rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-100 focus:border-purple-500 focus:outline-none disabled:opacity-60">
                                @error('streamTitle') <span class="mt-1 block text-xs text-rose-400">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-zinc-500">Game Category</label>
                                <select wire:model="game_id" @disabled($streamTakenDown) class="w-full rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-100 focus:border-purple-500 focus:outline-none disabled:opacity-60">
                                    <option value="">— No Game —</option>
                                    @foreach($allGames as $game)
                                        <option value="{{ $game->id }}">{{ $game->localizedName() }}</option>
                                    @endforeach
                                </select>
                                @error('game_id') <span class="mt-1 block text-xs text-rose-400">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-zinc-500">Stream Description</label>
                            <textarea wire:model="streamDescription" @disabled($streamTakenDown) rows="2" placeholder="Tell viewers what this stream is about..." class="w-full rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-100 focus:border-purple-500 focus:outline-none disabled:opacity-60 resize-none"></textarea>
                            @error('streamDescription') <span class="mt-1 block text-xs text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_180px] sm:items-end">
                            <div>
                                <x-forms.image-crop-upload model="streamThumbnail" label="Stream Thumbnail" :width="960" :height="540" :max-mb="2" :disabled="$streamTakenDown" />
                            </div>
                            @if($streamThumbnail || $thumbnailUrl)
                                <div class="aspect-video overflow-hidden rounded-xl border border-zinc-800 bg-black">
                                    <img src="{{ $streamThumbnail ? $streamThumbnail->temporaryUrl() : $thumbnailUrl }}" alt="Stream thumbnail preview" class="h-full w-full object-cover">
                                </div>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-zinc-500 flex items-center gap-1.5">
                                    <span class="text-red-400">▶</span> YouTube URL
                                </label>
                                <input type="url" wire:model="youtube_stream_url" @disabled($streamTakenDown) placeholder="https://www.youtube.com/watch?v=..." class="w-full rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-100 focus:border-red-500 focus:outline-none disabled:opacity-60">
                                @error('youtube_stream_url') <span class="mt-1 block text-xs text-rose-400">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-zinc-500 flex items-center gap-1.5">
                                    <span class="text-purple-400">◈</span> Twitch URL
                                </label>
                                <input type="url" wire:model="twitch_stream_url" @disabled($streamTakenDown) placeholder="https://www.twitch.tv/channel" class="w-full rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-100 focus:border-purple-500 focus:outline-none disabled:opacity-60">
                                @error('twitch_stream_url') <span class="mt-1 block text-xs text-rose-400">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-zinc-500 flex items-center gap-1.5">
                                    <span class="text-blue-400">◉</span> Facebook URL
                                </label>
                                <input type="url" wire:model="facebook_stream_url" @disabled($streamTakenDown) placeholder="https://www.facebook.com/.../videos/..." class="w-full rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-100 focus:border-blue-500 focus:outline-none disabled:opacity-60">
                                @error('facebook_stream_url') <span class="mt-1 block text-xs text-rose-400">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <button type="submit" @disabled($streamTakenDown) wire:loading.attr="disabled" wire:target="streamThumbnail" class="inline-flex items-center justify-center gap-2 rounded-xl bg-purple-600 px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-purple-500 disabled:cursor-not-allowed disabled:opacity-50 shadow-[0_0_15px_rgba(147,51,234,0.3)]">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            <span wire:loading.remove wire:target="streamThumbnail">Save Stream</span><span wire:loading wire:target="streamThumbnail">Uploading Thumbnail...</span>
                        </button>
                    </form>
                </section>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════
             BROWSE SECTION (Twitch-style tabs)
        ═══════════════════════════════════════════════ --}}
        <div class="space-y-6">
            {{-- Section header --}}
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-black font-orbitron text-white uppercase tracking-widest">
                    {{ $isAdminView ? 'All Streams' : 'Browse Streams' }}
                </h2>
                <span class="text-xs text-zinc-500">{{ $playerStreams->total() }} stream{{ $playerStreams->total() === 1 ? '' : 's' }}</span>
            </div>

            {{-- ── Tab bar ── --}}
            @if(! $isAdminView)
            <div class="flex gap-2 flex-wrap">
                {{-- All tab --}}
                <button
                    wire:click="setTab('all')"
                    class="px-4 py-2 rounded-full text-[11px] font-black uppercase tracking-widest transition-all duration-200 {{ $activeTab === 'all' ? 'bg-purple-600 text-white shadow-[0_0_12px_rgba(147,51,234,0.4)]' : 'bg-zinc-900 text-zinc-400 hover:bg-zinc-800 hover:text-white border border-zinc-800' }}"
                >
                    All Streams
                </button>

                {{-- Game tabs --}}
                @foreach($games as $game)
                    <button
                        wire:click="setTab('game:{{ $game->id }}')"
                        class="px-4 py-2 rounded-full text-[11px] font-black uppercase tracking-widest transition-all duration-200 {{ $activeTab === 'game:'.$game->id ? 'bg-purple-600 text-white shadow-[0_0_12px_rgba(147,51,234,0.4)]' : 'bg-zinc-900 text-zinc-400 hover:bg-zinc-800 hover:text-white border border-zinc-800' }}"
                    >
                        {{ $game->localizedName() }}
                    </button>
                @endforeach
            </div>
            @endif

            {{-- ── Stream grid ── --}}
            @if($browsedStreams->isEmpty())
                <div class="rounded-2xl border border-purple-500/10 bg-[#0c081d]/80 p-12 text-center">
                    <i data-lucide="radio" class="w-14 h-14 mx-auto text-zinc-700 mb-4"></i>
                    <h3 class="text-sm font-black uppercase tracking-widest text-zinc-300">No Streams Found</h3>
                    <p class="mt-2 text-xs text-zinc-500">
                        @if(str_starts_with($activeTab, 'game:'))
                            No streams are currently live in this category.
                        @else
                            Player-created streams will appear here once players publish their stream URLs.
                        @endif
                    </p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach($browsedStreams as $playerStream)
                        @php
                            $stream = $streamService->streamForChannel($playerStream);
                            $isTakenDown = $playerStream->taken_down_at !== null;
                            $streamerName = $playerStream->user?->profile?->display_name ?? $playerStream->user?->username ?? 'Player';
                        @endphp

                        @if($stream !== null)
                            <article class="group relative flex flex-col rounded-xl overflow-hidden border {{ $isTakenDown ? 'border-rose-500/30 bg-rose-950/20' : 'border-zinc-800/60 bg-zinc-950' }} hover:border-purple-500/40 transition-all duration-300 hover:shadow-[0_0_20px_rgba(147,51,234,0.15)]">

                                {{-- Thumbnail area --}}
                                <a href="{{ $isAdminView ? route('admin.streams.watch', $playerStream->id) : (! $isTakenDown ? route('streams.watch', $playerStream->id) : '#') }}" wire:navigate class="block relative aspect-video bg-zinc-900 overflow-hidden">
                                    @if($playerStream->thumbnail_url)
                                        <img src="{{ $playerStream->thumbnail_url }}" alt="{{ $playerStream->title ?? $streamerName.' stream' }}" class="absolute inset-0 h-full w-full object-cover">
                                    @endif
                                    <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-purple-900/30 to-fuchsia-900/30">
                                        <i data-lucide="{{ $stream['icon'] }}" class="w-10 h-10 text-zinc-600 group-hover:text-purple-400 transition-colors duration-300"></i>
                                    </div>

                                    {{-- Play overlay --}}
                                    @if(! $isTakenDown)
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all duration-300 flex items-center justify-center">
                                        <div class="w-12 h-12 rounded-full bg-purple-600/0 group-hover:bg-purple-600/90 flex items-center justify-center transition-all duration-300 scale-50 group-hover:scale-100">
                                            <i data-lucide="play" class="w-5 h-5 text-white ml-0.5"></i>
                                        </div>
                                    </div>
                                    @endif

                                    {{-- Badges --}}
                                    <div class="absolute top-2 left-2 flex gap-1">
                                        @if($isTakenDown)
                                            <span class="bg-rose-600/90 text-white text-[9px] font-black px-2 py-0.5 rounded-full uppercase">Taken Down</span>
                                        @elseif($playerStream->is_live)
                                            <span class="flex items-center gap-1 bg-red-600/90 text-white text-[9px] font-black px-2 py-0.5 rounded-full uppercase">
                                                <span class="w-1.5 h-1.5 bg-white rounded-full animate-ping"></span> LIVE
                                            </span>
                                        @endif
                                        <span class="bg-black/70 text-zinc-300 text-[9px] font-bold px-2 py-0.5 rounded-full uppercase">{{ $stream['label'] }}</span>
                                    </div>

                                    {{-- Viewer count --}}
                                    @if($playerStream->viewer_count > 0)
                                    <div class="absolute bottom-2 left-2 flex items-center gap-1 bg-black/70 text-red-400 text-[9px] font-bold px-2 py-0.5 rounded-full">
                                        <i data-lucide="eye" class="w-2.5 h-2.5"></i>
                                        {{ $playerStream->formattedViewers() }}
                                    </div>
                                    @endif
                                </a>

                                {{-- Info --}}
                                <div class="p-3 flex flex-col gap-1.5 flex-1">
                                    {{-- Avatar + name --}}
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-purple-500 to-fuchsia-500 flex items-center justify-center text-white text-[10px] font-black flex-shrink-0">
                                            {{ strtoupper(substr($streamerName, 0, 2)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-white truncate">{{ $playerStream->title ?? $streamerName . ' Stream' }}</p>
                                            <p class="text-[10px] text-zinc-500 truncate">{{ $streamerName }}</p>
                                        </div>
                                    </div>

                                    {{-- Game tag --}}
                                    @if($playerStream->game)
                                        <p class="text-[10px] text-purple-400 font-bold">{{ $playerStream->game->localizedName() }}</p>
                                    @endif

                                    {{-- Stats --}}
                                    <div class="flex items-center gap-3 text-[10px] text-zinc-600 mt-auto pt-1 border-t border-zinc-800/50">
                                        <span class="flex items-center gap-1">
                                            <i data-lucide="eye" class="w-3 h-3"></i>
                                            {{ $playerStream->formattedTotalViews() }} views
                                        </span>
                                        @if($canModerateStreams && $isTakenDown)
                                            <span class="flex items-center gap-1 text-rose-400">
                                                <i data-lucide="ban" class="w-3 h-3"></i>
                                                Taken Down
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Admin actions --}}
                                    @if($canModerateStreams)
                                        <div class="flex flex-col gap-1.5 mt-1">
                                            {{-- View Stream --}}
                                            <a
                                                href="{{ route('admin.streams.watch', $playerStream->id) }}"
                                                wire:navigate
                                                class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-indigo-600/20 border border-indigo-600/40 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-indigo-300 transition hover:bg-indigo-600 hover:text-white"
                                            >
                                                <i data-lucide="eye" class="w-3 h-3"></i> View Stream
                                            </a>
                                            <div class="grid grid-cols-2 gap-1.5">
                                                {{-- Take Down / Restore --}}
                                                @if($isTakenDown)
                                                    <button wire:click="restorePlayerStream({{ $playerStream->id }})" class="inline-flex items-center justify-center gap-1 rounded-lg bg-emerald-600/20 border border-emerald-600/40 px-2 py-1.5 text-[9px] font-black uppercase tracking-wider text-emerald-300 transition hover:bg-emerald-600 hover:text-white">
                                                        <i data-lucide="rotate-ccw" class="w-2.5 h-2.5"></i> Restore
                                                    </button>
                                                @else
                                                    <button wire:click="takeDownPlayerStream({{ $playerStream->id }})" class="inline-flex items-center justify-center gap-1 rounded-lg bg-rose-600/20 border border-rose-600/40 px-2 py-1.5 text-[9px] font-black uppercase tracking-wider text-rose-300 transition hover:bg-rose-600 hover:text-white">
                                                        <i data-lucide="ban" class="w-2.5 h-2.5"></i> Take Down
                                                    </button>
                                                @endif

                                                {{-- Feature / Unfeature --}}
                                                <button
                                                    wire:click="toggleFeature({{ $playerStream->id }})"
                                                    class="inline-flex items-center justify-center gap-1 rounded-lg px-2 py-1.5 text-[9px] font-black uppercase tracking-wider transition {{ $playerStream->is_featured ? 'bg-amber-600/20 border border-amber-600/40 text-amber-300 hover:bg-amber-600 hover:text-white' : 'bg-zinc-800 border border-zinc-700 text-zinc-400 hover:border-amber-500/50 hover:text-amber-300' }}"
                                                >
                                                    <i data-lucide="star" class="w-2.5 h-2.5"></i>
                                                    {{ $playerStream->is_featured ? 'Unfeature' : 'Feature' }}
                                                </button>

                                                {{-- Mark Live / Offline --}}
                                                <button
                                                    wire:click="toggleLive({{ $playerStream->id }})"
                                                    class="inline-flex items-center justify-center gap-1 rounded-lg px-2 py-1.5 text-[9px] font-black uppercase tracking-wider transition {{ $playerStream->is_live ? 'bg-red-600/20 border border-red-600/40 text-red-300 hover:bg-red-600 hover:text-white' : 'bg-zinc-800 border border-zinc-700 text-zinc-400 hover:border-red-500/50 hover:text-red-300' }}"
                                                >
                                                    <i data-lucide="radio" class="w-2.5 h-2.5"></i>
                                                    {{ $playerStream->is_live ? 'Offline' : 'Mark Live' }}
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endif
                    @endforeach
                </div>

                @if($browsedStreams->hasPages())
                    <div class="mt-6">
                        {{ $browsedStreams->links() }}
                    </div>
                @endif

                {{-- Admin takedown reason box --}}
                @if($canModerateStreams)
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 mt-4">
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">Optional Takedown Reason (used for next Take Down)</label>
                        <textarea wire:model="takedownReason" rows="2" class="w-full rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-100 focus:border-rose-500 focus:outline-none resize-none" placeholder="Reason used for the next Take Down action"></textarea>
                        @error('takedownReason') <span class="mt-1 block text-xs text-rose-400">{{ $message }}</span> @enderror
                    </div>
                @endif
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════
             TOURNAMENT BROADCASTS
        ═══════════════════════════════════════════════ --}}
        @if(! $isAdminView)
        <div class="space-y-5">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-black font-orbitron text-white uppercase tracking-widest">Tournament Broadcasts</h2>
                @if($tournaments->isNotEmpty())
                    <span class="bg-red-600 text-white text-[9px] font-black px-2 py-0.5 rounded-full uppercase tracking-widest">{{ $tournaments->count() }} active</span>
                @endif
            </div>

            @if($tournaments->isEmpty())
                <div class="rounded-2xl border border-purple-500/10 bg-[#0c081d]/80 p-8 text-center">
                    <i data-lucide="tv" class="w-12 h-12 text-zinc-700 mx-auto mb-4"></i>
                    <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-300 uppercase">No Tournament Broadcasts</h3>
                    <p class="text-xs text-zinc-500 mt-2">Tournament broadcasts appear when admins attach stream URLs to events.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($tournaments as $tournament)
                        @php
                            $streamItems = $streamService->streamsForTournament($tournament);
                            $status = $streamService->statusLabel($tournament);
                        @endphp

                        @foreach($streamItems as $stream)
                            <article class="group flex flex-col rounded-xl overflow-hidden border border-zinc-800/60 bg-zinc-950 hover:border-purple-500/40 transition-all duration-300 hover:shadow-[0_0_20px_rgba(147,51,234,0.15)]">
                                <div class="relative aspect-video bg-zinc-900">
                                    @if($tournament->banner_url)
                                        <img src="{{ $tournament->banner_url }}" alt="{{ $tournament->name }}" class="absolute inset-0 h-full w-full object-cover">
                                    @endif
                                    <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-cyan-900/30 to-blue-900/30">
                                        <i data-lucide="swords" class="w-10 h-10 text-zinc-600"></i>
                                    </div>
                                    <div class="absolute top-2 left-2 flex gap-1">
                                        <span class="text-[9px] font-black px-2 py-0.5 rounded-full uppercase {{ $status['class'] }}">{{ $status['label'] }}</span>
                                        <span class="bg-black/70 text-zinc-300 text-[9px] font-bold px-2 py-0.5 rounded-full">{{ $stream['label'] }}</span>
                                    </div>
                                    @if($tournament->game)
                                    <div class="absolute bottom-2 left-2">
                                        <span class="bg-cyan-600/80 text-white text-[9px] font-bold px-2 py-0.5 rounded-full">{{ $tournament->game->localizedName() }}</span>
                                    </div>
                                    @endif
                                </div>

                                <div class="p-3 flex flex-col gap-2">
                                    <p class="text-xs font-black text-white truncate">{{ $tournament->name }}</p>
                                    <p class="text-[10px] text-zinc-500">{{ $tournament->start_at ? $tournament->start_at->format('M d, Y h:i A') : 'TBD' }}</p>
                                    <div class="flex gap-2 mt-1">
                                        <a href="/tournaments/{{ $tournament->uuid }}/view" wire:navigate class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-cyan-600/20 border border-cyan-600/40 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-cyan-300 transition hover:bg-cyan-600 hover:text-white">
                                            <i data-lucide="swords" class="w-3 h-3"></i> Tournament
                                        </a>
                                        <a href="{{ $stream['url'] }}" target="_blank" rel="noopener noreferrer" class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-zinc-800 border border-zinc-700 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-zinc-300 transition hover:bg-zinc-700">
                                            <i data-lucide="external-link" class="w-3 h-3"></i> Watch
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    @endforeach
                </div>
            @endif
        </div>
        @endif

    </div>{{-- end padded content --}}
</div>
