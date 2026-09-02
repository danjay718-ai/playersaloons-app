@php
    $isHeadToHead = $competitionType === 'head_to_head';
    $competitionLabel = $isHeadToHead ? 'Head-to-Head' : 'Tournaments';
    $competitionSingular = $isHeadToHead ? 'Head-to-Head' : 'Tournament';
@endphp
<div class="space-y-8">
    <section class="relative -mx-4 -mt-4 overflow-hidden border-b border-zinc-800 sm:-mx-6 md:rounded-2xl md:border lg:-mx-0">
        <div class="h-64 sm:h-80 lg:h-96">
            @if($game->bannerUrl())
                <img src="{{ $game->bannerUrl() }}" alt="{{ $game->localizedName() }} banner" class="h-full w-full object-cover">
            @else
                <div class="h-full w-full bg-[radial-gradient(circle_at_70%_20%,rgba(139,92,246,.38),transparent_36%),linear-gradient(120deg,#09090b,#18102f,#050505)]"></div>
            @endif
        </div>
        <div class="absolute inset-0 bg-gradient-to-t from-zinc-950 via-zinc-950/25 to-black/20"></div>
        <div class="absolute inset-x-0 bottom-0 flex items-end gap-4 p-5 sm:gap-6 sm:p-8">
            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl border-2 border-white/15 bg-zinc-900 shadow-2xl sm:h-28 sm:w-28">
                @if($game->cardImageUrl())<img src="{{ $game->cardImageUrl() }}" alt="{{ $game->localizedName() }}" class="h-full w-full object-cover">@else<div class="flex h-full w-full items-center justify-center"><i data-lucide="gamepad-2" class="h-9 w-9 text-violet-400"></i></div>@endif
            </div>
            <div class="min-w-0 pb-1"><p class="text-[10px] font-black uppercase tracking-[0.3em] text-violet-300">Game Hub</p><h1 class="mt-1 truncate font-orbitron text-2xl font-black uppercase text-white sm:text-4xl">{{ $game->localizedName() }}</h1></div>
        </div>
    </section>

    <nav class="flex gap-2 overflow-x-auto border-b border-zinc-800 pb-3 [scrollbar-width:none]">
        @foreach(['overview' => 'Overview', 'browse' => 'Browse '.$competitionLabel, 'streams' => 'Streams'] as $key => $label)
            <button wire:click="$set('activeTab', '{{ $key }}')" class="rounded-lg px-5 py-2.5 font-orbitron text-[10px] font-black uppercase tracking-widest transition {{ $activeTab === $key ? 'bg-violet-600 text-white' : 'text-zinc-500 hover:bg-zinc-900 hover:text-white' }}">{{ $label }}</button>
        @endforeach
    </nav>

    @if($activeTab === 'overview')
        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
            <div class="rounded-2xl border border-zinc-800 bg-zinc-900/40 p-6"><p class="text-[10px] font-black uppercase tracking-[0.25em] text-violet-400">About the game</p><h2 class="mt-3 font-orbitron text-xl font-black uppercase text-white">Overview</h2><div class="prose prose-invert mt-4 max-w-none text-sm leading-7 text-zinc-400">{!! nl2br(e($game->localizedDescription() ?: 'Game information will be added soon.')) !!}</div></div>
            <aside class="rounded-2xl border border-zinc-800 bg-zinc-950/70 p-6"><p class="text-[10px] font-black uppercase tracking-widest text-zinc-600">Active {{ $competitionLabel }}</p><p class="mt-3 font-orbitron text-4xl font-black text-violet-400">{{ $game->tournaments()->where('competition_type', $isHeadToHead ? 'head_to_head' : 'tournament')->whereIn('status', ['REGISTRATION_OPEN','REGISTRATION_CLOSED','CHECKIN_OPEN','CHECKIN_CLOSED','BRACKET_GENERATED','ONGOING'])->count() }}</p><p class="mt-2 text-xs text-zinc-500">Open, check-in, and live {{ strtolower($competitionLabel) }}.</p></aside>
        </section>
    @elseif($activeTab === 'browse')
        <section class="space-y-6">
            <div class="space-y-4"><div class="flex items-end justify-between gap-4"><div><p class="text-[10px] font-black uppercase tracking-[0.25em] text-amber-400">Hand-picked events</p><h2 class="mt-2 font-orbitron text-xl font-black uppercase text-white">Featured {{ $competitionLabel }}</h2></div><span class="hidden text-xs text-zinc-500 sm:block">Highlights from active {{ strtolower($competitionLabel) }}</span></div>@if(($featuredGroups && $featuredGroups->isNotEmpty()) || $featuredTournaments->isNotEmpty())<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@if($featuredGroups)@foreach($featuredGroups as $template)<x-player.v2-tournament-parent-card :template="$template" :public-view="$publicView ?? false" />@endforeach @else @foreach($featuredTournaments as $tournament)<x-player.tournament-card :tournament="$tournament" :action-label="'View '.$competitionSingular" />@endforeach @endif</div>@else<div class="rounded-2xl border border-dashed border-zinc-800 p-8 text-center text-sm text-zinc-600">No featured {{ strtolower($competitionLabel) }} for this game yet.</div>@endif</div>
            <div class="rounded-2xl border border-zinc-800 bg-zinc-900/40 p-4 sm:p-5"><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search {{ strtolower($competitionLabel) }}" class="rounded-xl border border-zinc-800 bg-zinc-950 px-4 py-3 text-sm text-white outline-none focus:border-violet-500">
                <input wire:model.live="startDate" type="date" class="rounded-xl border border-zinc-800 bg-zinc-950 px-4 py-3 text-sm text-zinc-300 [color-scheme:dark]">
                <select wire:model.live="platformId" class="rounded-xl border border-zinc-800 bg-zinc-950 px-3 py-3 text-sm text-zinc-300"><option value="">All platforms</option>@foreach($platforms as $platform)<option value="{{ $platform->id }}">{{ $platform->name }}</option>@endforeach</select>
                <select wire:model.live="frequency" class="rounded-xl border border-zinc-800 bg-zinc-950 px-3 py-3 text-sm text-zinc-300"><option value="">All frequencies</option><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="one-time">One-time</option></select>
            </div></div>
            <div class="flex gap-2">@foreach(['upcoming' => 'Upcoming','ongoing' => 'Ongoing','past' => 'Past'] as $key => $label)<button wire:click="$set('tournamentStatus', '{{ $key }}')" class="rounded-lg px-4 py-2 text-[10px] font-black uppercase tracking-wider {{ $tournamentStatus === $key ? 'bg-violet-600 text-white' : 'bg-zinc-900 text-zinc-500' }}">{{ $label }}</button>@endforeach</div>
            @if($tournamentGroups ? $tournamentGroups->count() : $tournaments->count())<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@if($tournamentGroups)@foreach($tournamentGroups as $template)<x-player.v2-tournament-parent-card :template="$template" :tab="$tournamentStatus" :public-view="$publicView ?? false" />@endforeach @else @foreach($tournaments as $tournament)<x-player.tournament-card :tournament="$tournament" action-label="View {{ $competitionSingular }}" />@endforeach @endif</div><div>{{ ($tournamentGroups ?? $tournaments)->links('vendor.livewire.custom-pagination') }}</div>@else<div class="rounded-2xl border border-dashed border-zinc-800 p-10 text-center text-sm text-zinc-600">No {{ strtolower($competitionLabel) }} match these filters.</div>@endif
        </section>
    @else
        <section class="space-y-5"><div><p class="text-[10px] font-black uppercase tracking-[0.25em] text-red-400">Watch the action</p><h2 class="mt-2 font-orbitron text-xl font-black uppercase text-white">{{ $game->localizedName() }} Streams</h2></div>
            @if($streams->isNotEmpty())<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@foreach($streams as $stream)@php $streamThumbnail = $stream->thumbnail_url ?: $stream->tournament?->banner_url; @endphp<a href="{{ route('streams.watch', $stream->id) }}" wire:navigate class="group overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/50"><div class="relative aspect-video bg-black">@if($streamThumbnail)<img src="{{ $streamThumbnail }}" alt="{{ $stream->title }}" class="h-full w-full object-cover">@else<div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-violet-950 to-zinc-950"><i data-lucide="play" class="h-9 w-9 text-violet-400"></i></div>@endif@if($stream->is_live)<span class="absolute left-3 top-3 rounded bg-red-600 px-2 py-1 text-[9px] font-black uppercase text-white">Live</span>@endif</div><div class="p-4"><h3 class="line-clamp-1 font-bold text-white group-hover:text-violet-300">{{ $stream->title ?: 'Live Stream' }}</h3><p class="mt-2 text-xs text-zinc-500">{{ $stream->user?->profile?->display_name ?? $stream->user?->username ?? ucfirst($stream->provider) }} · {{ number_format($stream->viewer_count) }} viewers</p></div></a>@endforeach</div>@else<div class="rounded-2xl border border-dashed border-zinc-800 p-10 text-center text-sm text-zinc-600">No public streams for this game yet.</div>@endif
        </section>
    @endif
</div>
