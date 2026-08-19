{{-- stream-watch.blade.php — YouTube/Twitch style stream viewer --}}
{{-- Supports both Player layout and Admin layout --}}
<div
    class="flex flex-col {{ $isAdminView ? '' : 'lg:flex-row' }} gap-0 {{ $isAdminView ? '' : '-mt-4 sm:-mt-6 md:-mt-8 -mx-4 sm:-mx-6 md:-mx-8' }} min-h-[calc(100vh-5rem)]"
    x-data="{
        showChat: true,
        messages: @js($recentMessages),
        viewerCount: {{ $viewerCount }},

        init() {
            // Heartbeat every 30s to update viewer presence
            setInterval(() => {
                $wire.heartbeat();
            }, 30000);
            this.$nextTick(() => this.scrollChat());

            // Reverb WebSockets
            if (typeof window.Echo !== 'undefined') {
                window.Echo.channel('stream.{{ $streamChannel->id }}')
                    .listen('.StreamMessageSent', (e) => {
                        if (this.messages.some(message => message.id === e.message.id)) return;
                        this.messages.push(e.message);
                        this.$nextTick(() => this.scrollChat());
                    })
                    .listen('.StreamMessageDeleted', (e) => {
                        this.messages = this.messages.filter(m => m.id !== e.messageId);
                    })
                    .listen('.StreamViewerCountUpdated', (e) => {
                        this.viewerCount = e.viewerCount;
                    });
            }
        },
        scrollChat() {
            const el = this.$refs.chatBox;
            if (el) el.scrollTop = el.scrollHeight;
        },
        formatViewers(n) {
            if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
            if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
            return String(n);
        }
    }"
    @chat-updated.window="messages = $wire.recentMessages; $nextTick(() => scrollChat())"
