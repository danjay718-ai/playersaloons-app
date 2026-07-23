<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameTranslation;
use App\Modules\CMS\Models\LandingSection;
use App\Modules\CMS\Models\LandingSectionItem;
use App\Modules\CMS\Models\Platform;
use App\Modules\CMS\Models\PublicNavigationItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\WithPagination;

class CmsAdmin extends AdminComponent
{
    use WithPagination;

    public string $tab = 'games'; // games | pages | platforms | landing | navigation | about

    public function mount(?string $section = null): void
    {
        $this->tab = $this->resolveTab($section);

        if ($this->tab === 'landing') {
            $firstSection = LandingSection::query()->orderBy('sort_order')->first();
            if ($firstSection) {
                $this->selectLandingSection((int) $firstSection->id);
            }
        }

        if ($this->tab === 'about') {
            $this->loadAboutSettings();
        }
    }

    // Game modals / forms
    public bool $showGameModal = false;

    public ?int $selectedGameId = null;

    public string $gameName = '';

    public string $gameDescription = '';

    public string $gameBannerPath = '';

    public string $gameLocale = 'en';

    // Platform modals / forms
    public bool $showPlatformModal = false;

    public ?int $selectedPlatformId = null;

    public string $platformName = '';

    public string $platformSlug = '';

    // Delete confirmation modal
    public bool $showDeleteModal = false;

    public ?int $deleteTargetId = null;

    public string $deleteTargetType = ''; // 'platform' or 'page'

    // Landing page forms
    public ?int $selectedLandingSectionId = null;

    public string $landingSectionTitle = '';

    public string $landingSectionSubtitle = '';

    public string $landingSectionBody = '';

    public string $landingSectionMediaPath = '';

    public string $landingSectionCtaLabel = '';

    public string $landingSectionCtaUrl = '';

    public int $landingSectionSortOrder = 0;

    public bool $landingSectionIsActive = true;

    public bool $showLandingItemModal = false;

    public ?int $selectedLandingItemId = null;

    public ?int $landingItemSectionId = null;

    public string $landingItemKey = '';

    public string $landingItemTitle = '';

    public string $landingItemSubtitle = '';

    public string $landingItemBody = '';

    public string $landingItemIcon = '';

    public string $landingItemLabel = '';

    public string $landingItemUrl = '';

    public int $landingItemSortOrder = 0;

    public bool $landingItemIsActive = true;

    // Public navigation forms
    public bool $showNavigationItemModal = false;

    public ?int $selectedNavigationItemId = null;

    public string $navigationLabel = '';

    public string $navigationUrl = '';

    public string $navigationIcon = '';

    public string $navigationMatchPattern = '';

    public string $navigationVisibility = 'public';

    public int $navigationSortOrder = 0;

    public bool $navigationIsActive = true;

    public bool $navigationOpensNewTab = false;

    // About Page forms
    public string $aboutTitle = '';
    public string $aboutSubtitle = '';
    public string $aboutBody = '';

    protected $paginationTheme = 'tailwind';

    public function setTab(string $tabName): void
    {
        $this->tab = $tabName;
        $this->resetPage();

        if ($tabName === 'landing' && $this->selectedLandingSectionId === null) {
            $firstSection = LandingSection::query()->orderBy('sort_order')->first();
            if ($firstSection) {
                $this->selectLandingSection((int) $firstSection->id);
            }
        }

        if ($tabName === 'about') {
            $this->loadAboutSettings();
        }
    }

    private function loadAboutSettings(): void
    {
        $settings = \App\Modules\Operations\Models\SystemSetting::query()->whereIn('key', ['about.title', 'about.subtitle', 'about.body'])->pluck('value', 'key');
        $this->aboutTitle = $settings['about.title'] ?? 'About PlayerSaloons';
        $this->aboutSubtitle = $settings['about.subtitle'] ?? 'Our mission is to revolutionize competitive gaming.';
        $this->aboutBody = $settings['about.body'] ?? '<p>Welcome to PlayerSaloons.</p>';
    }

