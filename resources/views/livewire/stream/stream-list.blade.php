<div class="space-y-8">
    <x-ui.toasts />

    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.3em] {{ $isAdminView ? 'text-indigo-400' : 'text-cyan-400' }}">Broadcast Center</p>
            <h1 class="mt-2 text-3xl md:text-4xl font-black {{ $isAdminView ? 'font-extrabold tracking-tight' : 'font-orbitron tracking-tight' }} text-white uppercase">
                {{ $isAdminView ? 'Stream Moderation' : 'Player Streams' }}
            </h1>
            <p class="mt-2 max-w-2xl text-sm {{ $isAdminView ? 'text-slate-400' : 'text-zinc-500' }}">
                {{ $isAdminView ? 'Review player-created streams and take down abusive or invalid broadcasts.' : 'Watch other players and manage your own YouTube, Twitch, or Facebook stream.' }}
            </p>
        </div>
        <div class="rounded-2xl border {{ $isAdminView ? 'border-slate-700 bg-slate-900 text-slate-400' : 'border-zinc-800 bg-zinc-950/70 text-zinc-500' }} px-4 py-3 text-[10px] font-black uppercase tracking-widest">
            {{ $playerStreams->count() }} player stream{{ $playerStreams->count() === 1 ? '' : 's' }}
        </div>
    </div>

    @if(! $isAdminView && auth()->user()?->hasRole('PLAYER'))
        @php
            $ownStream = $playerStreams->firstWhere('user_id', auth()->id());
            $streamTakenDown = $playerStreams
                ->where('user_id', auth()->id())
                ->whereNotNull('taken_down_at')
                ->isNotEmpty();
        @endphp

        <section class="rounded-2xl border border-cyan-500/20 bg-zinc-950/70 p-5 md:p-6">
            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-black font-orbitron uppercase tracking-widest text-white">My Stream</h2>
                    <p class="mt-1 text-xs text-zinc-500">Add at least one public stream URL. Your stream appears here when public and not taken down.</p>
                </div>
                <label class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-zinc-400">
                    <input type="checkbox" wire:model="is_public" @disabled($streamTakenDown) class="rounded border-zinc-700 bg-zinc-900 text-cyan-500 focus:ring-cyan-500">
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
                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-zinc-500">Stream Title</label>
                    <input type="text" wire:model="streamTitle" @disabled($streamTakenDown) placeholder="e.g. Ranked grind with viewers" class="w-full rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-100 focus:border-cyan-500 focus:outline-none disabled:opacity-60">
                    @error('streamTitle') <span class="mt-1 block text-xs text-rose-400">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-zinc-500">YouTube URL</label>
                        <input type="url" wire:model="youtube_stream_url" @disabled($streamTakenDown) placeholder="https://www.youtube.com/watch?v=..." class="w-full rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-100 focus:border-cyan-500 focus:outline-none disabled:opacity-60">
                        @error('youtube_stream_url') <span class="mt-1 block text-xs text-rose-400">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-zinc-500">Twitch URL</label>
                        <input type="url" wire:model="twitch_stream_url" @disabled($streamTakenDown) placeholder="https://www.twitch.tv/channel" class="w-full rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-100 focus:border-cyan-500 focus:outline-none disabled:opacity-60">
                        @error('twitch_stream_url') <span class="mt-1 block text-xs text-rose-400">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-zinc-500">Facebook Live URL</label>
                        <input type="url" wire:model="facebook_stream_url" @disabled($streamTakenDown) placeholder="https://www.facebook.com/.../videos/..." class="w-full rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-100 focus:border-cyan-500 focus:outline-none disabled:opacity-60">
                        @error('facebook_stream_url') <span class="mt-1 block text-xs text-rose-400">{{ $message }}</span> @enderror
                    </div>
                </div>

                <button type="submit" @disabled($streamTakenDown) class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-500 px-5 py-3 text-[10px] font-black uppercase tracking-widest text-zinc-950 transition hover:bg-cyan-300 disabled:cursor-not-allowed disabled:opacity-50">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Save Stream
                </button>
            </form>
        </section>
    @endif

    <section class="space-y-5">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-black {{ $isAdminView ? 'text-slate-100' : 'font-orbitron text-white' }} uppercase tracking-widest">Community Streams</h2>
        </div>

        @if($playerStreams->isEmpty())
            <div class="rounded-2xl border {{ $isAdminView ? 'border-slate-800 bg-slate-900/70' : 'border-purple-500/15 bg-[#0c081d]' }} p-10 text-center">
                <i data-lucide="radio" class="w-12 h-12 mx-auto {{ $isAdminView ? 'text-slate-600' : 'text-zinc-700' }} mb-4"></i>
                <h3 class="text-sm font-black uppercase tracking-widest {{ $isAdminView ? 'text-slate-300' : 'text-zinc-300' }}">No Player Streams</h3>
                <p class="mt-2 text-xs {{ $isAdminView ? 'text-slate-500' : 'text-zinc-500' }}">Player-created streams will appear here after players publish their stream URLs.</p>
            </div>
        @else
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                @foreach($playerStreams as $playerStream)
                    @php
                        $stream = $streamService->streamForChannel($playerStream);
                        $isTakenDown = $playerStream->taken_down_at !== null;
                        $streamerName = $playerStream->user->profile?->display_name ?: $playerStream->user->username;
                    @endphp

                    @if($stream !== null)
                        <article class="overflow-hidden rounded-2xl border {{ $isTakenDown ? 'border-rose-500/40 bg-rose-950/20' : ($isAdminView ? 'border-slate-800 bg-slate-900/80' : 'border-zinc-800/80 bg-zinc-950/70') }} shadow-2xl">
                            <div class="aspect-video bg-black {{ $isTakenDown ? 'opacity-40' : '' }}">
                                <iframe class="h-full w-full" src="{{ $stream['embed_url'] }}" title="{{ $streamerName }} {{ $stream['label'] }} stream" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share; fullscreen" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
                            </div>

                            <div class="space-y-4 p-5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-widest {{ $isTakenDown ? 'border-rose-500/50 bg-rose-500/10 text-rose-300' : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300' }}">
                                        {{ $isTakenDown ? 'Taken Down' : 'Player Stream' }}
                                    </span>
                                    <span class="rounded-full border {{ $isAdminView ? 'border-slate-700 bg-slate-950 text-slate-300' : 'border-zinc-700 bg-zinc-900 text-zinc-400' }} px-3 py-1 text-[10px] font-black uppercase tracking-widest">
                                        {{ $stream['label'] }}
                                    </span>
                                </div>

                                <div>
                                    <h3 class="text-lg font-black {{ $isAdminView ? 'text-slate-100' : 'font-orbitron text-white' }} uppercase tracking-tight">{{ $playerStream->title ?: $streamerName.' Stream' }}</h3>
                                    <p class="mt-1 text-xs {{ $isAdminView ? 'text-slate-500' : 'text-zinc-500' }}">by {{ $streamerName }}</p>
                                    @if($isTakenDown && $playerStream->takedown_reason)
                                        <p class="mt-2 text-xs text-rose-300">Reason: {{ $playerStream->takedown_reason }}</p>
                                    @endif
                                </div>

                                <div class="flex flex-col gap-3 sm:flex-row">
                                    <a href="{{ $stream['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border {{ $isAdminView ? 'border-slate-700 bg-slate-950 text-slate-300 hover:border-slate-500' : 'border-zinc-800 bg-zinc-900 text-zinc-300 hover:border-zinc-600' }} px-4 py-3 text-[10px] font-black uppercase tracking-widest transition hover:text-white">
                                        <i data-lucide="external-link" class="w-4 h-4"></i>
                                        Open on {{ $stream['label'] }}
                                    </a>

                                    @if($canModerateStreams)
                                        @if($isTakenDown)
                                            <button wire:click="restorePlayerStream({{ $playerStream->id }})" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-emerald-500">
                                                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                                Restore
                                            </button>
                                        @else
                                            <button wire:click="takeDownPlayerStream({{ $playerStream->id }})" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-rose-500">
                                                <i data-lucide="ban" class="w-4 h-4"></i>
                                                Take Down
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endif
                @endforeach
            </div>

            @if($canModerateStreams)
                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">Optional Takedown Reason</label>
                    <textarea wire:model="takedownReason" rows="2" class="w-full rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-100 focus:border-rose-500 focus:outline-none" placeholder="Reason used for the next Take Down action"></textarea>
                    @error('takedownReason') <span class="mt-1 block text-xs text-rose-400">{{ $message }}</span> @enderror
                </div>
            @endif
        @endif
    </section>

    <section class="space-y-5">
        <h2 class="text-xl font-black {{ $isAdminView ? 'text-slate-100' : 'font-orbitron text-white' }} uppercase tracking-widest">Game Trailers</h2>

            @if($gameTrailers->isEmpty())
                <div class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-8 text-center">
                    <i data-lucide="gamepad-2" class="w-12 h-12 text-zinc-700 mx-auto mb-4"></i>
                    <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-300 uppercase">No Game Trailers</h3>
                    <p class="text-xs text-zinc-500 mt-2">Sample game trailers appear here after the trailer seeder runs.</p>
                </div>
            @else
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    @foreach($gameTrailers as $game)
                        @foreach($game->streamChannels as $gameTrailer)
                            @php
                                $stream = $streamService->streamForChannel($gameTrailer);
                            @endphp

                            @if($stream !== null)
                                <article class="overflow-hidden rounded-2xl border border-zinc-800/80 bg-zinc-950/70 shadow-2xl">
                                    <div class="aspect-video bg-black">
                                        <iframe class="h-full w-full" src="{{ $stream['embed_url'] }}" title="{{ $game->localizedName() }} trailer" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share; fullscreen" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
                                    </div>

                                    <div class="space-y-4 p-5">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full border border-cyan-500/20 bg-cyan-500/10 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-cyan-300">Game Trailer</span>
                                            <span class="rounded-full border border-zinc-700 bg-zinc-900 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-zinc-400">{{ $stream['label'] }}</span>
                                        </div>

                                        <div>
                                            <h3 class="text-lg font-black font-orbitron text-white uppercase tracking-tight">{{ $game->localizedName() }}</h3>
                                            <p class="mt-1 text-xs text-zinc-500">{{ $gameTrailer->title }}</p>
                                        </div>

                                        <a href="{{ $stream['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-zinc-300 transition hover:border-zinc-600 hover:text-white">
                                            <i data-lucide="external-link" class="w-4 h-4"></i>
                                            Open on {{ $stream['label'] }}
                                        </a>
                                    </div>
                                </article>
                            @endif
                        @endforeach
                    @endforeach
                </div>
            @endif
    </section>

    @if(! $isAdminView)
        <section class="space-y-5">
            <h2 class="text-xl font-black font-orbitron text-white uppercase tracking-widest">Tournament Broadcasts</h2>

            @if($tournaments->isEmpty())
                <div class="bg-[#0c081d] border border-purple-500/15 rounded-2xl p-8 text-center">
                    <i data-lucide="tv" class="w-12 h-12 text-zinc-700 mx-auto mb-4"></i>
                    <h3 class="text-sm font-black font-orbitron tracking-wider text-zinc-300 uppercase">No Tournament Broadcasts</h3>
                    <p class="text-xs text-zinc-500 mt-2">Tournament broadcasts appear when admins attach provider stream URLs to events.</p>
                </div>
            @else
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    @foreach($tournaments as $tournament)
                        @php
                            $streamItems = $streamService->streamsForTournament($tournament);
                            $status = $streamService->statusLabel($tournament);
                        @endphp

                        @foreach($streamItems as $stream)
                            <article class="overflow-hidden rounded-2xl border border-zinc-800/80 bg-zinc-950/70 shadow-2xl">
                                <div class="aspect-video bg-black">
                                    <iframe class="h-full w-full" src="{{ $stream['embed_url'] }}" title="{{ $tournament->name }} {{ $stream['label'] }} stream" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share; fullscreen" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
                                </div>

                                <div class="space-y-5 p-5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-widest {{ $status['class'] }}">{{ $status['label'] }}</span>
                                        <span class="rounded-full border border-zinc-700 bg-zinc-900 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-zinc-400">{{ $stream['label'] }}</span>
                                        <span class="rounded-full border border-cyan-500/20 bg-cyan-500/10 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-cyan-300">{{ $tournament->game->localizedName() }}</span>
                                    </div>

                                    <div>
                                        <h2 class="text-xl font-black font-orbitron text-white uppercase tracking-tight">{{ $tournament->name }}</h2>
                                        <p class="mt-1 text-xs text-zinc-500">Starts {{ $tournament->start_at ? $tournament->start_at->format('M d, Y h:i A') : 'TBD' }}</p>
                                    </div>

                                    <div class="flex flex-col gap-3 sm:flex-row">
                                        <a href="/tournaments/{{ $tournament->uuid }}/view" wire:navigate class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-cyan-500 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-zinc-950 transition hover:bg-cyan-300">
                                            <i data-lucide="swords" class="w-4 h-4"></i>
                                            Tournament
                                        </a>
                                        <a href="{{ $stream['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-zinc-300 transition hover:border-zinc-600 hover:text-white">
                                            <i data-lucide="external-link" class="w-4 h-4"></i>
                                            Open on {{ $stream['label'] }}
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</div>
