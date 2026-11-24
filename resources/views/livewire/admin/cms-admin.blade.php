<div class="admin-game-management" x-data="gameManagementUi($wire, {{ Js::from($defaultGameCategoryId ?? null) }})">


    <!-- Feedback Alerts -->
    @if(session()->has('success'))
        <div role="status" class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <i data-lucide="check-circle" class="w-4 h-4 mr-2 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div role="alert" class="bg-red-500/10 border border-red-500/25 text-red-300 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <i data-lucide="alert-circle" class="w-4 h-4 mr-2 shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Games Tab Content -->
    @if($tab === 'games')
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-lg font-black text-white">Game Management</h2><p class="mt-1 text-xs text-slate-400">Create games, organize categories, assign platforms, manage artwork, or soft delete catalog entries while preserving connected history.</p></div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="openCategoryManager" class="inline-flex min-h-[40px] items-center justify-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-200 transition hover:border-cyan-400/60 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-400">
                    <i data-lucide="folder-kanban" class="h-4 w-4 text-cyan-400"></i><span>{{ __('Manage Categories') }}</span>
                </button>
                @if($this->canManageCategories())
                    <button type="button" x-on:click="openCreateGame()" class="inline-flex min-h-[40px] items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-400">
                        <i data-lucide="plus" class="h-4 w-4"></i><span>Add New Game</span>
                    </button>
                @endif
            </div>
        </div>

        <!-- Category Tabs -->
        <div class="mb-4 rounded-xl border border-slate-800 bg-[#0b1220] p-3">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-2">
                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ __('Game Categories') }}</span>
                <span wire:loading wire:target="setGameCategoryFilter,gameSearch,gameCatalogFilter,gamePlatformFilter,gameTemplateFilter,gameArtworkFilter,setGameRecordTab" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-cyan-300">
                    <span class="h-2 w-2 rounded-full bg-cyan-400 animate-pulse"></span>{{ __('Updating catalog…') }}
                </span>
            </div>
            <div role="tablist" aria-label="{{ __('Filter games by category') }}" x-data="categoryTablist()" @keydown="onKeydown($event)" class="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $gameCategoryFilter === '' ? 'true' : 'false' }}"
                    wire:click="setGameCategoryFilter('')"
                    class="inline-flex min-h-[38px] shrink-0 items-center gap-2 rounded-lg border px-3.5 py-2 text-xs font-bold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-400 {{ $gameCategoryFilter === '' ? 'border-lime-400/70 bg-lime-400/15 text-lime-200' : 'border-slate-800 bg-slate-900/70 text-slate-300 hover:border-slate-600 hover:text-white' }}"
                >
                    <span>{{ __('All Games') }}</span>
                    <span class="rounded-full bg-slate-950/80 px-2 py-0.5 font-mono text-[10px] tabular-nums {{ $gameCategoryFilter === '' ? 'text-lime-300' : 'text-slate-400' }}">{{ $allGamesTabCount }}</span>
                </button>
                @foreach($gameCategories as $category)
                    @php
                        $tabCount = ($gameRecordTab === 'deleted' && $this->canViewDeletedGames())
                            ? (int) $category->deleted_record_count
                            : (int) $category->active_record_count;
                        $isSelected = $gameCategoryFilter === $category->slug;
                    @endphp
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                        wire:key="admin-cat-tab-{{ $category->id }}"
                        wire:click="setGameCategoryFilter('{{ $category->slug }}')"
                        class="inline-flex min-h-[38px] shrink-0 items-center gap-2 rounded-lg border px-3.5 py-2 text-xs font-bold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-400 {{ $isSelected ? 'border-lime-400/70 bg-lime-400/15 text-lime-200' : 'border-slate-800 bg-slate-900/70 text-slate-300 hover:border-slate-600 hover:text-white' }}"
                    >
                        <span>{{ $category->name }}</span>
                        <span class="rounded-full bg-slate-950/80 px-2 py-0.5 font-mono text-[10px] tabular-nums {{ $isSelected ? 'text-lime-300' : 'text-slate-400' }}">{{ $tabCount }}</span>
                        @if(! $category->is_active)
                            <span class="rounded border border-amber-500/40 bg-amber-500/15 px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-wider text-amber-300">{{ __('Inactive') }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
        <div class="mb-5 rounded-xl border border-slate-800 bg-slate-900/60 p-4">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="setGameRecordTab('active')" class="rounded-lg px-3 py-2 text-xs font-semibold {{ $gameRecordTab !== 'deleted' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">Games</button>
                    @if($this->canViewDeletedGames())
                        <button type="button" wire:click="setGameRecordTab('deleted')" class="rounded-lg px-3 py-2 text-xs font-semibold {{ $gameRecordTab === 'deleted' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">Deleted Games</button>
                    @endif
                </div>
                <button type="button" wire:click="clearGameFilters" class="text-left text-xs font-bold text-slate-400 hover:text-white">Clear filters</button>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <label class="xl:col-span-2"><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Search</span><div class="relative"><i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-600"></i><input type="search" wire:model.live.debounce.350ms="gameSearch" placeholder="Name, slug, description, or platform" class="w-full rounded-lg border border-slate-800 bg-slate-950 py-2 pl-9 pr-3 text-xs text-slate-200 placeholder:text-slate-600 focus:border-indigo-500 focus:outline-none"></div></label>
                @if($gameRecordTab !== 'deleted')<label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Catalog status</span><select wire:model.live="gameCatalogFilter" class="game-filter-field"><option value="">All statuses</option><option value="enabled">Enabled</option><option value="disabled">Disabled</option></select></label>@endif
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Platform</span><select wire:model.live="gamePlatformFilter" class="game-filter-field"><option value="">All platforms</option>@foreach($gamePlatforms as $platform)<option value="{{ $platform->id }}">{{ $platform->name }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Tournament template</span><select wire:model.live="gameTemplateFilter" class="game-filter-field"><option value="">All templates</option><option value="configured">Configured</option><option value="missing">Not configured</option></select></label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Artwork</span><select wire:model.live="gameArtworkFilter" class="game-filter-field"><option value="">All artwork</option><option value="complete">Logo + cover photo present</option><option value="missing_card">Missing game logo</option><option value="missing_banner">Missing game cover photo</option></select></label>
            </div>
        </div>
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="p-4">Game Slug</th>
                            <th class="p-4">Logo / Cover Photo</th>
                            <th class="p-4">Name (EN)</th>
                            <th class="p-4">{{ __('Category') }}</th>
                            <th class="p-4">Platforms</th>
                            <th class="p-4">Description</th>
                            <th class="p-4">Catalog status</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        @forelse($games as $game)
                            <tr class="hover:bg-slate-900/40 {{ $game->trashed() ? 'opacity-65' : '' }}" wire:key="game-{{ $game->id }}">
                                <td class="p-4 font-semibold text-slate-200 font-mono">
                                    {{ $game->slug }}
                                    <span class="block text-[9px] text-slate-500 font-normal mt-0.5">{{ $game->uuid }}</span>
                                </td>
                                <td class="p-4">
                                    @if($game->cardArtworkUrl() || $game->bannerUrl())
                                        <div class="flex gap-2">
                                            @if($game->cardArtworkUrl())<div class="h-[45px] w-[60px] overflow-hidden rounded-lg border border-slate-800 bg-slate-950"><img src="{{ $game->cardArtworkUrl() }}" alt="{{ $game->slug }} card" loading="lazy" decoding="async" class="h-full w-full object-cover"></div>@endif
                                            @if($game->bannerUrl())
                                                <div class="h-12 w-20 overflow-hidden rounded-lg border border-slate-800 bg-slate-950">
                                                    <img src="{{ $game->bannerUrl() }}" alt="{{ $game->slug }} banner" loading="lazy" decoding="async" class="h-full w-full object-cover">
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-[10px] text-slate-600">No banner</span>
                                    @endif
                                </td>
                                <td class="p-4 text-slate-200 font-semibold">
                                    {{ $game->localizedName() }}
                                    @if(config('features.tournament_v2.enabled'))
                                        <span class="mt-1 block text-[9px] font-bold uppercase tracking-wider {{ $game->tournamentDefaults ? 'text-violet-300' : 'text-slate-600' }}">
                                            {{ $game->tournamentDefaults ? 'Tournament template configured' : 'Tournament template not configured' }}
                                        </span>
                                        <span class="mt-1 block text-[9px] font-bold uppercase tracking-wider {{ $game->headToHeadDefaults ? 'text-fuchsia-300' : 'text-slate-600' }}">
                                            {{ $game->headToHeadDefaults ? 'H2H template configured' : 'H2H template not configured' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    @if($game->category)
                                        <span class="inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-[10px] font-bold {{ $game->category->is_active ? 'border-cyan-500/25 bg-cyan-500/10 text-cyan-200' : 'border-amber-500/35 bg-amber-500/10 text-amber-200' }}">
                                            <span>{{ $game->category->name }}</span>
                                        </span>
                                        @if(! $game->category->is_active)
                                            <span class="mt-1 block text-[9px] font-bold uppercase tracking-wider text-amber-300">{{ __('Category inactive') }}</span>
                                        @endif
                                    @else
                                        <span class="text-[10px] text-slate-500">—</span>
                                    @endif
                                    @if($game->isPlayStrategyManaged())
                                        <span class="mt-1 inline-flex items-center gap-1 rounded border border-lime-400/35 bg-lime-400/10 px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-wider text-lime-300">
                                            PlayStrategy · {{ $game->playStrategyVariant() }}
                                        </span>
                                    @endif
                                </td>
                                <td class="p-4"><div class="flex max-w-[180px] flex-wrap gap-1">@forelse($game->platforms as $platform)<span class="rounded border border-slate-700 bg-slate-900 px-1.5 py-0.5 text-[9px] text-slate-400">{{ $platform->name }}</span>@empty<span class="text-[10px] text-slate-600">All / unassigned</span>@endforelse</div></td>
                                <td class="p-4 text-slate-400 max-w-[280px] truncate" title="{{ $game->localizedDescription() }}">
                                    {{ $game->localizedDescription() ?? __('No description') }}
                                </td>
                                <td class="p-4">
                                    @if($game->trashed())
                                        <span class="inline-flex rounded border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[9px] font-bold uppercase text-amber-300">Deleted</span>
                                    @else
                                    <button wire:click="toggleGameActive({{ $game->id }})"
                                            class="inline-flex items-center px-2 py-0.5 rounded border text-[9px] font-bold uppercase transition-colors
                                            {{ $game->is_active 
                                               ? 'bg-emerald-500/10 text-emerald-450 border-emerald-500/20 hover:bg-emerald-500/20' 
                                               : 'bg-red-500/10 text-red-400 border-red-500/20 hover:bg-red-500/20' }}">
                                        {{ $game->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                    @endif
                                </td>
                                <td class="p-4 text-right">
                                    @if($game->trashed())
                                    <x-admin.action-dropdown>
                                        <div class="py-1">
                                            @can('games.restore')
                                                <button type="button" x-on:click="open = false" wire:click="restoreGame({{ $game->id }})" class="game-action-item"><i data-lucide="rotate-ccw" class="h-4 w-4 text-emerald-400"></i>Restore</button>
                                            @endcan
                                            @can('games.force_delete')
                                                <button type="button" x-on:click="open = false" wire:click="confirmPermanentDeleteGame({{ $game->id }})" class="game-action-item text-red-300"><i data-lucide="trash-2" class="h-4 w-4"></i>Permanent Delete</button>
                                            @endcan
                                        </div>
                                    </x-admin.action-dropdown>
                                    @else
                                    <div class="relative inline-block text-left" x-data="{ open: false }" x-on:click.outside="open = false">
                                        <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open" aria-label="Game actions" class="rounded-lg border border-slate-700 bg-slate-900 p-2 text-slate-400 hover:border-slate-600 hover:text-white"><i data-lucide="ellipsis-vertical" class="h-4 w-4"></i></button>
                                        <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 z-30 mt-2 w-52 overflow-hidden rounded-xl border border-slate-700 bg-slate-900 py-1 text-left shadow-2xl shadow-black/40">
                                            <button type="button" x-on:click="open = false; openEditGame({{ Illuminate\Support\Js::from([
                                                'id' => $game->id,
                                                'locale' => 'en',
                                                'slug' => $game->slug,
                                                'categoryId' => (int) $game->game_category_id,
                                                'gameplayProvider' => (string) ($game->gameplay_provider ?: 'manual'),
                                                'playStrategyVariant' => $game->playStrategySettings()['variant'],
                                                'playStrategyClockLimit' => $game->playStrategySettings()['clock_limit'],
                                                'playStrategyClockIncrement' => $game->playStrategySettings()['clock_increment'],
                                                'isActive' => (bool) $game->is_active,
                                                'bannerPath' => $game->bannerUrl() ?? '',
                                                'cardImagePath' => $game->cardArtworkUrl() ?? '',
                                                'platformIds' => $game->platforms->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                                                'translations' => $game->translations->mapWithKeys(fn ($translation) => [$translation->locale => ['name' => $translation->name, 'description' => $translation->description ?? '']])->all(),
                                            ]) }})" class="game-action-item"><i data-lucide="edit" class="h-4 w-4 text-indigo-400"></i>Edit game</button>
                                            @if(config('features.tournament_v2.enabled') && auth()->user()?->can('tournaments.manage'))
                                                <a href="{{ route('admin.games.tournament-defaults.edit', $game) }}" class="game-action-item"><i data-lucide="trophy" class="h-4 w-4 text-violet-300"></i>Tournament template</a>
                                                <a href="{{ route('admin.games.head-to-head-defaults.edit', $game) }}" class="game-action-item"><i data-lucide="swords" class="h-4 w-4 text-fuchsia-300"></i>Head-to-Head template</a>
                                            @endif
                                            <div class="my-1 border-t border-slate-800"></div>
                                            <livewire:admin.recoverable-delete resource="games" :menu-item="true" :record-id="$game->id" :key="'delete-games-'.$game->id" />
                                        </div>
                                    </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-8 text-center text-slate-500 italic">No games found. Add the first game to the catalog or clear your category filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            {{ $games->links('vendor.livewire.custom-pagination') }}
        </div>
    @endif

    <!-- Platforms Tab Content -->
    @if($tab === 'platforms')
        <div class="flex justify-end mb-4">
            <button wire:click="openPlatformCreateModal" 
                    class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs uppercase tracking-wider px-3.5 py-2.5 rounded-lg flex items-center shadow-md transition-colors">
                <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>
                <span>Add Platform</span>
            </button>
        </div>

        <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="p-4">Platform Name</th>
                            <th class="p-4">Slug</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        @forelse($platforms as $platform)
                            <tr class="hover:bg-slate-900/40" wire:key="platform-{{ $platform->id }}">
                                <td class="p-4 font-semibold text-slate-200">
                                    {{ $platform->name }}
                                </td>
                                <td class="p-4 text-slate-350 font-mono">
                                    {{ $platform->slug }}
                                </td>
                                <td class="p-4">
                                    <button wire:click="togglePlatformActive({{ $platform->id }})" 
                                            class="inline-flex items-center px-2 py-0.5 rounded border text-[9px] font-bold uppercase transition-colors
                                            {{ $platform->is_active 
                                               ? 'bg-emerald-500/10 text-emerald-450 border-emerald-500/20 hover:bg-emerald-500/20' 
                                               : 'bg-red-500/10 text-red-400 border-red-500/20 hover:bg-red-500/20' }}">
                                        {{ $platform->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </td>
                                <td class="p-4 text-right space-x-2">
                                    <button wire:click="openPlatformEditModal({{ $platform->id }})" class="p-1.5 text-indigo-400 hover:text-white bg-indigo-950/40 border border-indigo-900/50 rounded-lg" title="Edit Platform">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </button>
                                    <livewire:admin.recoverable-delete resource="platforms" :record-id="$platform->id" :key="'delete-platforms-'.$platform->id" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-slate-500 italic">No platforms created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            {{ $platforms->links('vendor.livewire.custom-pagination') }}
        </div>
    @endif

    <!-- Landing Page Tab Content -->
    @if($tab === 'landing')
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-900/60 p-4">
            <div><h2 class="text-sm font-bold text-white">{{ __('Pages & Navigation') }}</h2><p class="mt-1 text-xs text-slate-400">{{ __('Manage the pages and links shown in the top menu.') }}</p></div>
            <a href="{{ route('admin.cms', ['section' => 'navigation']) }}" class="rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Manage pages & links') }}</a>
        </div>
        <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="space-y-2">
                @foreach($landingSections as $section)
                    <button type="button"
                            wire:click="selectLandingSection({{ $section->id }})"
                            class="w-full rounded-lg border px-4 py-3 text-left transition-colors {{ $selectedLandingSectionId === $section->id ? 'border-indigo-500 bg-indigo-500/10 text-indigo-200' : 'border-slate-800 bg-[#0f172a] text-slate-400 hover:text-slate-200' }}">
                        <span class="block text-[10px] font-black uppercase tracking-widest">{{ $section->key }}</span>
                        <span class="mt-1 block truncate text-sm font-bold">{{ $section->title ?: 'Untitled section' }}</span>
                    </button>
                @endforeach
            </aside>

            @if($selectedLandingSectionId)
                @php($selectedLandingSection = $landingSections->firstWhere('id', $selectedLandingSectionId))
                <div class="space-y-6">
                    <form wire:submit.prevent="saveLandingSection" class="rounded-xl border border-slate-800 bg-[#0f172a] p-6">
                        <div class="mb-5 flex items-center justify-between gap-4 border-b border-slate-800 pb-4">
                            <div>
                                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-200">Section Content</h3>
                                <p class="mt-1 text-xs text-slate-500">Key: <span class="font-mono">{{ $selectedLandingSection?->key }}</span></p>
                            </div>
                            <label class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-400">
                                <input type="checkbox" wire:model="landingSectionIsActive" class="rounded border-slate-700 bg-slate-900 text-indigo-500">
                                Active
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Title</label>
                                <input type="text" wire:model="landingSectionTitle" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                                @error('landingSectionTitle') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Subtitle</label>
                                <input type="text" wire:model="landingSectionSubtitle" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                                @error('landingSectionSubtitle') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Body</label>
                                <textarea wire:model="landingSectionBody" rows="4" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none"></textarea>
                                @error('landingSectionBody') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                            </div>
                            @if($selectedLandingSection?->key === 'hero')
                                <div class="md:col-span-2">
                                    <label for="hero-rotating-phrases" class="mb-1 block text-xs font-bold text-slate-300">{{ __('Hero rotating phrases') }}</label>
                                    <textarea id="hero-rotating-phrases" wire:model="landingHeroRotatingWords" rows="4" aria-describedby="hero-rotating-help" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100"></textarea>
                                    <p id="hero-rotating-help" class="mt-1 text-xs text-slate-400">{{ __('One phrase per line, in display order. These rotate after the last phrase of the Title. Clear this field to keep only the Title phrase.') }}</p>
                                    @error('landingHeroRotatingWords') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                                </div>
                            @endif
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Media Path</label>
                                <input type="text" wire:model="landingSectionMediaPath" placeholder="/compressed_v1.mp4" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                                @error('landingSectionMediaPath') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Sort Order</label>
                                <input type="number" wire:model="landingSectionSortOrder" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                                @error('landingSectionSortOrder') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">CTA Label</label>
                                <input type="text" wire:model="landingSectionCtaLabel" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                                @error('landingSectionCtaLabel') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">CTA URL</label>
                                <input type="text" wire:model="landingSectionCtaUrl" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                                @error('landingSectionCtaUrl') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white hover:bg-indigo-500">
                                Save Section
                            </button>
                        </div>
                    </form>

                    <div class="rounded-xl border border-slate-800 bg-[#0f172a]">
                        <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                            <div>
                                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-200">Section Items</h3>
                                <p class="mt-1 text-xs text-slate-500">Cards, steps, stat labels, review entries, or footer links.</p>
                            </div>
                            <button type="button" wire:click="openCreateLandingItemModal({{ $selectedLandingSectionId }})" class="rounded-lg bg-indigo-600 px-3.5 py-2.5 text-xs font-bold uppercase tracking-wider text-white hover:bg-indigo-500">
                                Add Item
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-slate-800 text-[10px] font-bold uppercase text-slate-400">
                                        <th class="p-4">Item</th>
                                        <th class="p-4">Label / URL</th>
                                        <th class="p-4">Order</th>
                                        <th class="p-4">Status</th>
                                        <th class="p-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/50">
                                    @forelse($selectedLandingSection?->items ?? [] as $item)
                                        <tr wire:key="landing-item-{{ $item->id }}">
                                            <td class="p-4">
                                                <span class="block font-semibold text-slate-200">{{ $item->title ?: $item->item_key }}</span>
                                                <span class="mt-1 block max-w-md truncate text-slate-500">{{ $item->body }}</span>
                                            </td>
                                            <td class="p-4 text-slate-400">
                                                <span class="block">{{ $item->label ?: '-' }}</span>
                                                <span class="block font-mono text-[10px] text-slate-600">{{ $item->url ?: '-' }}</span>
                                            </td>
                                            <td class="p-4 text-slate-400">{{ $item->sort_order }}</td>
                                            <td class="p-4">
                                                <button type="button" wire:click="toggleLandingItemActive({{ $item->id }})" class="rounded border px-2 py-0.5 text-[9px] font-bold uppercase {{ $item->is_active ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-400' : 'border-red-500/20 bg-red-500/10 text-red-400' }}">
                                                    {{ $item->is_active ? 'Active' : 'Disabled' }}
                                                </button>
                                            </td>
                                            <td class="p-4 text-right space-x-2">
                                                <button type="button" wire:click="openEditLandingItemModal({{ $item->id }})" class="rounded-lg border border-indigo-900/50 bg-indigo-950/40 p-1.5 text-indigo-400 hover:text-white" title="Edit">
                                                    <i data-lucide="edit" class="h-4 w-4"></i>
                                                </button>
                                                <livewire:admin.recoverable-delete resource="landing_items" :record-id="$item->id" :key="'delete-landing_items-'.$item->id" />
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="p-8 text-center text-slate-500 italic">No editable items for this section yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Public Navigation Tab Content -->
    @if($tab === 'navigation')
        <div class="flex justify-end mb-4">
            <button wire:click="openCreateNavigationItemModal"
                    class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs uppercase tracking-wider px-3.5 py-2.5 rounded-lg flex items-center shadow-md transition-colors">
                <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>
                <span>Add Nav Item</span>
            </button>
        </div>

        <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="p-4">Label</th>
                            <th class="p-4">URL</th>
                            <th class="p-4">Visibility</th>
                            <th class="p-4">Order</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        @forelse($publicNavigationItems as $item)
                            <tr class="hover:bg-slate-900/40" wire:key="nav-item-{{ $item->id }}">
                                <td class="p-4 font-semibold text-slate-200">
                                    <span class="inline-flex items-center gap-2">
                                        @if($item->icon)
                                            <i data-lucide="{{ $item->icon }}" class="h-4 w-4 text-indigo-400"></i>
                                        @endif
                                        {{ $item->label }}
                                    </span>
                                    <span class="block text-[9px] text-slate-500 mt-0.5">Pattern: {{ $item->match_pattern ?: '-' }}</span>
                                </td>
                                <td class="p-4 font-mono text-slate-400">
                                    {{ $item->url }}
                                    @if($item->opens_new_tab)
                                        <span class="ml-2 rounded border border-slate-700 px-1.5 py-0.5 text-[9px] uppercase text-slate-500">New tab</span>
                                    @endif
                                </td>
                                <td class="p-4 text-slate-400">{{ str_replace('_', ' ', $item->visibility) }}</td>
                                <td class="p-4 text-slate-400">{{ $item->sort_order }}</td>
                                <td class="p-4">
                                    <button wire:click="toggleNavigationItemActive({{ $item->id }})"
                                            class="inline-flex items-center px-2 py-0.5 rounded border text-[9px] font-bold uppercase transition-colors
                                            {{ $item->is_active
                                               ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20 hover:bg-emerald-500/20'
                                               : 'bg-red-500/10 text-red-400 border-red-500/20 hover:bg-red-500/20' }}">
                                        {{ $item->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </td>
                                <td class="p-4 text-right space-x-2">
                                    <button wire:click="openEditNavigationItemModal({{ $item->id }})" class="p-1.5 text-indigo-400 hover:text-white bg-indigo-950/40 border border-indigo-900/50 rounded-lg" title="Edit Navigation Item">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </button>
                                    <livewire:admin.recoverable-delete resource="navigation" :record-id="$item->id" :key="'delete-navigation-'.$item->id" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-500 italic">No public navigation items created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Game Translation Modal -->
    @if($tab === 'games')
        <div x-cloak x-show="gameModalOpen" x-on:keydown.escape.window="closeGameModal()" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" x-on:click="closeGameModal()"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-2xl max-h-[92vh] w-full overflow-y-auto shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider" x-text="$wire.selectedGameId ? 'Edit Game' : 'Add New Game'"></h3>
                    <button type="button" x-on:click="closeGameModal()" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form wire:submit.prevent="saveGameTranslation" class="p-6 space-y-4 text-xs">


                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Game Slug</label><input type="text" wire:model="gameSlug" disabled aria-label="Game Slug" placeholder="Generated from game name" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">@error('gameSlug')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror</div>
                        <label class="flex items-center gap-3 rounded-lg border border-slate-800 bg-slate-900 px-4 py-3"><input type="checkbox" wire:model="gameIsActive" class="rounded border-slate-700 bg-slate-950 text-indigo-500"><span><span class="block text-xs font-bold text-white">Active in catalog</span><span class="mt-0.5 block text-[10px] text-slate-500">Visible to players and tournament creation.</span></span></label>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="admin-game-category" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">{{ __('Category') }} *</label>
                            <select id="admin-game-category" wire:model.live="gameCategoryId" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                <option value="">{{ __('Select category') }}</option>
                                @foreach($gameCategories as $category)
                                    <option value="{{ $category->id }}">
                                        {{ $category->name }}{{ ! $category->is_active ? ' ('.__('Inactive').')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('gameCategoryId') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="admin-game-name" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Game Name *</label>
                            <input id="admin-game-name" type="text" wire:model.live.debounce.150ms="gameName" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('gameName') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    @if($selectedGameId === null && ((int) $gameCategoryId === (int) ($cardBoardCategoryId ?? 0) || $gameGameplayProvider === 'playstrategy'))
                        <div class="rounded-lg border border-cyan-500/35 bg-cyan-950/20 p-4 space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <label for="admin-ps-preset" class="text-[10px] font-bold uppercase tracking-wider text-cyan-300">{{ __('Quick-Fill from PlayStrategy Catalog (Optional)') }}</label>
                                <span class="text-[10px] text-slate-400">{{ __('Or leave blank to add any manual Card/Board game') }}</span>
                            </div>
                            <select id="admin-ps-preset" wire:model.live="playStrategyPreset" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white focus:border-cyan-400 focus:outline-none">
                                <option value="">{{ __('— Manual Card / Board Game (Enter details below) —') }}</option>
                                @foreach(\App\Modules\CMS\Models\Game::playStrategyCatalog() as $presetKey => $preset)
                                    @php($isUsed = in_array(strtolower($presetKey), $usedPlayStrategyVariants ?? [], true))
                                    <option value="{{ $presetKey }}" {{ $isUsed ? 'disabled' : '' }}>
                                        {{ $preset['name'] }} ({{ $presetKey }}) — {{ (int) ($preset['clock_limit'] / 60) }}m + {{ $preset['clock_increment'] }}s{{ $isUsed ? ' · '.__('Already Added') : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[10px] leading-relaxed text-slate-400">{{ __('Selecting a PlayStrategy game automatically fills the name, slug, description, clock settings, Web Browser platform, and PlayStrategy provider. Each PlayStrategy game can only be added once.') }}</p>
                        </div>
                    @endif

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Description</label>
                        <textarea wire:model="gameDescription" rows="4" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500"></textarea>
                        @error('gameDescription') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <x-forms.image-crop-upload model="gameCardImage" label="Game Logo" :width="440" :height="330" :max-mb="2" />
                            @if($gameCardImage)<img src="{{ $gameCardImage->temporaryUrl() }}" alt="New game logo preview" decoding="async" class="aspect-[4/3] w-full rounded-lg border border-slate-800 object-cover">@endif
                            <img x-show="!$wire.gameCardImage && $wire.gameCardImagePath && !$wire.removeGameCardImage" x-bind:src="$wire.gameCardImagePath" alt="Current game logo" loading="lazy" decoding="async" class="aspect-[4/3] w-full rounded-lg border border-slate-800 object-cover">
                            <label x-show="$wire.gameCardImagePath" class="flex items-center gap-2 text-[10px] font-bold text-red-300"><input type="checkbox" wire:model="removeGameCardImage" class="rounded border-slate-700 bg-slate-900 text-red-500"> Remove current game logo</label>
                        </div>
                        <div class="space-y-2">
                            <x-forms.image-crop-upload model="gameBannerImage" label="Game Cover Photo" :width="1000" :height="400" :max-mb="2" />
                            @if($gameBannerImage)<img src="{{ $gameBannerImage->temporaryUrl() }}" alt="New game cover photo preview" decoding="async" class="aspect-[5/2] w-full rounded-lg border border-slate-800 object-cover">@endif
                            <img x-show="!$wire.gameBannerImage && $wire.gameBannerPath && !$wire.removeGameBannerImage" x-bind:src="$wire.gameBannerPath" alt="Current game cover photo" loading="lazy" decoding="async" class="aspect-[5/2] w-full rounded-lg border border-slate-800 object-cover">
                            <label x-show="$wire.gameBannerPath" class="flex items-center gap-2 text-[10px] font-bold text-red-300"><input type="checkbox" wire:model="removeGameBannerImage" class="rounded border-slate-700 bg-slate-900 text-red-500"> Remove current game cover photo</label>
                        </div>
                    </div>

                    <fieldset class="rounded-lg border border-slate-800 bg-slate-900/60 p-4"><legend class="px-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Supported Platforms {{ $selectedGameId ? '' : '*' }}</legend><div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">@forelse($gamePlatforms as $platform)<label class="flex items-center gap-2 rounded border border-slate-800 bg-slate-950 px-3 py-2 text-xs text-slate-300"><input type="checkbox" wire:model="gamePlatformIds" value="{{ $platform->id }}" class="rounded border-slate-700 bg-slate-900 text-indigo-500"> {{ $platform->name }}</label>@empty<p class="col-span-full text-xs text-amber-300">Create platforms first from the Platforms tab.</p>@endforelse</div>@error('gamePlatformIds')<p class="mt-2 text-xs text-red-400">Select at least one supported platform when adding a game.</p>@enderror @error('gamePlatformIds.*')<p class="mt-2 text-xs text-red-400">{{ $message }}</p>@enderror</fieldset>

                    <fieldset class="rounded-lg border border-cyan-900/40 bg-cyan-950/10 p-4">
                        <legend class="px-1 text-[10px] font-bold uppercase tracking-wider text-cyan-300">{{ __('Gameplay Provider') }}</legend>
                        <p class="mb-3 text-[10px] leading-relaxed text-slate-400">{{ __('Gameplay provider is configured independently from game category. Choose PlayStrategy for automated external board matches and verified API results.') }}</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="admin-game-provider" class="block text-[10px] font-bold uppercase text-slate-400">{{ __('Provider') }}</label>
                                <select id="admin-game-provider" wire:model.live="gameGameplayProvider" class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-white">
                                    <option value="manual">{{ __('Manual (Player-Reported)') }}</option>
                                    <option value="playstrategy">{{ __('PlayStrategy (Automated Boards)') }}</option>
                                </select>
                                @error('gameGameplayProvider')<span class="text-xs text-red-400">{{ $message }}</span>@enderror
                            </div>
                            @if($gameGameplayProvider === 'playstrategy')
                                <div>
                                    <label for="admin-ps-variant" class="block text-[10px] font-bold uppercase text-slate-400">{{ __('PlayStrategy Variant') }}</label>
                                    <select id="admin-ps-variant" wire:model="gamePlayStrategyVariant" class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-white">
                                        @foreach(\App\Modules\CMS\Models\Game::PLAYSTRATEGY_VARIANTS as $variantKey => $variantLabel)
                                            @php($variantUsed = in_array(strtolower($variantKey), $usedPlayStrategyVariants ?? [], true))
                                            <option value="{{ $variantKey }}" {{ $variantUsed ? 'disabled' : '' }}>{{ $variantLabel }} ({{ $variantKey }}){{ $variantUsed ? ' · '.__('Already Added') : '' }}</option>
                                        @endforeach
                                    </select>
                                    @error('gamePlayStrategyVariant')<span class="text-xs text-red-400">{{ $message }}</span>@enderror
                                </div>
                                <div>
                                    <label for="admin-ps-clock" class="block text-[10px] font-bold uppercase text-slate-400">{{ __('Clock Limit (seconds)') }}</label>
                                    <input id="admin-ps-clock" wire:model="gamePlayStrategyClockLimit" type="number" min="60" max="7200" class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-white">
                                    @error('gamePlayStrategyClockLimit')<span class="text-xs text-red-400">{{ $message }}</span>@enderror
                                </div>
                                <div>
                                    <label for="admin-ps-inc" class="block text-[10px] font-bold uppercase text-slate-400">{{ __('Clock Increment (seconds)') }}</label>
                                    <input id="admin-ps-inc" wire:model="gamePlayStrategyClockIncrement" type="number" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-white">
                                    @error('gamePlayStrategyClockIncrement')<span class="text-xs text-red-400">{{ $message }}</span>@enderror
                                </div>
                            @endif
                        </div>
                    </fieldset>

                    <fieldset class="rounded-lg border border-cyan-900/40 bg-cyan-950/10 p-4">
                        <legend class="px-1 text-[10px] font-bold uppercase tracking-wider text-cyan-300">Player Connection Details</legend>
                        <p class="mb-3 text-[10px] leading-relaxed text-slate-500">Shown when a player registers and inside the Match Room. IDs remain flexible text because every game uses a different format.</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div><label class="block text-[10px] font-bold uppercase text-slate-400">Game ID Label</label><input wire:model="gameIdLabel" type="text" placeholder="Game ID / In-Game Name" class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-white">@error('gameIdLabel')<span class="text-xs text-red-400">{{ $message }}</span>@enderror</div>
                            <div><label class="block text-[10px] font-bold uppercase text-slate-400">Example</label><input wire:model="gameIdExample" type="text" placeholder="e.g. PlayerName#1234" class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-white"></div>
                            <div class="sm:col-span-2"><label class="block text-[10px] font-bold uppercase text-slate-400">How Players Connect</label><select disabled aria-label="How Players Connect" class="mt-1 w-full cursor-not-allowed rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-white opacity-60"><option value="server_room" selected>Join a Server Room</option></select></div>
                            <div class="sm:col-span-2"><label class="block text-[10px] font-bold uppercase text-slate-400">Player Instructions</label><textarea wire:model="gameIdInstructions" rows="2" placeholder="Where to find the ID and how opponents should connect." class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-white"></textarea></div>
                        </div>
                    </fieldset>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" x-on:click="closeGameModal()"
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="gameCardImage,gameBannerImage"
                                class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg disabled:cursor-wait disabled:opacity-50">
                            <span wire:loading.remove wire:target="gameCardImage,gameBannerImage">Save Game</span>
                            <span wire:loading wire:target="gameCardImage,gameBannerImage">Uploading Image…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Manage Game Categories Modal -->
        @if($showCategoryManagerModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="manage-categories-title" x-on:keydown.escape.window="$wire.closeCategoryManager()">
                <div class="fixed inset-0 bg-black/65 backdrop-blur-sm" wire:click="closeCategoryManager"></div>
                <div class="relative z-10 max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl border border-slate-800 bg-[#0f172a] shadow-2xl">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 bg-[#0b0f19] px-6 py-4">
                        <div>
                            <h3 id="manage-categories-title" class="text-sm font-bold uppercase tracking-wider text-white">{{ __('Game Categories') }}</h3>
                            <p class="mt-0.5 text-xs text-slate-400">{{ __('Create, rename, reorder, activate, or deactivate categories. Categories referenced by any game (including archived games) cannot be deleted.') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($this->canManageCategories())
                                <button type="button" wire:click="openCreateCategoryModal" class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-indigo-500">
                                    <i data-lucide="plus" class="h-3.5 w-3.5"></i><span>{{ __('Add Category') }}</span>
                                </button>
                            @endif
                            <button type="button" wire:click="closeCategoryManager" aria-label="{{ __('Close categories manager') }}" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white">
                                <i data-lucide="x" class="h-5 w-5"></i>
                            </button>
                        </div>
                    </div>

                    <div class="p-6 space-y-4">
                        @if($categoryError !== '')
                            <div role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-xs text-red-300">
                                {{ $categoryError }}
                            </div>
                        @endif

                        <div class="overflow-x-auto rounded-xl border border-slate-800">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-slate-800 bg-slate-950/60 text-[10px] font-bold uppercase text-slate-400">
                                        <th class="p-3">{{ __('Order') }}</th>
                                        <th class="p-3">{{ __('Category') }}</th>
                                        <th class="p-3">{{ __('Games') }}</th>
                                        <th class="p-3">{{ __('Status') }}</th>
                                        <th class="p-3 text-right">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/70">
                                    @forelse($gameCategories as $category)
                                        @php($hasAnyGames = (int) $category->total_games_count > 0)
                                        <tr wire:key="manage-cat-{{ $category->id }}" class="hover:bg-slate-900/50">
                                            <td class="p-3 font-mono text-slate-300">
                                                <div class="inline-flex items-center gap-1">
                                                    <span class="w-6 tabular-nums">{{ $category->display_order }}</span>
                                                    @if($this->canManageCategories())
                                                        <button type="button" wire:click="moveCategory({{ $category->id }}, 'up')" @disabled($loop->first) aria-label="{{ __('Move :name up', ['name' => $category->name]) }}" class="rounded border border-slate-700 bg-slate-900 p-1 text-slate-300 hover:border-cyan-400/50 hover:text-white disabled:opacity-30"><i data-lucide="chevron-up" class="h-3.5 w-3.5"></i></button>
                                                        <button type="button" wire:click="moveCategory({{ $category->id }}, 'down')" @disabled($loop->last) aria-label="{{ __('Move :name down', ['name' => $category->name]) }}" class="rounded border border-slate-700 bg-slate-900 p-1 text-slate-300 hover:border-cyan-400/50 hover:text-white disabled:opacity-30"><i data-lucide="chevron-down" class="h-3.5 w-3.5"></i></button>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="p-3">
                                                <span class="block font-bold text-white">{{ $category->name }}</span>
                                                <span class="font-mono text-[10px] text-slate-400">{{ $category->slug }}</span>
                                            </td>
                                            <td class="p-3 font-mono tabular-nums text-slate-300">
                                                <span>{{ (int) $category->active_record_count }} {{ __('active') }}</span>
                                                @if((int) $category->deleted_record_count > 0)
                                                    <span class="block text-[10px] text-amber-300">{{ (int) $category->deleted_record_count }} {{ __('archived') }}</span>
                                                @endif
                                            </td>
                                            <td class="p-3">
                                                <span class="inline-flex items-center gap-1.5 rounded border px-2 py-0.5 text-[10px] font-bold uppercase {{ $category->is_active ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-amber-500/35 bg-amber-500/10 text-amber-300' }}">
                                                    <span class="h-1.5 w-1.5 rounded-full {{ $category->is_active ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                                                    {{ $category->is_active ? __('Active') : __('Inactive') }}
                                                </span>
                                            </td>
                                            <td class="p-3 text-right">
                                                @if($this->canManageCategories())
                                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                                        <button type="button" wire:click="openEditCategoryModal({{ $category->id }})" class="rounded-lg border border-slate-700 bg-slate-900 px-2.5 py-1.5 text-[11px] font-semibold text-slate-200 hover:border-indigo-400 hover:text-white">{{ __('Edit') }}</button>
                                                        <button type="button" wire:click="requestCategoryStatusChange({{ $category->id }})" class="rounded-lg border px-2.5 py-1.5 text-[11px] font-semibold {{ $category->is_active ? 'border-amber-500/40 bg-amber-500/10 text-amber-200 hover:bg-amber-500/20' : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200 hover:bg-emerald-500/20' }}">
                                                            {{ $category->is_active ? __('Deactivate') : __('Activate') }}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            wire:click="deleteCategory({{ $category->id }})"
                                                            @disabled($hasAnyGames)
                                                            title="{{ $hasAnyGames ? __('Cannot delete while active or archived games belong to this category.') : __('Delete category') }}"
                                                            class="rounded-lg border border-red-500/30 bg-red-500/10 px-2.5 py-1.5 text-[11px] font-semibold text-red-300 hover:bg-red-500/20 disabled:cursor-not-allowed disabled:opacity-40"
                                                        >
                                                            {{ __('Delete') }}
                                                        </button>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="p-6 text-center text-slate-400">{{ __('No game categories created yet.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Create / Edit Game Category Modal -->
        @if($showCategoryModal)
            <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="category-form-title" x-on:keydown.escape.window="$wire.set('showCategoryModal', false)">
                <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" wire:click="$set('showCategoryModal', false)"></div>
                <div class="relative z-10 w-full max-w-md overflow-hidden rounded-xl border border-slate-800 bg-[#0f172a] shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-800 bg-[#0b0f19] px-6 py-4">
                        <h3 id="category-form-title" class="text-sm font-bold uppercase tracking-wider text-slate-200">
                            {{ $selectedCategoryId ? __('Edit Game Category') : __('Create Game Category') }}
                        </h3>
                        <button type="button" wire:click="$set('showCategoryModal', false)" aria-label="{{ __('Close category form') }}" class="text-slate-400 hover:text-white">
                            <i data-lucide="x" class="h-5 w-5"></i>
                        </button>
                    </div>
                    <form wire:submit.prevent="saveCategory" class="p-6 space-y-4 text-xs">
                        <div>
                            <label for="cat-name" class="mb-1 block text-[10px] font-bold uppercase text-slate-400">{{ __('Category Name') }} *</label>
                            <input id="cat-name" type="text" wire:model.live.debounce.150ms="categoryName" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                            @error('categoryName')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label for="cat-slug" class="mb-1 block text-[10px] font-bold uppercase text-slate-400">{{ __('Unique Slug') }} *</label>
                            <input id="cat-slug" type="text" wire:model="categorySlug" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 font-mono text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                            @error('categorySlug')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="cat-order" class="mb-1 block text-[10px] font-bold uppercase text-slate-400">{{ __('Display Order') }}</label>
                                <input id="cat-order" type="number" min="0" wire:model="categoryDisplayOrder" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                                @error('categoryDisplayOrder')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                            </div>
                            <label class="flex items-center gap-2.5 self-end rounded-lg border border-slate-800 bg-slate-900 px-3 py-2.5 text-xs font-semibold text-slate-200">
                                <input type="checkbox" wire:model="categoryIsActive" class="rounded border-slate-700 bg-slate-950 text-indigo-500">
                                <span>{{ __('Active') }}</span>
                            </label>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-slate-800 pt-4">
                            <button type="button" wire:click="$set('showCategoryModal', false)" class="rounded-lg bg-slate-800 px-4 py-2 text-xs font-bold uppercase text-slate-200 hover:bg-slate-700">{{ __('Cancel') }}</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveCategory" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold uppercase text-white hover:bg-indigo-500 disabled:opacity-50">{{ __('Save Category') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Deactivate Category Confirmation Modal -->
        @if($confirmDeactivateCategoryId !== null)
            @teleport('body')
                <div class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="deactivate-category-title" x-on:keydown.escape.window="$wire.cancelDeactivateCategory()">
                    <button type="button" wire:click="cancelDeactivateCategory" class="absolute inset-0 bg-black/75 backdrop-blur-sm" aria-label="{{ __('Close deactivation dialog') }}"></button>
                    <div class="relative z-10 w-full max-w-lg rounded-2xl border border-amber-500/40 bg-[#0f172a] p-6 text-slate-200 shadow-2xl">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-amber-500/35 bg-amber-500/15 text-amber-300">
                                <i data-lucide="alert-triangle" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <h3 id="deactivate-category-title" class="text-base font-bold text-white">{{ __('Deactivate ":name"?', ['name' => $confirmDeactivateCategoryName]) }}</h3>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ __('Affects :games game(s) and :tournaments active/open tournament(s).', ['games' => $confirmDeactivateCategoryGamesCount, 'tournaments' => $confirmDeactivateCategoryOpenTournamentsCount]) }}
                                </p>
                            </div>
                        </div>

                        <ul class="mt-4 space-y-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 text-xs leading-relaxed text-slate-300">
                            <li class="flex gap-2"><span class="text-amber-300 font-bold">•</span><span>{{ __('Hides this category, its games, and its tournaments from player discovery, game selectors, All Games, and featured results.') }}</span></li>
                            <li class="flex gap-2"><span class="text-amber-300 font-bold">•</span><span>{{ __('Blocks new registrations for tournaments in this category across web and API.') }}</span></li>
                            <li class="flex gap-2"><span class="text-emerald-300 font-bold">•</span><span>{{ __('Preserves accepted registrations, ongoing match access, cancellation rights, bracket advancement, payouts, and admin access.') }}</span></li>
                        </ul>

                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" wire:click="cancelDeactivateCategory" class="rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-xs font-bold uppercase text-slate-200 hover:bg-slate-800">{{ __('Cancel') }}</button>
                            <button type="button" wire:click="confirmDeactivateCategory" wire:loading.attr="disabled" wire:target="confirmDeactivateCategory" class="rounded-lg bg-amber-500 px-4 py-2 text-xs font-bold uppercase text-slate-950 hover:bg-amber-400 disabled:opacity-50">{{ __('Deactivate Category') }}</button>
                        </div>
                    </div>
                </div>
            @endteleport
        @endif
    @endif

    <!-- Create/Edit Platform Modal -->
    @if($showPlatformModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showPlatformModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">
                        {{ $selectedPlatformId ? 'Edit Platform' : 'Create Platform' }}
                    </h3>
                    <button wire:click="$set('showPlatformModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form wire:submit.prevent="savePlatform" class="p-6 space-y-4 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Platform Name</label>
                        <input type="text" wire:model="platformName" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        @error('platformName') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">URL Slug</label>
                        <input type="text" wire:model="platformSlug" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        @error('platformSlug') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showPlatformModal', false)" 
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Save Platform
                        </button>
                    </div>
                </form>
            </div>
        </div>

    @endif

    <!-- Create/Edit Landing Item Modal -->
    @if($showLandingItemModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showLandingItemModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-2xl w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">
                        {{ $selectedLandingItemId ? 'Edit Landing Item' : 'Create Landing Item' }}
                    </h3>
                    <button wire:click="$set('showLandingItemModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form wire:submit.prevent="saveLandingItem" class="p-6 space-y-4 text-xs">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Item Key</label>
                            <input type="text" wire:model="landingItemKey" placeholder="matches_played / review-1" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('landingItemKey') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Sort Order</label>
                            <input type="number" wire:model="landingItemSortOrder" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('landingItemSortOrder') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Title</label>
                            <input type="text" wire:model="landingItemTitle" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('landingItemTitle') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Subtitle</label>
                            <input type="text" wire:model="landingItemSubtitle" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('landingItemSubtitle') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Label</label>
                            <input type="text" wire:model="landingItemLabel" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('landingItemLabel') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">URL</label>
                            <input type="text" wire:model="landingItemUrl" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('landingItemUrl') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Lucide Icon</label>
                            <input type="text" wire:model="landingItemIcon" placeholder="trophy" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('landingItemIcon') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-400">
                                <input type="checkbox" wire:model="landingItemIsActive" class="rounded border-slate-700 bg-slate-900 text-indigo-500">
                                Active
                            </label>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Body</label>
                            <textarea wire:model="landingItemBody" rows="5" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500"></textarea>
                            @error('landingItemBody') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showLandingItemModal', false)"
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Save Item
                        </button>
                    </div>
                </form>
            </div>
        </div>

    @endif

    <!-- Create/Edit Navigation Item Modal -->
    @if($showNavigationItemModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showNavigationItemModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-2xl w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">
                        {{ $selectedNavigationItemId ? 'Edit Navigation Item' : 'Create Navigation Item' }}
                    </h3>
                    <button wire:click="$set('showNavigationItemModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form wire:submit.prevent="saveNavigationItem" class="p-6 space-y-4 text-xs">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Label</label>
                            <input type="text" wire:model="navigationLabel" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('navigationLabel') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">URL</label>
                            <input type="text" wire:model="navigationUrl" placeholder="/tournaments" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('navigationUrl') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Lucide Icon</label>
                            <input type="text" wire:model="navigationIcon" placeholder="trophy" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('navigationIcon') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Active Pattern</label>
                            <input type="text" wire:model="navigationMatchPattern" placeholder="tournaments*" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('navigationMatchPattern') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Visibility</label>
                            <select wire:model="navigationVisibility" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-350 focus:outline-none focus:border-indigo-500">
                                <option value="public">Everyone</option>
                                <option value="guest">Guests only</option>
                                <option value="auth">Signed-in users</option>
                                <option value="player">Players only</option>
                                <option value="staff">Staff only</option>
                                <option value="guest_or_player">Guests and players</option>
                            </select>
                            @error('navigationVisibility') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Sort Order</label>
                            <input type="number" wire:model="navigationSortOrder" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('navigationSortOrder') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-400">
                                <input type="checkbox" wire:model="navigationIsActive" class="rounded border-slate-700 bg-slate-900 text-indigo-500">
                                Active
                            </label>
                            <label class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-400">
                                <input type="checkbox" wire:model="navigationOpensNewTab" class="rounded border-slate-700 bg-slate-900 text-indigo-500">
                                New tab
                            </label>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showNavigationItemModal', false)"
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Save Navigation
                        </button>
                    </div>
                </form>
            </div>
        </div>

    @endif

    <!-- Delete Confirmation Modal -->
        <div x-cloak x-show="deleteModalOpen" x-on:keydown.escape.window="closeDeleteModal()" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" x-on:click="closeDeleteModal()"></div>
            <div class="bg-[#0f172a] border border-red-900/50 rounded-xl {{ $deleteTargetType === 'game' ? 'max-w-xl' : 'max-w-sm' }} max-h-[90vh] w-full overflow-y-auto shadow-2xl relative z-10 text-center">
                <div class="p-6">
                    <div class="w-16 h-16 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-4 border border-red-500/20">
                        <i data-lucide="alert-triangle" class="w-8 h-8 text-red-500"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-200 mb-2" x-text="$wire.deleteTargetType === 'game' ? 'Soft Delete Game' : 'Confirm Deletion'"></h3>
                    <div wire:loading wire:target="confirmDelete" class="mb-6 space-y-3">
                        <div class="mx-auto h-3 w-4/5 animate-pulse rounded bg-slate-800"></div>
                        <div class="grid grid-cols-2 gap-3"><div class="h-16 animate-pulse rounded-lg bg-slate-900"></div><div class="h-16 animate-pulse rounded-lg bg-slate-900"></div></div>
                        <p class="text-xs text-slate-500">Loading affected tournament and player records…</p>
                    </div>
                    <div wire:loading.remove wire:target="confirmDelete">
                    @if($deleteTargetType === 'game' && $gameDeleteImpact)
                        <p class="text-sm text-slate-400">Delete <strong class="text-white">{{ $gameDeleteImpact['name'] }}</strong>? It will disappear from player-facing catalogs, but all historical data stays intact. Future competition generation for this game stops.</p>
                        <div class="my-5 grid grid-cols-2 gap-3"><div class="rounded-lg border border-slate-800 bg-slate-950 p-3"><p class="text-xl font-black text-amber-300">{{ $gameDeleteImpact['tournament_count'] }}</p><p class="mt-1 text-[9px] font-bold uppercase tracking-wider text-slate-500">Affected tournaments</p></div><div class="rounded-lg border border-slate-800 bg-slate-950 p-3"><p class="text-xl font-black text-cyan-300">{{ $gameDeleteImpact['player_count'] }}</p><p class="mt-1 text-[9px] font-bold uppercase tracking-wider text-slate-500">Unique joined players</p></div></div>
                        @if($gameDeleteImpact['tournaments'] !== [])
                            <div class="mb-6 max-h-56 overflow-y-auto rounded-lg border border-slate-800 text-left"><div class="sticky top-0 bg-slate-900 px-3 py-2 text-[9px] font-bold uppercase tracking-wider text-slate-500">Preserved tournament records</div>@foreach($gameDeleteImpact['tournaments'] as $impactTournament)<div class="flex items-center justify-between gap-3 border-t border-slate-800/70 px-3 py-2.5"><div class="min-w-0"><p class="truncate text-xs font-bold text-slate-200">{{ $impactTournament['name'] }}</p><p class="mt-0.5 text-[9px] uppercase text-slate-600">{{ str_replace('_', ' ', $impactTournament['status']) }}</p></div><span class="shrink-0 text-[10px] text-cyan-300">{{ $impactTournament['registrations'] }} joined</span></div>@endforeach @if($gameDeleteImpact['remaining_tournament_count'] > 0)<div class="border-t border-slate-800 px-3 py-2 text-center text-[10px] text-slate-500">+{{ $gameDeleteImpact['remaining_tournament_count'] }} more preserved tournaments</div>@endif</div>
                        @endif
                        @if($gameDeleteImpact['players'] !== [])
                            <div class="mb-6 text-left"><p class="mb-2 text-[9px] font-bold uppercase tracking-wider text-slate-500">Players with preserved participation</p><div class="flex max-h-28 flex-wrap gap-1.5 overflow-y-auto">@foreach($gameDeleteImpact['players'] as $impactPlayer)<span class="rounded border border-cyan-500/15 bg-cyan-500/5 px-2 py-1 text-[10px] text-cyan-200" title="{{ '@'.$impactPlayer['username'] }}">{{ $impactPlayer['display_name'] }}</span>@endforeach @if($gameDeleteImpact['remaining_player_count'] > 0)<span class="px-2 py-1 text-[10px] text-slate-500">+{{ $gameDeleteImpact['remaining_player_count'] }} more</span>@endif</div></div>
                        @endif
                    @else
                        <p class="text-sm text-slate-400 mb-6">Are you sure you want to delete this {{ $deleteTargetType === 'navigation' ? 'navigation item' : $deleteTargetType }}? The record will be hidden; its data and connected history remain stored.</p>
                    @endif
                    </div>
                    
                    <div class="flex space-x-3 justify-center">
                        <button type="button" x-on:click="closeDeleteModal()"
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-sm px-6 py-2.5 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="button" wire:click="executeDelete" wire:loading.attr="disabled" wire:target="confirmDelete,executeDelete"
                                class="bg-red-600 hover:bg-red-500 text-white font-bold text-sm px-6 py-2.5 rounded-lg shadow-[0_4px_12px_rgba(220,38,38,0.2)] transition-colors">
                            {{ $deleteTargetType === 'game' ? 'Soft Delete Game' : 'Yes, Delete' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

    <!-- About Us Tab Content -->
    @if($tab === 'about')
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6 p-6">
            <h2 class="text-xl font-bold text-white mb-2">About Us Page Content</h2>
            <p class="text-sm text-slate-400 mb-6">Manage the content that appears on the public About Us page.</p>
            
            <form wire:submit.prevent="saveAboutSettings" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Title</label>
                    <input type="text" wire:model="aboutTitle" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-indigo-500 transition-colors">
                    @error('aboutTitle') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Subtitle</label>
                    <input type="text" wire:model="aboutSubtitle" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-indigo-500 transition-colors">
                    @error('aboutSubtitle') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Body Content (HTML allowed)</label>
                    <div class="bg-slate-900 border border-slate-800 rounded-lg overflow-hidden focus-within:border-indigo-500 transition-colors">
                        <textarea wire:model="aboutBody" rows="15" class="w-full bg-transparent px-4 py-3 text-sm text-slate-200 focus:outline-none resize-y" placeholder="<p>Write your about us content here...</p>"></textarea>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">{{ __('For plain text, leave a blank line between paragraphs. For HTML, wrap each paragraph in <p> tags.') }}</p>
                    @error('aboutBody') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="pt-4 flex justify-end relative">
                    <div wire:loading wire:target="saveAboutSettings" class="absolute inset-0 bg-[#0f172a]/60 backdrop-blur-[1px] flex items-center justify-end pr-4 rounded-lg">
                         <svg class="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    </div>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm uppercase tracking-wider px-6 py-3 rounded-lg flex items-center transition-colors">
                        <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                        Save Content
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- Cookie Management Tab Content -->
    @if($tab === 'cookies')
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6 p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">
                        <i data-lucide="cookie" class="w-6 h-6 text-amber-400"></i>
                        Cookie & Privacy Banner Management
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">Configure cookie consent popups, compliance disclaimers, and tracking categories for players and visitors.</p>
                </div>
                <label class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-300 cursor-pointer bg-slate-900 border border-slate-800 px-4 py-2 rounded-lg">
                    <input type="checkbox" wire:model="cookieConsentEnabled" class="rounded border-slate-700 bg-slate-950 text-indigo-500">
                    Consent Banner Active
                </label>
            </div>

            <form wire:submit.prevent="saveCookieSettings" class="space-y-6">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Consent Modal Heading</label>
                        <input type="text" wire:model="cookieTitle" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 transition-colors text-sm">
                        @error('cookieTitle') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Consent Notice Message</label>
                        <textarea wire:model="cookieMessage" rows="3" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 transition-colors text-sm resize-y"></textarea>
                        @error('cookieMessage') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Cookie Policy Link URL</label>
                        <input type="text" wire:model="cookiePolicyUrl" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 transition-colors text-sm">
                        @error('cookiePolicyUrl') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-3 pt-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Cookie Categories</label>
                        <div class="flex items-center gap-3 bg-slate-900/60 border border-slate-800 rounded-lg p-3">
                            <input type="checkbox" wire:model="cookieAnalyticsEnabled" id="cat_analytics" class="rounded border-slate-700 bg-slate-950 text-indigo-500">
                            <label for="cat_analytics" class="text-xs text-slate-300 font-semibold cursor-pointer">
                                Analytics & Performance Cookies (Telemetry, visitor metrics)
                            </label>
                        </div>
                        <div class="flex items-center gap-3 bg-slate-900/60 border border-slate-800 rounded-lg p-3">
                            <input type="checkbox" wire:model="cookieMarketingEnabled" id="cat_marketing" class="rounded border-slate-700 bg-slate-950 text-indigo-500">
                            <label for="cat_marketing" class="text-xs text-slate-300 font-semibold cursor-pointer">
                                Marketing & Promotional Cookies (Campaign attribution)
                            </label>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Essential Cookies Description</label>
                        <textarea wire:model="cookieEssentialNote" rows="2" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 transition-colors text-sm resize-y"></textarea>
                        @error('cookieEssentialNote') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm uppercase tracking-wider px-6 py-3 rounded-lg flex items-center transition-colors">
                        <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                        Save Cookie Settings
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- Footer Management Tab Content -->
    @if($tab === 'footer')
        <div class="space-y-6">
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm p-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-white flex items-center gap-2">
                            <i data-lucide="panel-bottom" class="w-6 h-6 text-indigo-400"></i>
                            Footer Content & Layout Management
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">Full control over footer brand description, compliance disclaimers, social links, and navigation.</p>
                    </div>
                </div>

                <form wire:submit.prevent="saveFooterSettings" class="space-y-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Brand Tagline / Slogan</label>
                            <input type="text" wire:model="footerBrandTagline" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 transition-colors text-sm">
                            @error('footerBrandTagline') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Copyright Line</label>
                            <input type="text" wire:model="footerCopyright" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 transition-colors text-sm">
                            @error('footerCopyright') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="footer-company-details" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">{{ __('Company Details') }}</label>
                            <textarea id="footer-company-details" wire:model="footerCompanyDetails" rows="5" maxlength="1000" aria-describedby="footer-company-details-help" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 transition-colors text-sm resize-y"></textarea>
                            <p id="footer-company-details-help" class="text-xs text-slate-400 mt-1">{{ __('Shown separately from copyright. Use a new line for each company detail.') }}</p>
                            @error('footerCompanyDetails') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Brand Statement / Mission Note</label>
                            <textarea wire:model="footerDescription" rows="2" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 transition-colors text-sm resize-y"></textarea>
                            @error('footerDescription') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Regulatory / Esports Disclaimer Notice</label>
                            <textarea wire:model="footerDisclaimer" rows="2" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 transition-colors text-sm resize-y"></textarea>
                            @error('footerDisclaimer') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-300 mb-3 flex items-center gap-2">
                                <i data-lucide="share-2" class="w-4 h-4 text-cyan-400"></i>
                                Social & Community Channels
                            </h3>
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-400 mb-1">Discord</label>
                                    <input type="url" wire:model="footerDiscord" placeholder="https://discord.gg/..." class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-white text-xs">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-400 mb-1">X (Twitter)</label>
                                    <input type="url" wire:model="footerTwitter" placeholder="https://x.com/..." class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-white text-xs">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-400 mb-1">Twitch</label>
                                    <input type="url" wire:model="footerTwitch" placeholder="https://twitch.tv/..." class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-white text-xs">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-400 mb-1">YouTube</label>
                                    <input type="url" wire:model="footerYoutube" placeholder="https://youtube.com/..." class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-white text-xs">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm uppercase tracking-wider px-6 py-3 rounded-lg flex items-center transition-colors">
                            <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                            Save Footer Configuration
                        </button>
                    </div>
                </form>
            </div>

            <!-- Footer Links Section -->
            @if(isset($footerSection))
                <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-4">
                        <div>
                            <h3 class="text-base font-bold text-white flex items-center gap-2">
                                <i data-lucide="link" class="w-4 h-4 text-emerald-400"></i>
                                Footer Navigation Links
                            </h3>
                            <p class="text-xs text-slate-400 mt-1">Manage links displayed in the public footer (Terms, Privacy, Cookies, Refunds, etc.).</p>
                        </div>
                        <button type="button" wire:click="openCreateLandingItemModal({{ $footerSection->id }})" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-indigo-500">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                            Add Footer Link
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                                    <th class="p-3">Label</th>
                                    <th class="p-3">URL / Route</th>
                                    <th class="p-3">Order</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                @forelse($footerSection->items as $item)
                                    <tr class="hover:bg-slate-900/50">
                                        <td class="p-3 font-semibold text-white">{{ $item->label ?: $item->title }}</td>
                                        <td class="p-3 font-mono text-slate-400 text-[11px]">{{ $item->url ?: '#' }}</td>
                                        <td class="p-3 text-slate-400">{{ $item->sort_order }}</td>
                                        <td class="p-3">
                                            <button type="button" wire:click="toggleLandingItemActive({{ $item->id }})" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $item->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                                {{ $item->is_active ? 'Active' : 'Hidden' }}
                                            </button>
                                        </td>
                                        <td class="p-3 text-right space-x-2">
                                            <button type="button" wire:click="openEditLandingItemModal({{ $item->id }})" class="text-indigo-400 hover:text-indigo-300 font-bold text-[11px]">Edit</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-4 text-center text-slate-500">No footer links configured yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif
    @if(in_array($tab, ['games', 'platforms', 'navigation', 'landing'], true) && !($tab === 'games' && $gameRecordTab === 'deleted'))
        <x-admin.deletion-actions :resource="$tab === 'landing' ? 'landing_items' : $tab" />
    @endif
    @if($permanentDeleteGameId !== null)
        @teleport('body')
            <div class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="permanent-delete-game-title" x-on:keydown.escape.window="$wire.cancelPermanentDeleteGame()">
                <button type="button" wire:click="cancelPermanentDeleteGame" class="absolute inset-0 bg-black/70" aria-label="Close permanent deletion dialog"></button>
                <div class="relative max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5 text-slate-200 shadow-xl">
                    <h2 id="permanent-delete-game-title" class="text-lg font-semibold">Permanently delete {{ $permanentDeleteGameName }}?</h2>
                    <p class="mt-3 text-sm leading-6 text-amber-300">This permanently removes the game, its catalog translations, and its platform assignments. It cannot be restored. Uploaded files and audit records are retained.</p>
                    @if($permanentDeleteReferences)
                        <div role="alert" class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm leading-6 text-red-300">
                            <p class="font-semibold">Blocked: connected data must be preserved.</p>
                            <p>These records still reference this game. Permanently removing it could delete connected records or break their history, so permanent deletion is disabled.</p>
                        </div>
                        <ul class="mt-2 space-y-1 text-sm text-slate-400">
                            @foreach($permanentDeleteReferences as $table => $count)
                                <li>{{ ucwords(str_replace('_', ' ', $table)) }}: {{ $count }} record(s)</li>
                            @endforeach
                        </ul>
                        <p class="mt-3 text-sm text-slate-400">Keep this game soft deleted or restore it. Connected records, including deleted history, will not be removed.</p>
                    @else
                        <label class="mt-4 block text-sm text-slate-400">Type DELETE to confirm
                            <input type="text" wire:model="permanentDeleteConfirmation" autocomplete="off" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-200">
                        </label>
                    @endif
                    @error('permanentDeleteConfirmation')<p role="alert" class="mt-3 text-sm text-red-400">{{ $message }}</p>@enderror
                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" wire:click="cancelPermanentDeleteGame" class="rounded-lg border border-slate-700 px-4 py-2 text-sm">Cancel</button>
                        <button type="button" wire:click="permanentlyDeleteGame" wire:loading.attr="disabled" @disabled(count($permanentDeleteReferences) > 0) class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Permanent Delete</button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>

<style>
    [x-cloak] { display: none !important; }
    .game-filter-field { width: 100%; border: 1px solid rgb(30 41 59); border-radius: .5rem; background: rgb(2 6 23); padding: .5rem .65rem; color: rgb(203 213 225); font-size: .75rem; outline: none; }
    .game-filter-field:focus { border-color: rgb(99 102 241); }
    .game-action-item { display: flex; width: 100%; align-items: center; gap: .65rem; padding: .55rem .75rem; color: rgb(203 213 225); font-size: .75rem; font-weight: 600; text-align: left; }
    .game-action-item:hover { background: rgb(30 41 59); color: white; }
</style>
