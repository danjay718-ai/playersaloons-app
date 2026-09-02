<div x-data="gameManagementUi($wire)">
    <!-- Feedback Alerts -->
    @if(session()->has('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Games Tab Content -->
    @if($tab === 'games')
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-lg font-black text-white">Game Management</h2><p class="mt-1 text-xs text-slate-500">Create games, assign platforms, manage artwork, tournament templates, or safely archive catalog entries.</p></div>
            <button type="button" x-on:click="openCreateGame()" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white hover:bg-indigo-500">
                <i data-lucide="plus" class="h-4 w-4"></i><span>Add New Game</span>
            </button>
        </div>
        <div class="mb-5 rounded-xl border border-slate-800 bg-slate-900/60 p-4">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="flex rounded-lg border border-slate-800 bg-slate-950 p-1">
                    <button type="button" wire:click="setGameRecordTab('active')" class="rounded-md px-4 py-2 text-xs font-bold transition {{ $gameRecordTab === 'active' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">Active Games</button>
                    <button type="button" wire:click="setGameRecordTab('archived')" class="rounded-md px-4 py-2 text-xs font-bold transition {{ $gameRecordTab === 'archived' ? 'bg-amber-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">Archived Games</button>
                </div>
                <button type="button" wire:click="clearGameFilters" class="text-left text-xs font-bold text-slate-400 hover:text-white">Clear filters</button>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <label class="xl:col-span-2"><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Search</span><div class="relative"><i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-600"></i><input type="search" wire:model.live.debounce.350ms="gameSearch" placeholder="Name, slug, description, or platform" class="w-full rounded-lg border border-slate-800 bg-slate-950 py-2 pl-9 pr-3 text-xs text-slate-200 placeholder:text-slate-600 focus:border-indigo-500 focus:outline-none"></div></label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Catalog status</span><select wire:model.live="gameCatalogFilter" class="game-filter-field"><option value="">All statuses</option><option value="enabled">Enabled</option><option value="disabled">Disabled</option></select></label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Platform</span><select wire:model.live="gamePlatformFilter" class="game-filter-field"><option value="">All platforms</option>@foreach($gamePlatforms as $platform)<option value="{{ $platform->id }}">{{ $platform->name }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Tournament template</span><select wire:model.live="gameTemplateFilter" class="game-filter-field"><option value="">All templates</option><option value="configured">Configured</option><option value="missing">Not configured</option></select></label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Artwork</span><select wire:model.live="gameArtworkFilter" class="game-filter-field"><option value="">All artwork</option><option value="complete">Card + banner present</option><option value="missing_card">Missing card image</option><option value="missing_banner">Missing banner</option></select></label>
            </div>
        </div>
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="p-4">Game Slug</th>
                            <th class="p-4">Card / Banner</th>
                            <th class="p-4">Name (EN)</th>
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
                                <td class="p-4"><div class="flex max-w-[180px] flex-wrap gap-1">@forelse($game->platforms as $platform)<span class="rounded border border-slate-700 bg-slate-900 px-1.5 py-0.5 text-[9px] text-slate-400">{{ $platform->name }}</span>@empty<span class="text-[10px] text-slate-600">All / unassigned</span>@endforelse</div></td>
                                <td class="p-4 text-slate-400 max-w-[280px] truncate" title="{{ $game->localizedDescription() }}">
                                    {{ $game->localizedDescription() ?? __('No description') }}
                                </td>
                                <td class="p-4">
                                    @if($game->trashed())
                                        <span class="inline-flex rounded border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[9px] font-bold uppercase text-amber-300">Archived</span>
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
                                    <div class="relative inline-block text-left" x-data="{ open: false }" x-on:click.outside="open = false">
                                        <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open" aria-label="Archived game actions" class="rounded-lg border border-slate-700 bg-slate-900 p-2 text-slate-400 hover:border-slate-600 hover:text-white"><i data-lucide="ellipsis-vertical" class="h-4 w-4"></i></button>
                                        <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 z-30 mt-2 w-52 overflow-hidden rounded-xl border border-slate-700 bg-slate-900 py-1 text-left shadow-2xl shadow-black/40">
                                            <button type="button" x-on:click="open = false" wire:click="restoreGame({{ $game->id }})" class="game-action-item"><i data-lucide="archive-restore" class="h-4 w-4 text-emerald-400"></i>Restore game</button>
                                        </div>
                                    </div>
                                    @else
                                    <div class="relative inline-block text-left" x-data="{ open: false }" x-on:click.outside="open = false">
                                        <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open" aria-label="Game actions" class="rounded-lg border border-slate-700 bg-slate-900 p-2 text-slate-400 hover:border-slate-600 hover:text-white"><i data-lucide="ellipsis-vertical" class="h-4 w-4"></i></button>
                                        <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 z-30 mt-2 w-52 overflow-hidden rounded-xl border border-slate-700 bg-slate-900 py-1 text-left shadow-2xl shadow-black/40">
                                            <button type="button" x-on:click="open = false; openEditGame({{ Illuminate\Support\Js::from([
                                                'id' => $game->id,
                                                'locale' => 'en',
                                                'slug' => $game->slug,
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
                                            <button type="button" x-on:click="open = false; openGameArchive({{ $game->id }})" wire:loading.attr="disabled" wire:target="confirmDelete('game', {{ $game->id }})" class="game-action-item text-red-300 hover:bg-red-950/40 hover:text-red-200"><i data-lucide="archive" class="h-4 w-4"></i>Archive game</button>
                                        </div>
                                    </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-500 italic">No games found. Add the first game to the catalog.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            {{ $games->links() }}
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
                                    <button wire:click="confirmDelete('platform', {{ $platform->id }})" class="p-1.5 text-red-400 hover:text-white bg-red-950/40 border border-red-900/50 rounded-lg" title="Delete Platform">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
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
            {{ $platforms->links() }}
        </div>
    @endif

    <!-- Landing Page Tab Content -->
    @if($tab === 'landing')
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
                                                <button type="button" wire:click="deleteLandingItem({{ $item->id }})" class="rounded-lg border border-red-900/50 bg-red-950/40 p-1.5 text-red-400 hover:text-white" title="Delete">
                                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                                </button>
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
                                    <button wire:click="confirmDelete('navigation', {{ $item->id }})" class="p-1.5 text-red-400 hover:text-white bg-red-950/40 border border-red-900/50 rounded-lg" title="Delete Navigation Item">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
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
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Language Locale</label>
                        <select wire:model="gameLocale" x-on:change="switchGameLocale($event.target.value)" x-bind:disabled="!$wire.selectedGameId" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-350 focus:outline-none focus:border-indigo-500 disabled:opacity-60">
                            <option value="en">English (EN)</option>
                            <option value="es">Español (ES)</option>
                            <option value="tl">Tagalog (TL)</option>
                        </select>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Game Slug</label><input type="text" wire:model="gameSlug" placeholder="e.g. valorant" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">@error('gameSlug')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror</div>
                        <label class="flex items-center gap-3 rounded-lg border border-slate-800 bg-slate-900 px-4 py-3"><input type="checkbox" wire:model="gameIsActive" class="rounded border-slate-700 bg-slate-950 text-indigo-500"><span><span class="block text-xs font-bold text-white">Active in catalog</span><span class="mt-0.5 block text-[10px] text-slate-500">Visible to players and tournament creation.</span></span></label>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Game Name</label>
                        <input type="text" wire:model="gameName" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        @error('gameName') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Description</label>
                        <textarea wire:model="gameDescription" rows="4" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500"></textarea>
                        @error('gameDescription') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <x-forms.image-crop-upload model="gameCardImage" label="Game Card Image" :width="440" :height="330" :max-mb="2" />
                            @if($gameCardImage)<img src="{{ $gameCardImage->temporaryUrl() }}" alt="New card preview" decoding="async" class="aspect-[4/3] w-full rounded-lg border border-slate-800 object-cover">@endif
                            <img x-show="!$wire.gameCardImage && $wire.gameCardImagePath && !$wire.removeGameCardImage" x-bind:src="$wire.gameCardImagePath" alt="Current card" loading="lazy" decoding="async" class="aspect-[4/3] w-full rounded-lg border border-slate-800 object-cover">
                            <label x-show="$wire.gameCardImagePath" class="flex items-center gap-2 text-[10px] font-bold text-red-300"><input type="checkbox" wire:model="removeGameCardImage" class="rounded border-slate-700 bg-slate-900 text-red-500"> Remove current card image</label>
                        </div>
                        <div class="space-y-2">
                            <x-forms.image-crop-upload model="gameBannerImage" label="Hero Cover" :width="1000" :height="400" :max-mb="2" />
                            @if($gameBannerImage)<img src="{{ $gameBannerImage->temporaryUrl() }}" alt="New hero preview" decoding="async" class="aspect-[5/2] w-full rounded-lg border border-slate-800 object-cover">@endif
                            <img x-show="!$wire.gameBannerImage && $wire.gameBannerPath && !$wire.removeGameBannerImage" x-bind:src="$wire.gameBannerPath" alt="Current hero" loading="lazy" decoding="async" class="aspect-[5/2] w-full rounded-lg border border-slate-800 object-cover">
                            <label x-show="$wire.gameBannerPath" class="flex items-center gap-2 text-[10px] font-bold text-red-300"><input type="checkbox" wire:model="removeGameBannerImage" class="rounded border-slate-700 bg-slate-900 text-red-500"> Remove current hero cover</label>
                        </div>
                    </div>

                    <fieldset class="rounded-lg border border-slate-800 bg-slate-900/60 p-4"><legend class="px-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Supported Platforms {{ $selectedGameId ? '' : '*' }}</legend><div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">@forelse($gamePlatforms as $platform)<label class="flex items-center gap-2 rounded border border-slate-800 bg-slate-950 px-3 py-2 text-xs text-slate-300"><input type="checkbox" wire:model="gamePlatformIds" value="{{ $platform->id }}" class="rounded border-slate-700 bg-slate-900 text-indigo-500"> {{ $platform->name }}</label>@empty<p class="col-span-full text-xs text-amber-300">Create platforms first from the Platforms tab.</p>@endforelse</div>@error('gamePlatformIds')<p class="mt-2 text-xs text-red-400">Select at least one supported platform when adding a game.</p>@enderror @error('gamePlatformIds.*')<p class="mt-2 text-xs text-red-400">{{ $message }}</p>@enderror</fieldset>

                    <fieldset class="rounded-lg border border-cyan-900/40 bg-cyan-950/10 p-4">
                        <legend class="px-1 text-[10px] font-bold uppercase tracking-wider text-cyan-300">Player Connection Details</legend>
                        <p class="mb-3 text-[10px] leading-relaxed text-slate-500">Shown when a player registers and inside the Match Room. IDs remain flexible text because every game uses a different format.</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div><label class="block text-[10px] font-bold uppercase text-slate-400">Game ID Label</label><input wire:model="gameIdLabel" type="text" placeholder="Game ID / In-Game Name" class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-white">@error('gameIdLabel')<span class="text-xs text-red-400">{{ $message }}</span>@enderror</div>
                            <div><label class="block text-[10px] font-bold uppercase text-slate-400">Example</label><input wire:model="gameIdExample" type="text" placeholder="e.g. PlayerName#1234" class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-white"></div>
                            <div class="sm:col-span-2"><label class="block text-[10px] font-bold uppercase text-slate-400">How Players Connect</label><select wire:model="gameConnectionMethod" class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-white"><option value="player_invite">Add or invite the opponent</option><option value="lobby_code">Use a lobby code</option><option value="server_room">Join a server or room</option><option value="admin_instructions">Follow organizer instructions</option></select></div>
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
                    <h3 class="text-lg font-bold text-slate-200 mb-2" x-text="$wire.deleteTargetType === 'game' ? 'Archive Game' : 'Confirm Deletion'"></h3>
                    <div wire:loading wire:target="confirmDelete" class="mb-6 space-y-3">
                        <div class="mx-auto h-3 w-4/5 animate-pulse rounded bg-slate-800"></div>
                        <div class="grid grid-cols-2 gap-3"><div class="h-16 animate-pulse rounded-lg bg-slate-900"></div><div class="h-16 animate-pulse rounded-lg bg-slate-900"></div></div>
                        <p class="text-xs text-slate-500">Loading affected tournament and player records…</p>
                    </div>
                    <div wire:loading.remove wire:target="confirmDelete">
                    @if($deleteTargetType === 'game' && $gameDeleteImpact)
                        <p class="text-sm text-slate-400">Archive <strong class="text-white">{{ $gameDeleteImpact['name'] }}</strong>? It will disappear from player-facing catalogs, but all historical data stays intact.</p>
                        <div class="my-5 grid grid-cols-2 gap-3"><div class="rounded-lg border border-slate-800 bg-slate-950 p-3"><p class="text-xl font-black text-amber-300">{{ $gameDeleteImpact['tournament_count'] }}</p><p class="mt-1 text-[9px] font-bold uppercase tracking-wider text-slate-500">Affected tournaments</p></div><div class="rounded-lg border border-slate-800 bg-slate-950 p-3"><p class="text-xl font-black text-cyan-300">{{ $gameDeleteImpact['player_count'] }}</p><p class="mt-1 text-[9px] font-bold uppercase tracking-wider text-slate-500">Unique joined players</p></div></div>
                        @if($gameDeleteImpact['tournaments'] !== [])
                            <div class="mb-6 max-h-56 overflow-y-auto rounded-lg border border-slate-800 text-left"><div class="sticky top-0 bg-slate-900 px-3 py-2 text-[9px] font-bold uppercase tracking-wider text-slate-500">Preserved tournament records</div>@foreach($gameDeleteImpact['tournaments'] as $impactTournament)<div class="flex items-center justify-between gap-3 border-t border-slate-800/70 px-3 py-2.5"><div class="min-w-0"><p class="truncate text-xs font-bold text-slate-200">{{ $impactTournament['name'] }}</p><p class="mt-0.5 text-[9px] uppercase text-slate-600">{{ str_replace('_', ' ', $impactTournament['status']) }}</p></div><span class="shrink-0 text-[10px] text-cyan-300">{{ $impactTournament['registrations'] }} joined</span></div>@endforeach @if($gameDeleteImpact['remaining_tournament_count'] > 0)<div class="border-t border-slate-800 px-3 py-2 text-center text-[10px] text-slate-500">+{{ $gameDeleteImpact['remaining_tournament_count'] }} more preserved tournaments</div>@endif</div>
                        @endif
                        @if($gameDeleteImpact['players'] !== [])
                            <div class="mb-6 text-left"><p class="mb-2 text-[9px] font-bold uppercase tracking-wider text-slate-500">Players with preserved participation</p><div class="flex max-h-28 flex-wrap gap-1.5 overflow-y-auto">@foreach($gameDeleteImpact['players'] as $impactPlayer)<span class="rounded border border-cyan-500/15 bg-cyan-500/5 px-2 py-1 text-[10px] text-cyan-200" title="{{ '@'.$impactPlayer['username'] }}">{{ $impactPlayer['display_name'] }}</span>@endforeach @if($gameDeleteImpact['remaining_player_count'] > 0)<span class="px-2 py-1 text-[10px] text-slate-500">+{{ $gameDeleteImpact['remaining_player_count'] }} more</span>@endif</div></div>
                        @endif
                    @else
                        <p class="text-sm text-slate-400 mb-6">Are you sure you want to delete this {{ $deleteTargetType === 'navigation' ? 'navigation item' : $deleteTargetType }}? This action cannot be undone.</p>
                    @endif
                    </div>
                    
                    <div class="flex space-x-3 justify-center">
                        <button type="button" x-on:click="closeDeleteModal()"
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-sm px-6 py-2.5 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="button" wire:click="executeDelete" wire:loading.attr="disabled" wire:target="confirmDelete,executeDelete"
                                class="bg-red-600 hover:bg-red-500 text-white font-bold text-sm px-6 py-2.5 rounded-lg shadow-[0_4px_12px_rgba(220,38,38,0.2)] transition-colors">
                            {{ $deleteTargetType === 'game' ? 'Archive Game' : 'Yes, Delete' }}
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
</div>

<style>
    [x-cloak] { display: none !important; }
    .game-filter-field { width: 100%; border: 1px solid rgb(30 41 59); border-radius: .5rem; background: rgb(2 6 23); padding: .5rem .65rem; color: rgb(203 213 225); font-size: .75rem; outline: none; }
    .game-filter-field:focus { border-color: rgb(99 102 241); }
    .game-action-item { display: flex; width: 100%; align-items: center; gap: .65rem; padding: .55rem .75rem; color: rgb(203 213 225); font-size: .75rem; font-weight: 600; text-align: left; }
    .game-action-item:hover { background: rgb(30 41 59); color: white; }
</style>
