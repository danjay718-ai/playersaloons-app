<div>
    <!-- Navigation Tabs -->
    <div class="flex border-b border-slate-800 mb-6">
        <button wire:click="setTab('games')" 
                class="px-5 py-3 border-b-2 text-sm font-semibold tracking-wider uppercase transition-colors
                {{ $tab === 'games' 
                   ? 'border-indigo-500 text-indigo-400 font-bold' 
                   : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-800' }}">
            Games Catalog
        </button>
        <button wire:click="setTab('pages')" 
                class="px-5 py-3 border-b-2 text-sm font-semibold tracking-wider uppercase transition-colors
                {{ $tab === 'pages' 
                   ? 'border-indigo-500 text-indigo-400 font-bold' 
                   : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-800' }}">
            CMS Pages
        </button>
        <button wire:click="setTab('platforms')" 
                class="px-5 py-3 border-b-2 text-sm font-semibold tracking-wider uppercase transition-colors
                {{ $tab === 'platforms' 
                   ? 'border-indigo-500 text-indigo-400 font-bold' 
                   : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-800' }}">
            Platforms
        </button>
        <button wire:click="setTab('landing')"
                class="px-5 py-3 border-b-2 text-sm font-semibold tracking-wider uppercase transition-colors
                {{ $tab === 'landing'
                   ? 'border-indigo-500 text-indigo-400 font-bold'
                   : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-800' }}">
            Landing Page
        </button>
        <button wire:click="setTab('navigation')"
                class="px-5 py-3 border-b-2 text-sm font-semibold tracking-wider uppercase transition-colors
                {{ $tab === 'navigation'
                   ? 'border-indigo-500 text-indigo-400 font-bold'
                   : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-800' }}">
            Navigation
        </button>
    </div>

    <!-- Feedback Alerts -->
    @if(session()->has('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Games Tab Content -->
    @if($tab === 'games')
        <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="p-4">Game Slug</th>
                            <th class="p-4">Banner</th>
                            <th class="p-4">Name (EN)</th>
                            <th class="p-4">Description</th>
                            <th class="p-4">Catalog status</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        @forelse($games as $game)
                            <tr class="hover:bg-slate-900/40" wire:key="game-{{ $game->id }}">
                                <td class="p-4 font-semibold text-slate-200 font-mono">
                                    {{ $game->slug }}
                                    <span class="block text-[9px] text-slate-500 font-normal mt-0.5">{{ $game->uuid }}</span>
                                </td>
                                <td class="p-4">
                                    @if($game->banner_path)
                                        <div class="h-12 w-24 overflow-hidden rounded-lg border border-slate-800 bg-slate-950">
                                            <img src="{{ $game->banner_path }}" alt="{{ $game->slug }} banner" class="h-full w-full object-cover">
                                        </div>
                                    @else
                                        <span class="text-[10px] text-slate-600">No banner</span>
                                    @endif
                                </td>
                                <td class="p-4 text-slate-200 font-semibold">
                                    {{ $game->translations->where('locale', 'en')->first()?->name ?? 'N/A' }}
                                </td>
                                <td class="p-4 text-slate-400 max-w-[280px] truncate" title="{{ $game->translations->where('locale', 'en')->first()?->description }}">
                                    {{ $game->translations->where('locale', 'en')->first()?->description ?? 'No description' }}
                                </td>
                                <td class="p-4">
                                    <button wire:click="toggleGameActive({{ $game->id }})" 
                                            class="inline-flex items-center px-2 py-0.5 rounded border text-[9px] font-bold uppercase transition-colors
                                            {{ $game->is_active 
                                               ? 'bg-emerald-500/10 text-emerald-450 border-emerald-500/20 hover:bg-emerald-500/20' 
                                               : 'bg-red-500/10 text-red-400 border-red-500/20 hover:bg-red-500/20' }}">
                                        {{ $game->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </td>
                                <td class="p-4 text-right">
                                    <button wire:click="editGameTranslation({{ $game->id }})" class="p-1.5 text-indigo-400 hover:text-white bg-indigo-950/40 border border-indigo-900/50 rounded-lg" title="Edit Translations">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-500 italic">No games seeded in database.</td>
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

    <!-- CMS Pages Tab Content -->
    @if($tab === 'pages')
        <div class="flex justify-end mb-4">
            <button wire:click="openCreatePageModal" 
                    class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs uppercase tracking-wider px-3.5 py-2.5 rounded-lg flex items-center shadow-md transition-colors">
                <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>
                <span>Add CMS Page</span>
            </button>
        </div>

        <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                            <th class="p-4">Page Title (EN)</th>
                            <th class="p-4">URL Slug</th>
                            <th class="p-4">Publisher</th>
                            <th class="p-4">Publication status</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        @forelse($pages as $page)
                            <tr class="hover:bg-slate-900/40" wire:key="page-{{ $page->id }}">
                                <td class="p-4 font-semibold text-slate-200">
                                    {{ $page->translations->where('locale', 'en')->first()?->title ?? 'Untitled Page' }}
                                    <span class="block text-[9px] text-slate-550 mt-0.5">UUID: {{ $page->uuid }}</span>
                                </td>
                                <td class="p-4 text-slate-350 font-mono">
                                    /pages/{{ $page->slug }}
                                </td>
                                <td class="p-4 text-slate-400">
                                    {{ $page->creator->username ?? 'System' }}
                                </td>
                                <td class="p-4">
                                    @if($page->published_at)
                                        <span class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold uppercase bg-emerald-500/10 text-emerald-450 border-emerald-500/20">
                                            Published
                                        </span>
                                        <span class="block text-[9px] text-slate-550 mt-0.5">{{ $page->published_at->format('Y-m-d') }}</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold uppercase bg-slate-800 text-slate-450 border-slate-700">
                                            Draft
                                        </span>
                                    @endif
                                </td>
                                <td class="p-4 text-right space-x-2">
                                    @if(!$page->published_at)
                                        <button wire:click="publishPage({{ $page->id }})" class="p-1.5 text-emerald-400 hover:text-white bg-emerald-950/40 border border-emerald-900/50 rounded-lg" title="Publish Page">
                                            <i data-lucide="send" class="w-4 h-4"></i>
                                        </button>
                                    @endif
                                    <button wire:click="openEditPageModal({{ $page->id }})" class="p-1.5 text-indigo-400 hover:text-white bg-indigo-950/40 border border-indigo-900/50 rounded-lg" title="Edit Page">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </button>
                                    <button wire:click="confirmDelete('page', {{ $page->id }})" class="p-1.5 text-red-400 hover:text-white bg-red-950/40 border border-red-900/50 rounded-lg" title="Delete">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-500 italic">No CMS pages created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            {{ $pages->links() }}
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
    @if($showGameModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showGameModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Edit Game Translation</h3>
                    <button wire:click="$set('showGameModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form wire:submit.prevent="saveGameTranslation" class="p-6 space-y-4 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Language Locale</label>
                        <select wire:model="gameLocale" wire:change="editGameTranslation({{ $selectedGameId }})" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-350 focus:outline-none focus:border-indigo-500">
                            <option value="en">English (EN)</option>
                            <option value="es">Español (ES)</option>
                            <option value="tl">Tagalog (TL)</option>
                        </select>
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

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Landing Banner Path</label>
                        <input type="text" wire:model="gameBannerPath" placeholder="/storage/games/valorant.webp" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        <p class="mt-1 text-[10px] text-slate-500">Used by the landing page game carousel. Leave blank to show the generated pattern fallback.</p>
                        @error('gameBannerPath') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showGameModal', false)" 
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Save Translation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Create/Edit Page Modal -->
    @if($showPageModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showPageModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-2xl w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">
                        {{ $isPageEdit ? 'Edit CMS Page' : 'Create CMS Page' }}
                    </h3>
                    <button wire:click="$set('showPageModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form wire:submit.prevent="saveCmsPage" class="p-6 space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Language Locale</label>
                            <select wire:model="pageLocale" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-350 focus:outline-none focus:border-indigo-500">
                                <option value="en">English (EN)</option>
                                <option value="es">Español (ES)</option>
                                <option value="tl">Tagalog (TL)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">URL Slug</label>
                            <input type="text" wire:model="pageSlug" placeholder="e.g. terms-of-service"
                                   class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('pageSlug') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Page Title</label>
                        <input type="text" wire:model="pageTitle" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        @error('pageTitle') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Page HTML / Markdown Content</label>
                        <textarea wire:model="pageContent" rows="8" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 font-mono focus:outline-none focus:border-indigo-500"></textarea>
                        @error('pageContent') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showPageModal', false)" 
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Save Page
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
    @if($showDeleteModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showDeleteModal', false)"></div>
            <div class="bg-[#0f172a] border border-red-900/50 rounded-xl max-w-sm w-full overflow-hidden shadow-2xl relative z-10 text-center">
                <div class="p-6">
                    <div class="w-16 h-16 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-4 border border-red-500/20">
                        <i data-lucide="alert-triangle" class="w-8 h-8 text-red-500"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-200 mb-2">Confirm Deletion</h3>
                    <p class="text-sm text-slate-400 mb-6">
                        Are you sure you want to delete this {{ $deleteTargetType === 'navigation' ? 'navigation item' : $deleteTargetType }}? This action cannot be undone.
                    </p>
                    
                    <div class="flex space-x-3 justify-center">
                        <button type="button" wire:click="$set('showDeleteModal', false)" 
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-sm px-6 py-2.5 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="button" wire:click="executeDelete"
                                class="bg-red-600 hover:bg-red-500 text-white font-bold text-sm px-6 py-2.5 rounded-lg shadow-[0_4px_12px_rgba(220,38,38,0.2)] transition-colors">
                            Yes, Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