>
    <x-ui.toasts />

    {{-- ═══════════════════════════════════════════════════════════
         ADMIN CONTROL BAR — only shown to moderators
    ══════════════════════════════════════════════════════════════ --}}
    @if($canModerate)
    <div class="w-full {{ $isAdminView ? 'bg-[#0f172a] border-b border-slate-700' : 'bg-zinc-900/90 border-b border-zinc-700' }} px-4 sm:px-6 py-2.5 flex flex-wrap items-center gap-2 z-20">
        <span class="text-[10px] font-black uppercase tracking-widest {{ $isAdminView ? 'text-slate-400' : 'text-zinc-500' }} mr-2 flex items-center gap-1.5">
            <i data-lucide="shield" class="w-3.5 h-3.5 {{ $isAdminView ? 'text-indigo-400' : 'text-purple-400' }}"></i>
            Mod Controls
        </span>

        {{-- Take down / Restore --}}
        @if($streamChannel->taken_down_at)
            <button
                wire:click="restore"
                wire:confirm="Restore this stream and make it public again?"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600/20 border border-emerald-600/40 text-emerald-300 hover:bg-emerald-600 hover:text-white text-[10px] font-black uppercase tracking-wide transition-all duration-150"
            >
                <i data-lucide="rotate-ccw" class="w-3 h-3"></i> Restore Stream
            </button>
        @else
            <button
                wire:click="takeDown"
                wire:confirm="Are you sure you want to take down this stream?"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-600/20 border border-rose-600/40 text-rose-300 hover:bg-rose-600 hover:text-white text-[10px] font-black uppercase tracking-wide transition-all duration-150"
            >
                <i data-lucide="ban" class="w-3 h-3"></i> Take Down
            </button>
        @endif

        {{-- Feature / Unfeature --}}
        <button
            wire:click="toggleFeatured"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wide transition-all duration-150 {{ $streamChannel->is_featured ? 'bg-amber-600/30 border border-amber-500/50 text-amber-300 hover:bg-amber-600 hover:text-white' : 'bg-zinc-800 border border-zinc-700 text-zinc-400 hover:border-amber-500/50 hover:text-amber-300' }}"
        >
            <i data-lucide="star" class="w-3 h-3"></i>
            {{ $streamChannel->is_featured ? 'Unfeature' : 'Feature' }}
        </button>

        {{-- Mark Live / Offline --}}
        <button
            wire:click="toggleLive"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wide transition-all duration-150 {{ $streamChannel->is_live ? 'bg-red-600/30 border border-red-500/50 text-red-300 hover:bg-red-600 hover:text-white' : 'bg-zinc-800 border border-zinc-700 text-zinc-400 hover:border-red-500/50 hover:text-red-300' }}"
        >
            @if($streamChannel->is_live)
                <span class="w-1.5 h-1.5 bg-red-400 rounded-full animate-ping"></span> Mark Offline
            @else
                <i data-lucide="radio" class="w-3 h-3"></i> Mark Live
            @endif
        </button>

        {{-- Status badges --}}
        <div class="flex items-center gap-2 ml-auto flex-wrap">
            @if($streamChannel->taken_down_at)
                <span class="inline-flex items-center gap-1 text-[9px] font-black uppercase px-2 py-1 rounded-full bg-rose-900/40 border border-rose-700/40 text-rose-300">
                    <i data-lucide="ban" class="w-2.5 h-2.5"></i> Taken Down
                </span>
            @endif
            @if($streamChannel->is_featured)
                <span class="inline-flex items-center gap-1 text-[9px] font-black uppercase px-2 py-1 rounded-full bg-amber-900/40 border border-amber-700/40 text-amber-300">
                    <i data-lucide="star" class="w-2.5 h-2.5"></i> Featured
                </span>
            @endif
            @if($streamChannel->is_live)
                <span class="inline-flex items-center gap-1 text-[9px] font-black uppercase px-2 py-1 rounded-full bg-red-900/40 border border-red-700/40 text-red-300 animate-pulse">
                    <span class="w-1.5 h-1.5 bg-red-400 rounded-full"></span> Live
                </span>
            @endif

            {{-- Delete stream (SUPER_ADMIN / ADMIN only) --}}
            @if(auth()->user()?->hasAnyRole(['SUPER_ADMIN', 'ADMIN']))
                <button
                    wire:click="deleteStream"
                    wire:confirm="Permanently DELETE this stream? This cannot be undone."
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-900/30 border border-red-700/40 text-red-400 hover:bg-red-700 hover:text-white text-[10px] font-black uppercase tracking-wide transition-all duration-150"
                >
                    <i data-lucide="trash-2" class="w-3 h-3"></i> Delete
                </button>
            @endif
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════
         MAIN BODY: Video Left + Chat Right
    ══════════════════════════════════════════════════════════════ --}}
    <div class="flex flex-col lg:flex-row flex-1 min-h-0">

        {{-- ── VIDEO + DETAILS column ─────────────────────────────── --}}
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto {{ $isAdminView ? 'bg-[#090d16]' : '' }}">

            {{-- Video embed --}}
            <div class="relative w-full bg-black" style="padding-bottom: 56.25%; height: 0; overflow: hidden;">
                <iframe
                    class="absolute inset-0 w-full h-full"
                    src="{{ $embedUrl }}"
                    title="{{ $streamChannel->title ?? 'Stream' }}"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share; fullscreen"
                    allowfullscreen
                    referrerpolicy="strict-origin-when-cross-origin"
                ></iframe>
            </div>

            {{-- ── Meta bar ── --}}
            <div class="px-4 sm:px-6 py-4 border-b {{ $isAdminView ? 'border-slate-800/60' : 'border-zinc-800/60' }} space-y-3">
                {{-- Title row --}}
                <div class="flex flex-wrap items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            @if($streamChannel->is_live)
                                <span class="flex items-center gap-1.5 bg-red-600 text-white text-[10px] font-black tracking-wider uppercase px-2.5 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-white rounded-full animate-ping"></span> LIVE
                                </span>
                            @endif
                            @if($streamChannel->taken_down_at && $canModerate)
                                <span class="flex items-center gap-1 text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-rose-900/50 border border-rose-700/40 text-rose-300">
                                    <i data-lucide="ban" class="w-3 h-3"></i> Taken Down
                                </span>
                            @endif
                            @if($streamChannel->game)
                                <span class="text-[10px] {{ $isAdminView ? 'text-indigo-400' : 'text-purple-400' }} font-bold uppercase">
                                    {{ $streamChannel->game->localizedName() }}
                                </span>
                            @endif
                            <span class="text-[10px] {{ $isAdminView ? 'bg-slate-800 text-slate-400' : 'bg-zinc-800 text-zinc-400' }} rounded-full px-2 py-0.5 font-bold uppercase">{{ $stream['label'] }}</span>
                        </div>
                        <h1 class="text-base sm:text-xl font-black text-white leading-tight">
                            {{ $streamChannel->title ?? $streamerName . "'s Stream" }}
                        </h1>
                    </div>

                    {{-- Stats --}}
                    <div class="flex items-center gap-4 text-xs flex-shrink-0">
                        <div class="flex items-center gap-1.5 text-red-400 font-bold">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                            <span x-text="formatViewers(viewerCount)"></span>
                            <span class="{{ $isAdminView ? 'text-slate-500' : 'text-zinc-500' }} font-normal">watching</span>
                        </div>
                        <div class="hidden sm:flex items-center gap-1.5 {{ $isAdminView ? 'text-slate-500' : 'text-zinc-500' }}">
                            <i data-lucide="play-circle" class="w-4 h-4"></i>
                            <span>{{ $streamChannel->formattedTotalViews() }} views</span>
                        </div>
                    </div>
                </div>

                {{-- Streamer row --}}
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br {{ $isAdminView ? 'from-indigo-500 to-indigo-700' : 'from-purple-500 to-fuchsia-500' }} flex items-center justify-center text-white font-black text-sm flex-shrink-0">
                            {{ strtoupper(substr($streamerName, 0, 2)) }}
                        </div>
                        <div>
                            <p class="text-sm font-black text-white">{{ $streamerName }}</p>
                            <p class="text-[10px] {{ $isAdminView ? 'text-slate-500' : 'text-zinc-500' }}">
                                @if($streamChannel->user_id)
                                    Player Stream
                                @elseif($streamChannel->tournament_id)
                                    Tournament Broadcast
                                @else
                                    Game Channel
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Chat toggle (mobile) --}}
                        <button
                            @click="showChat = !showChat"
                            class="lg:hidden flex items-center gap-1.5 {{ $isAdminView ? 'bg-slate-800 border-slate-700 text-slate-300' : 'bg-zinc-900 border-zinc-700 text-zinc-300' }} border hover:border-purple-500/50 hover:text-white text-[10px] font-black uppercase tracking-widest px-3 py-2 rounded-xl transition-all duration-200"
                            :class="showChat && '{{ $isAdminView ? 'bg-indigo-900/30 border-indigo-600/50 text-indigo-300' : 'bg-purple-900/30 border-purple-600/50 text-purple-300' }}'"
                        >
                            <i data-lucide="message-circle" class="w-3.5 h-3.5"></i>
                            <span x-text="showChat ? 'Hide Chat' : 'Show Chat'"></span>
                        </button>

                        {{-- Back --}}
                        <a href="{{ $isAdminView ? '/admin/streams' : (auth()->check() ? route('streams') : ($streamChannel->game ? route('games.show', $streamChannel->game->slug).'?tab=streams' : '/tournaments')) }}" wire:navigate
                           class="flex items-center gap-1.5 {{ $isAdminView ? 'bg-slate-800 border-slate-700 text-slate-400' : 'bg-zinc-900 border-zinc-700 text-zinc-400' }} border hover:border-zinc-500 hover:text-white text-[10px] font-black uppercase tracking-widest px-3 py-2 rounded-xl transition-all duration-200">
                            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                            Back
                        </a>

                        <a href="{{ $stream['url'] }}" target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-1.5 {{ $isAdminView ? 'bg-slate-800 border-slate-700 text-slate-400' : 'bg-zinc-900 border-zinc-700 text-zinc-400' }} border hover:border-zinc-500 hover:text-white text-[10px] font-black uppercase tracking-widest px-3 py-2 rounded-xl transition-all duration-200">
                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                            {{ $stream['label'] }}
                        </a>
                    </div>
                </div>
            </div>

            {{-- ── About / Description ── --}}
            <div class="px-4 sm:px-6 py-5 space-y-4 flex-1">

                {{-- Stream description --}}
                @if($streamChannel->description)
                    <div class="rounded-xl {{ $isAdminView ? 'bg-slate-900/70 border-slate-800' : 'bg-zinc-900/80 border-zinc-800' }} border p-4">
                        <h2 class="text-xs font-black {{ $isAdminView ? 'text-slate-400' : 'text-zinc-400' }} uppercase tracking-widest mb-2">About This Stream</h2>
                        <p class="text-sm {{ $isAdminView ? 'text-slate-300' : 'text-zinc-300' }} leading-relaxed">{{ $streamChannel->description }}</p>
                    </div>
                @endif

                {{-- Game section --}}
                @if($streamChannel->game)
                    @php $gameDescript = $streamChannel->game->localizedDescription(); @endphp
                    <div class="rounded-xl {{ $isAdminView ? 'bg-slate-900/70 border-slate-800' : 'bg-zinc-900/80 border-zinc-800' }} border p-4">
                        <div class="flex items-center gap-3 mb-3">
                            @if($streamChannel->game->bannerUrl())
                                <img src="{{ $streamChannel->game->bannerUrl() }}" alt="{{ $streamChannel->game->localizedName() }}" class="w-10 h-10 rounded-lg object-cover">
                            @else
                                <div class="w-10 h-10 rounded-lg {{ $isAdminView ? 'bg-indigo-900/30' : 'bg-purple-900/30' }} flex items-center justify-center">
                                    <i data-lucide="gamepad-2" class="w-5 h-5 {{ $isAdminView ? 'text-indigo-400' : 'text-purple-400' }}"></i>
                                </div>
                            @endif
                            <div>
                                <p class="text-sm font-black text-white">{{ $streamChannel->game->localizedName() }}</p>
                                <p class="text-[10px] {{ $isAdminView ? 'text-indigo-400' : 'text-purple-400' }} font-bold">Game Category</p>
                            </div>
                        </div>
                        @if($gameDescript)
                            <p class="text-xs {{ $isAdminView ? 'text-slate-400' : 'text-zinc-400' }} leading-relaxed mb-4">{{ $gameDescript }}</p>
                        @endif

                        {{-- Game sub-tabs --}}
                        @if(! $isAdminView)
                        <div x-data="{ gameTab: 'streams' }">
                            <div class="flex gap-2 mb-4 flex-wrap">
                                @foreach(['streams' => 'More Streams', 'tournaments' => 'Competitions'] as $tab => $label)
                                    <button
                                        @click="gameTab = '{{ $tab }}'"
                                        :class="gameTab === '{{ $tab }}' ? 'bg-purple-600 text-white' : 'bg-zinc-800 text-zinc-400 hover:text-white'"
                                        class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all duration-200"
                                    >{{ $label }}</button>
                                @endforeach
                            </div>

                            <div x-show="gameTab === 'streams'">
                                @if($gameStreams->isEmpty())
                                    <p class="text-xs text-zinc-600 text-center py-4">No other streams in this category.</p>
                                @else
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                        @foreach($gameStreams as $gs)
                                            @php $gsName = $gs->user?->profile?->display_name ?? $gs->user?->username ?? 'Player'; @endphp
                                            <a href="{{ route('streams.watch', $gs->id) }}" wire:navigate class="group block rounded-lg overflow-hidden border border-zinc-800 hover:border-purple-500/40 transition-all duration-200">
                                                <div class="aspect-video bg-gradient-to-br from-purple-900/20 to-fuchsia-900/20 flex items-center justify-center relative">
                                                    @if($gs->thumbnail_url)<img src="{{ $gs->thumbnail_url }}" alt="{{ $gs->title ?? $gsName }}" class="absolute inset-0 h-full w-full object-cover">@endif
                                                    <i data-lucide="play" class="w-6 h-6 text-zinc-600 group-hover:text-purple-400 transition-colors"></i>
                                                    @if($gs->viewer_count > 0)
                                                        <span class="absolute bottom-1 left-1 bg-black/70 text-red-400 text-[8px] font-bold px-1.5 py-0.5 rounded-full flex items-center gap-0.5">
                                                            <span class="w-1 h-1 bg-red-400 rounded-full"></span>
                                                            {{ $gs->formattedViewers() }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="p-2">
                                                    <p class="text-[10px] font-bold text-white truncate">{{ $gs->title ?? $gsName }}</p>
                                                    <p class="text-[9px] text-zinc-500 truncate">{{ $gsName }}</p>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div x-show="gameTab === 'tournaments'" x-cloak>
                                @if($gameCompetitions->isEmpty())
                                    <p class="text-xs text-zinc-600 text-center py-4">No active competitions for this game.</p>
                                @else
                                    <div class="space-y-2">
                                        @foreach($gameCompetitions as $t)
                                            <a href="/tournaments/{{ $t->uuid }}/view" wire:navigate class="flex items-center gap-3 p-3 rounded-xl bg-zinc-800/50 border border-zinc-700/50 hover:border-purple-500/40 transition-all duration-200">
                                                <i data-lucide="swords" class="w-5 h-5 text-cyan-400 flex-shrink-0"></i>
                                                <div class="min-w-0">
                                                    <p class="text-xs font-bold text-white truncate">{{ $t->name }}</p>
                                                    <p class="text-[10px] text-zinc-500">{{ $t->start_at?->format('M d, Y') ?? 'TBD' }} · {{ $t->status }}</p>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                        </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- ── LIVE CHAT column ────────────────────────────────────── --}}
        <div
            x-show="showChat"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-x-4"
            x-transition:enter-end="opacity-100 translate-x-0"
            class="flex flex-col w-full lg:w-[320px] xl:w-[360px] flex-shrink-0 border-t lg:border-t-0 lg:border-l {{ $isAdminView ? 'border-slate-800/60 bg-[#0f172a]/80' : 'border-zinc-800/60 bg-[#0a0718]/60' }}"
            style="max-height: calc(100vh - {{ $isAdminView ? '4rem' : '5rem' }});"
        >
            {{-- Chat header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b {{ $isAdminView ? 'border-slate-800/60' : 'border-zinc-800/60' }} flex-shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                    <span class="text-xs font-black uppercase tracking-widest text-white">Live Chat</span>
                </div>
                <div class="flex items-center gap-1.5 text-[10px] {{ $isAdminView ? 'text-slate-500' : 'text-zinc-500' }}">
                    <i data-lucide="users" class="w-3.5 h-3.5"></i>
                    <span x-text="formatViewers(viewerCount) + ' watching'"></span>
                </div>
            </div>

            {{-- Messages --}}
            <div
                x-ref="chatBox"
                class="flex-1 overflow-y-auto px-3 py-3 space-y-1.5 scroll-smooth"
                style="min-height: 200px;"
            >
                <template x-if="messages.length === 0">
                    <div class="flex flex-col items-center justify-center h-full text-center py-8 gap-2">
                        <i data-lucide="message-circle" class="w-8 h-8 {{ $isAdminView ? 'text-slate-700' : 'text-zinc-700' }}"></i>
                        <p class="text-xs {{ $isAdminView ? 'text-slate-600' : 'text-zinc-600' }}">No messages yet. Be the first to chat!</p>
                    </div>
                </template>

                <template x-for="msg in messages" :key="msg.id">
                    <div class="group flex flex-col gap-0.5 rounded-lg px-2 py-1.5 hover:{{ $isAdminView ? 'bg-slate-800/40' : 'bg-zinc-800/40' }} transition-colors" :class="msg.is_mod ? 'bg-emerald-950/20 border border-emerald-900/30' : ''">
                        <div class="flex items-baseline gap-1.5 flex-wrap">
                            <template x-if="msg.is_mod">
                                <span class="bg-emerald-600 text-white text-[8px] font-black uppercase tracking-wider px-1 py-0.5 rounded-sm flex items-center gap-0.5" title="Moderator">
                                    <i data-lucide="shield" class="w-2.5 h-2.5"></i> MOD
                                </span>
                            </template>
                            <span class="text-[11px] font-black" :style="'color: ' + msg.color" x-text="msg.username + ':'"></span>
                            <span class="text-xs {{ $isAdminView ? 'text-slate-300' : 'text-zinc-300' }} break-words" x-text="msg.message" :class="msg.is_mod ? 'font-medium text-emerald-300' : ''"></span>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <span class="text-[9px] {{ $isAdminView ? 'text-slate-700' : 'text-zinc-700' }}" x-text="msg.time"></span>

                            {{-- Admin actions per message --}}
                            @if($canModerate)
                            <div class="opacity-0 group-hover:opacity-100 flex items-center gap-2 transition-all duration-150">
                                <button
                                    @click.stop="$wire.muteUser(msg.user_id)"
                                    class="text-[9px] font-black uppercase tracking-wide text-amber-500 hover:text-amber-300 flex items-center gap-0.5"
                                    title="Mute User"
                                >
                                    <i data-lucide="volume-x" class="w-2.5 h-2.5"></i>
                                    Mute
                                </button>
                                <button
                                    @click.stop="$wire.deleteMessage(msg.id)"
                                    class="text-[9px] font-black uppercase tracking-wide text-rose-500 hover:text-rose-300 flex items-center gap-0.5"
                                    title="Delete message"
                                >
                                    <i data-lucide="trash-2" class="w-2.5 h-2.5"></i>
                                    Del
                                </button>
                            </div>
                            @endif
                        </div>
                    </div>
                </template>
            </div>

            {{-- Chat input --}}
            <div class="px-3 py-3 border-t {{ $isAdminView ? 'border-slate-800/60' : 'border-zinc-800/60' }} flex-shrink-0">
                @auth
                    <form wire:submit.prevent="sendMessage" class="flex gap-2">
                        <input
                            type="text"
                            wire:model="chatMessage"
                            placeholder="Send a message..."
                            maxlength="300"
                            class="flex-1 min-w-0 {{ $isAdminView ? 'bg-slate-900 border-slate-700' : 'bg-zinc-900 border-zinc-700' }} border rounded-xl px-3 py-2.5 text-xs text-zinc-100 placeholder-zinc-600 focus:border-purple-500 focus:outline-none transition-colors"
                            @keydown.enter.prevent="$wire.sendMessage()"
                        >
                        <button
                            type="submit"
                            class="flex-shrink-0 bg-purple-600 hover:bg-purple-500 text-white rounded-xl px-3 py-2.5 transition-all duration-200"
                            wire:loading.attr="disabled"
                            wire:target="sendMessage"
                        >
                            <i data-lucide="send" class="w-4 h-4"></i>
                        </button>
                    </form>
                    @error('chatMessage')
                        <p class="text-[10px] text-rose-400 mt-1">{{ $message }}</p>
                    @enderror
                @else
                    <div class="flex flex-col items-center justify-center py-2">
                        <p class="text-[10px] text-zinc-500 uppercase tracking-widest font-black mb-1.5">Sign in to chat</p>
                        <a href="/login" class="text-xs bg-purple-600 hover:bg-purple-500 text-white px-4 py-1.5 rounded-lg font-black transition-colors">Login</a>
                    </div>
                @endauth
            </div>
        </div>

    </div>{{-- end main body --}}

</div>