    public function saveAboutSettings(): void
    {
        $this->validate([
            'aboutTitle' => 'required|string|max:255',
            'aboutSubtitle' => 'nullable|string|max:255',
            'aboutBody' => 'required|string',
        ]);

        foreach ([
            'about.title' => $this->aboutTitle,
            'about.subtitle' => $this->aboutSubtitle,
            'about.body' => $this->aboutBody,
        ] as $key => $value) {
            \App\Modules\Operations\Models\SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'updated_by' => \Illuminate\Support\Facades\Auth::id()]
            );
        }

        session()->flash('success', 'About Us page content saved successfully.');
    }

    private function resolveTab(?string $section): string
    {
        return match ($section) {
            'landing' => 'landing',
            'games' => 'games',
            'platforms' => 'platforms',
            'navigation' => 'navigation',
            'about' => 'about',
            default => 'games',
        };
    }

    public function confirmDelete(string $type, int $id): void
    {
        $this->deleteTargetType = $type;
        $this->deleteTargetId = $id;
        $this->showDeleteModal = true;
    }

    public function executeDelete(): void
    {
        if (! $this->deleteTargetId) {
            return;
        }

        if ($this->deleteTargetType === 'platform') {
            $this->deletePlatform($this->deleteTargetId);
        } elseif ($this->deleteTargetType === 'navigation') {
            $this->deleteNavigationItem($this->deleteTargetId);
        }

        $this->showDeleteModal = false;
        $this->deleteTargetId = null;
        $this->deleteTargetType = '';
    }

    // --- PLATFORM ACTIONS ---
    public function togglePlatformActive(int $platformId): void
    {
        $platform = Platform::findOrFail($platformId);
        $platform->is_active = ! $platform->is_active;
        $platform->save();

        session()->flash('success', 'Platform status updated successfully.');
    }

    public function openPlatformCreateModal(): void
    {
        $this->selectedPlatformId = null;
        $this->platformName = '';
        $this->platformSlug = '';
        $this->showPlatformModal = true;
    }

    public function openPlatformEditModal(int $platformId): void
    {
        $this->selectedPlatformId = $platformId;
        $platform = Platform::findOrFail($platformId);

        $this->platformName = $platform->name;
        $this->platformSlug = $platform->slug;

        $this->showPlatformModal = true;
    }

    public function savePlatform(): void
    {
        $this->validate([
            'platformName' => 'required|string|max:255',
            'platformSlug' => 'required|string|max:255|unique:platforms,slug,'.$this->selectedPlatformId,
        ]);

        if ($this->selectedPlatformId) {
            $platform = Platform::findOrFail($this->selectedPlatformId);
            $platform->name = $this->platformName;
            $platform->slug = Str::slug($this->platformSlug);
            $platform->save();
            session()->flash('success', 'Platform updated successfully.');
        } else {
            Platform::create([
                'name' => $this->platformName,
                'slug' => Str::slug($this->platformSlug),
                'is_active' => true,
            ]);
            session()->flash('success', 'Platform created successfully.');
        }

        $this->showPlatformModal = false;
        $this->resetPlatformForm();
    }

    public function resetPlatformForm(): void
    {
        $this->selectedPlatformId = null;
        $this->platformName = '';
        $this->platformSlug = '';
    }

    public function deletePlatform(int $platformId): void
    {
        $platform = Platform::findOrFail($platformId);
        // You could add checks here to see if the platform is linked to existing tournaments before deleting
        $platform->delete();

        session()->flash('success', 'Platform deleted successfully.');
    }

    // --- GAME ACTIONS ---
    public function toggleGameActive(int $gameId): void
    {
        $game = Game::findOrFail($gameId);
        $game->is_active = ! $game->is_active;
        $game->save();

        session()->flash('success', 'Game status updated successfully.');
    }

    public function editGameTranslation(int $gameId): void
    {
        $this->selectedGameId = $gameId;
        $game = Game::findOrFail($gameId);
        /** @var GameTranslation|null $translation */
        $translation = $game->translations()->where('locale', $this->gameLocale)->first();

        $this->gameName = $translation !== null ? $translation->name : '';
        $this->gameDescription = $translation !== null ? $translation->description : '';
        $this->gameBannerPath = (string) $game->banner_path;
        $this->showGameModal = true;
    }

    public function saveGameTranslation(): void
    {
        $this->validate([
            'gameName' => 'required|string|max:255',
            'gameDescription' => 'nullable|string',
            'gameBannerPath' => 'nullable|string|max:255',
        ]);

        if (! $this->selectedGameId) {
            return;
        }

        DB::transaction(function (): void {
            Game::findOrFail($this->selectedGameId)->update([
                'banner_path' => $this->gameBannerPath !== '' ? $this->gameBannerPath : null,
            ]);

            GameTranslation::updateOrCreate([
                'game_id' => $this->selectedGameId,
                'locale' => $this->gameLocale,
            ], [
                'name' => $this->gameName,
                'description' => $this->gameDescription,
            ]);
        });

        session()->flash('success', 'Game translation saved successfully.');
        $this->showGameModal = false;
    }

    public function selectLandingSection(int $sectionId): void
    {
        $section = LandingSection::findOrFail($sectionId);

        $this->selectedLandingSectionId = (int) $section->id;
        $this->landingSectionTitle = (string) $section->title;
        $this->landingSectionSubtitle = (string) $section->subtitle;
        $this->landingSectionBody = (string) $section->body;
        $this->landingSectionMediaPath = (string) $section->media_path;
        $this->landingSectionCtaLabel = (string) $section->cta_label;
        $this->landingSectionCtaUrl = (string) $section->cta_url;
        $this->landingSectionSortOrder = (int) $section->sort_order;
        $this->landingSectionIsActive = (bool) $section->is_active;
    }

    public function saveLandingSection(): void
    {
        if (! $this->selectedLandingSectionId) {
            return;
        }

        $this->validate([
            'landingSectionTitle' => 'nullable|string|max:255',
            'landingSectionSubtitle' => 'nullable|string|max:255',
            'landingSectionBody' => 'nullable|string',
            'landingSectionMediaPath' => 'nullable|string|max:255',
            'landingSectionCtaLabel' => 'nullable|string|max:255',
            'landingSectionCtaUrl' => 'nullable|string|max:255',
            'landingSectionSortOrder' => 'integer|min:0|max:65535',
            'landingSectionIsActive' => 'boolean',
        ]);

        LandingSection::findOrFail($this->selectedLandingSectionId)->update([
            'title' => $this->landingSectionTitle,
            'subtitle' => $this->landingSectionSubtitle,
            'body' => $this->landingSectionBody,
            'media_path' => $this->landingSectionMediaPath,
            'cta_label' => $this->landingSectionCtaLabel,
            'cta_url' => $this->landingSectionCtaUrl,
            'sort_order' => $this->landingSectionSortOrder,
            'is_active' => $this->landingSectionIsActive,
        ]);

        session()->flash('success', 'Landing section saved successfully.');
    }

    public function openCreateLandingItemModal(int $sectionId): void
    {
        $this->selectedLandingItemId = null;
        $this->landingItemSectionId = $sectionId;
        $this->landingItemKey = '';
        $this->landingItemTitle = '';
        $this->landingItemSubtitle = '';
        $this->landingItemBody = '';
        $this->landingItemIcon = '';
        $this->landingItemLabel = '';
        $this->landingItemUrl = '';
        $this->landingItemSortOrder = 0;
        $this->landingItemIsActive = true;
        $this->showLandingItemModal = true;
    }

    public function openEditLandingItemModal(int $itemId): void
    {
        $item = LandingSectionItem::findOrFail($itemId);

        $this->selectedLandingItemId = (int) $item->id;
        $this->landingItemSectionId = (int) $item->landing_section_id;
        $this->landingItemKey = (string) $item->item_key;
        $this->landingItemTitle = (string) $item->title;
        $this->landingItemSubtitle = (string) $item->subtitle;
        $this->landingItemBody = (string) $item->body;
        $this->landingItemIcon = (string) $item->icon;
        $this->landingItemLabel = (string) $item->label;
        $this->landingItemUrl = (string) $item->url;
        $this->landingItemSortOrder = (int) $item->sort_order;
        $this->landingItemIsActive = (bool) $item->is_active;
        $this->showLandingItemModal = true;
    }

    public function saveLandingItem(): void
    {
        $this->validate([
            'landingItemSectionId' => 'required|integer|exists:landing_sections,id',
            'landingItemKey' => 'nullable|string|max:100',
            'landingItemTitle' => 'nullable|string|max:255',
            'landingItemSubtitle' => 'nullable|string|max:255',
            'landingItemBody' => 'nullable|string',
            'landingItemIcon' => 'nullable|string|max:100',
            'landingItemLabel' => 'nullable|string|max:255',
            'landingItemUrl' => 'nullable|string|max:255',
            'landingItemSortOrder' => 'integer|min:0|max:65535',
            'landingItemIsActive' => 'boolean',
        ]);

        $payload = [
            'landing_section_id' => $this->landingItemSectionId,
            'item_key' => $this->landingItemKey !== '' ? Str::slug($this->landingItemKey) : null,
            'title' => $this->landingItemTitle,
            'subtitle' => $this->landingItemSubtitle,
            'body' => $this->landingItemBody,
            'icon' => $this->landingItemIcon,
            'label' => $this->landingItemLabel,
            'url' => $this->landingItemUrl,
            'sort_order' => $this->landingItemSortOrder,
            'is_active' => $this->landingItemIsActive,
        ];

        if ($this->selectedLandingItemId) {
            LandingSectionItem::findOrFail($this->selectedLandingItemId)->update($payload);
        } else {
            LandingSectionItem::create(array_merge($payload, [
                'uuid' => Str::uuid()->toString(),
            ]));
        }

        $this->showLandingItemModal = false;
        session()->flash('success', 'Landing item saved successfully.');
    }

    public function toggleLandingItemActive(int $itemId): void
    {
        $item = LandingSectionItem::findOrFail($itemId);
        $item->update(['is_active' => ! $item->is_active]);

        session()->flash('success', 'Landing item status updated.');
    }

    public function deleteLandingItem(int $itemId): void
    {
        LandingSectionItem::findOrFail($itemId)->delete();

        session()->flash('success', 'Landing item deleted.');
    }

    public function openCreateNavigationItemModal(): void
    {
        $this->selectedNavigationItemId = null;
        $this->navigationLabel = '';
        $this->navigationUrl = '';
        $this->navigationIcon = '';
        $this->navigationMatchPattern = '';
        $this->navigationVisibility = 'public';
        $this->navigationSortOrder = 0;
        $this->navigationIsActive = true;
        $this->navigationOpensNewTab = false;
        $this->showNavigationItemModal = true;
    }

    public function openEditNavigationItemModal(int $itemId): void
    {
        $item = PublicNavigationItem::findOrFail($itemId);

        $this->selectedNavigationItemId = (int) $item->id;
        $this->navigationLabel = $item->label;
        $this->navigationUrl = $item->url;
        $this->navigationIcon = (string) $item->icon;
        $this->navigationMatchPattern = (string) $item->match_pattern;
        $this->navigationVisibility = $item->visibility;
        $this->navigationSortOrder = (int) $item->sort_order;
        $this->navigationIsActive = (bool) $item->is_active;
        $this->navigationOpensNewTab = (bool) $item->opens_new_tab;
        $this->showNavigationItemModal = true;
    }

    public function saveNavigationItem(): void
    {
        $this->validate([
            'navigationLabel' => 'required|string|max:100',
            'navigationUrl' => 'required|string|max:255',
            'navigationIcon' => 'nullable|string|max:100',
            'navigationMatchPattern' => 'nullable|string|max:100',
            'navigationVisibility' => 'required|string|in:public,guest,auth,player,staff,guest_or_player',
            'navigationSortOrder' => 'integer|min:0|max:65535',
            'navigationIsActive' => 'boolean',
            'navigationOpensNewTab' => 'boolean',
        ]);

        $payload = [
            'label' => $this->navigationLabel,
            'url' => $this->navigationUrl,
            'icon' => $this->navigationIcon !== '' ? $this->navigationIcon : null,
            'match_pattern' => $this->navigationMatchPattern !== '' ? $this->navigationMatchPattern : null,
            'visibility' => $this->navigationVisibility,
            'sort_order' => $this->navigationSortOrder,
            'is_active' => $this->navigationIsActive,
            'opens_new_tab' => $this->navigationOpensNewTab,
        ];

        if ($this->selectedNavigationItemId) {
            PublicNavigationItem::findOrFail($this->selectedNavigationItemId)->update($payload);
        } else {
            PublicNavigationItem::create(array_merge($payload, [
                'uuid' => Str::uuid()->toString(),
            ]));
        }

        $this->showNavigationItemModal = false;
        session()->flash('success', 'Navigation item saved successfully.');
    }

    public function toggleNavigationItemActive(int $itemId): void
    {
        $item = PublicNavigationItem::findOrFail($itemId);
        $item->update(['is_active' => ! $item->is_active]);

        session()->flash('success', 'Navigation item status updated.');
    }

    public function deleteNavigationItem(int $itemId): void
    {
        PublicNavigationItem::findOrFail($itemId)->delete();

        session()->flash('success', 'Navigation item deleted.');
    }

    public function render()
    {
        $data = match ($this->tab) {
            'platforms' => [
                'platforms' => Platform::paginate(10, ['*'], 'platforms_page'),
            ],
            'landing' => [
                'landingSections' => LandingSection::query()
                    ->with('items')
                    ->orderBy('sort_order')
                    ->get(),
            ],
            'navigation' => [
                'publicNavigationItems' => PublicNavigationItem::query()
                    ->orderBy('sort_order')
                    ->get(),
            ],
            'about' => [],
            default => [
                'games' => Game::with('translations')->paginate(10, ['*'], 'games_page'),
            ],
        };

        return view('livewire.admin.cms-admin', $data)->layout('components.layouts.admin', [
            'admin_title' => match ($this->tab) {
                'platforms' => 'Platforms',
                'landing' => 'Landing Page',
                'navigation' => 'Public Navigation',
                'about' => 'About Us Content',
                default => 'Games Catalog',
            },
        ]);
    }
}
