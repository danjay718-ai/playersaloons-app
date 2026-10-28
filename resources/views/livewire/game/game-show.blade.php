@php
    $isHeadToHead = $competitionType === 'head_to_head';
    $competitionLabel = $isHeadToHead ? __('Head-to-Head') : __('Tournaments');
    $competitionSingular = $isHeadToHead ? __('Head-to-Head') : __('Tournament');
    $fixedFrequency = $fixedFrequency ?? false;
    $frequencyLabel = $fixedFrequency ? __(ucfirst($frequency)).' ' : '';
@endphp
<div class="player-game-hub space-y-8">
    <section class="relative -mx-4 -mt-4 overflow-hidden border-b border-cyan-500/20 sm:-mx-6 md:rounded-2xl md:border lg:-mx-0">
        <div class="h-64 sm:h-80 lg:h-96">
            @if($game->bannerUrl())
                <img src="{{ $game->bannerUrl() }}" alt="{{ $game->localizedName() }} banner" class="h-full w-full object-cover">
            @else
                <div class="h-full w-full bg-[radial-gradient(circle_at_70%_20%,rgba(34,230,255,.32),transparent_40%),linear-gradient(120deg,#05080f,#0d1726,#05080f)]"></div>
            @endif
        </div>
        <div class="absolute inset-0 bg-gradient-to-t from-zinc-950 via-zinc-950/30 to-black/20"></div>
        <div class="absolute inset-x-0 bottom-0 flex items-end gap-4 p-5 sm:gap-6 sm:p-8">
            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl border-2 border-cyan-400/30 bg-zinc-900 shadow-2xl sm:h-28 sm:w-28">
                @if($game->cardImageUrl())<img src="{{ $game->cardImageUrl() }}" alt="{{ $game->localizedName() }}" class="h-full w-full object-cover">@else<div class="flex h-full w-full items-center justify-center"><i data-lucide="gamepad-2" class="h-9 w-9 text-cyan-400"></i></div>@endif
            </div>
            <div class="min-w-0 pb-1"><p class="gr-eyebrow !mb-1"><span class="gr-slash">//</span> Game Hub</p><h1 class="mt-1 truncate font-orbitron text-2xl font-black uppercase text-white sm:text-4xl">{{ $game->localizedName() }}</h1></div>
        </div>
    </section>

    <nav class="flex gap-2 overflow-x-auto border-b border-cyan-500/15 pb-3 [scrollbar-width:none]">
        @foreach(['overview' => 'Overview', 'browse' => 'Browse '.$frequencyLabel.$competitionLabel, 'streams' => 'Streams'] as $key => $label)
            <button wire:click="$set('activeTab', '{{ $key }}')" class="rounded-full px-5 py-2.5 font-orbitron text-[10px] font-black uppercase tracking-widest transition {{ $activeTab === $key ? 'bg-lime-400 text-zinc-950 shadow-[0_0_20px_rgba(198,255,61,.35)]' : 'border border-white/10 bg-zinc-900/60 text-zinc-400 hover:border-cyan-400/40 hover:text-white' }}">{{ $label }}</button>
        @endforeach
    </nav>

    @if($activeTab === 'overview')
        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
            <div class="rounded-2xl border border-cyan-500/20 bg-zinc-900/60 p-6"><p class="gr-eyebrow !mb-2"><span class="gr-slash">//</span> About the game</p><h2 class="font-orbitron text-xl font-black uppercase text-white">Overview</h2><div class="prose prose-invert mt-4 max-w-none text-sm leading-7 text-zinc-300">{!! nl2br(e($game->localizedDescription() ?: 'Game information will be added soon.')) !!}</div></div>
            <aside class="rounded-2xl border border-cyan-500/20 bg-zinc-950/80 p-6"><p class="text-[10px] font-black uppercase tracking-widest text-zinc-400">{{ __('Active :value1:value2', ['value1' => ($frequencyLabel), 'value2' => ($competitionLabel)]) }}</p><p class="mt-3 font-orbitron text-4xl font-black text-lime-300">{{ $activeCompetitionCount }}</p><p class="mt-2 text-xs text-zinc-400">{{ __('Upcoming and ongoing :value1.', ['value1' => (strtolower($frequencyLabel.$competitionLabel))]) }}</p></aside>
        </section>
    @elseif($activeTab === 'browse')
        <section class="space-y-6">
            @if($featuredTournaments->isNotEmpty())
                <div class="space-y-4">
                    <div class="flex items-end justify-between gap-4"><div><p class="gr-eyebrow !mb-2 !text-amber-300"><span class="gr-slash">//</span> Hand-picked events</p><h2 class="font-orbitron text-xl font-black uppercase text-white">{{ __('Featured :value1:value2', ['value1' => ($frequencyLabel), 'value2' => ($competitionLabel)]) }}</h2></div><span class="hidden text-xs text-zinc-400 sm:block">{{ __('Highlights from active :value1', ['value1' => (strtolower($frequencyLabel.$competitionLabel))]) }}</span></div>
                    <div class="hidden rounded-xl border border-white/5 bg-zinc-950/50 px-5 py-2.5 text-[10px] font-extrabold uppercase tracking-widest text-zinc-500 md:grid md:grid-cols-[52px_minmax(0,1.8fr)_120px_130px_170px_44px] md:items-center md:gap-5" aria-hidden="true">
                        <span></span>
                        <span>{{ __('Competition') }}</span>
                        <span>{{ __('Entry Fee') }}</span>
                        <span>{{ __('Prize') }}</span>
                        <span>{{ __('Full Capacity') }}</span>
                        <span></span>
                    </div>
                    <div class="flex flex-col gap-2.5">@foreach($featuredTournaments as $tournament)<x-player.tournament-card :tournament="$tournament" :action-label="__('View :competition', ['competition' => $competitionSingular])" :public-view="$publicView ?? false" />@endforeach</div>
                </div>
            @endif
            <div class="player-filter-panel rounded-2xl border border-cyan-500/20 bg-zinc-900/60 p-4 sm:p-5"><div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search {{ strtolower($frequencyLabel.$competitionLabel) }}" class="rounded-xl border border-white/10 bg-zinc-950/90 px-4 py-3 text-sm text-white outline-none focus:border-lime-400">
                <input wire:model.live="startDate" type="date" class="rounded-xl border border-white/10 bg-zinc-950/90 px-4 py-3 text-sm text-zinc-200 [color-scheme:dark]">
                <select wire:model.live="platformId" class="rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-3 text-sm text-zinc-200"><option value="">All platforms</option>@foreach($platforms as $platform)<option value="{{ $platform->id }}">{{ $platform->name }}</option>@endforeach</select>
                @if($fixedFrequency)
                    <div class="flex items-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 px-3 py-3 text-sm font-semibold text-cyan-200"><i data-lucide="calendar-days" class="mr-2 h-4 w-4 text-lime-300"></i>{{ __(':value1 Tournaments', ['value1' => (ucfirst($frequency))]) }}</div>
                @else
                    <select wire:model.live="frequency" class="rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-3 text-sm text-zinc-200"><option value="">All frequencies</option><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="one-time">One-time</option></select>
                @endif
                @if($isHeadToHead)
                    <div class="flex items-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 px-3 py-3 text-sm font-semibold text-cyan-200"><i data-lucide="swords" class="mr-2 h-4 w-4 text-lime-300"></i>1v1 only</div>
                @else
                    <div class="flex min-w-0 gap-2"><select wire:model.live="maxTeams" aria-label="Filter by maximum teams" class="min-w-0 flex-1 rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-3 text-sm text-zinc-200"><option value="">All max teams</option>@foreach([4, 8, 16, 32, 64, 128] as $maximum)<option value="{{ $maximum }}">{{ __(':value1 teams', ['value1' => ($maximum)]) }}</option>@endforeach<option value="custom">Custom</option></select>@if($maxTeams === 'custom')<input wire:model.live.debounce.300ms="customMaxTeams" type="number" min="2" max="128" step="2" aria-label="Custom maximum teams" placeholder="Max" class="w-24 rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-3 text-sm text-white outline-none focus:border-lime-400">@endif</div>
                @endif
            </div></div>
            <div class="flex gap-2">@foreach(['upcoming' => 'Upcoming','ongoing' => 'Ongoing','past' => 'Past'] as $key => $label)<button wire:click="$set('tournamentStatus', '{{ $key }}')" class="rounded-full px-4 py-2 font-orbitron text-[10px] font-black uppercase tracking-wider transition {{ $tournamentStatus === $key ? 'bg-lime-400 text-zinc-950' : 'border border-white/10 bg-zinc-900/60 text-zinc-400 hover:text-white' }}">{{ $label }}</button>@endforeach</div>
            @if($tournaments->count())
                <div class="hidden rounded-xl border border-white/5 bg-zinc-950/50 px-5 py-2.5 text-[10px] font-extrabold uppercase tracking-widest text-zinc-500 md:grid md:grid-cols-[52px_minmax(0,1.8fr)_120px_130px_170px_44px] md:items-center md:gap-5" aria-hidden="true">
                    <span></span>
                    <span>{{ __('Competition') }}</span>
                    <span>{{ __('Entry Fee') }}</span>
                    <span>{{ __('Prize') }}</span>
                    <span>{{ __('Full Capacity') }}</span>
                    <span></span>
                </div>
                <div class="flex flex-col gap-2.5">@foreach($tournaments as $tournament)<x-player.tournament-card :tournament="$tournament" :action-label="__('View :competition', ['competition' => $competitionSingular])" :public-view="$publicView ?? false" />@endforeach</div>
                <div class="flex flex-col gap-4 border-t border-zinc-900/60 pt-6 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-zinc-400">{{ __('Showing :value1–:value2 of :value3 results', ['value1' => ($tournaments->firstItem()), 'value2' => ($tournaments->lastItem()), 'value3' => ($tournaments->total())]) }}</p>{{ $tournaments->links('vendor.livewire.custom-pagination') }}</div>
            @else
                <div class="rounded-2xl border border-dashed border-zinc-800 p-10 text-center text-sm text-zinc-500">{{ __('No :value1 match these filters.', ['value1' => (strtolower($competitionLabel))]) }}</div>
            @endif
        </section>
    @else
        <section class="space-y-5"><div><p class="gr-eyebrow !mb-2 !text-red-400"><span class="gr-slash">//</span> Watch the action</p><h2 class="font-orbitron text-xl font-black uppercase text-white">{{ __(':value1 Streams', ['value1' => ($game->localizedName())]) }}</h2></div>
            @if($streams->isNotEmpty())<div class="flex flex-col gap-3.5">@foreach($streams as $stream)@php $streamThumbnail = $stream->thumbnail_url ?: $stream->tournament?->banner_url; @endphp<a href="{{ route('streams.watch', $stream->id) }}" wire:navigate class="player-clickable-card group flex flex-col gap-4 overflow-hidden rounded-2xl border border-cyan-500/20 bg-zinc-900/60 p-4 transition hover:border-lime-400/50 sm:flex-row sm:items-center"><div class="relative aspect-video w-full shrink-0 overflow-hidden rounded-xl bg-black sm:w-52">@if($streamThumbnail)<img src="{{ $streamThumbnail }}" alt="{{ $stream->title }}" class="h-full w-full object-cover">@else<div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-cyan-950 to-zinc-950"><i data-lucide="play" class="h-9 w-9 text-cyan-400"></i></div>@endif@if($stream->is_live)<span class="absolute left-3 top-3 rounded bg-red-600 px-2 py-1 text-[9px] font-black uppercase text-white">Live</span>@endif</div><div class="min-w-0 flex-1"><h3 class="truncate font-orbitron font-bold text-white group-hover:text-lime-300">{{ $stream->title ?: 'Live Stream' }}</h3><p class="mt-2 text-xs text-zinc-400">{{ __(':value1 · :value2 viewers', ['value1' => ($stream->user?->profile?->display_name ?? $stream->user?->username ?? ucfirst($stream->provider)), 'value2' => (number_format($stream->viewer_count))]) }}</p></div></a>@endforeach</div>@else<div class="rounded-2xl border border-dashed border-zinc-800 p-10 text-center text-sm text-zinc-500">No public streams for this game yet.</div>@endif
        </section>
    @endif
</div>
