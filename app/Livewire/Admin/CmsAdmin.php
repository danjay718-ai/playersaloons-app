<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\CMS\Models\CmsPage;
use App\Modules\CMS\Models\CmsPageTranslation;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameTranslation;
use App\Modules\CMS\Models\LandingSection;
use App\Modules\CMS\Models\LandingSectionItem;
use App\Modules\CMS\Models\Platform;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\WithPagination;

class CmsAdmin extends AdminComponent
{
    use WithPagination;

    public string $tab = 'games'; // games | pages | platforms | landing

    // Game modals / forms
    public bool $showGameModal = false;

    public ?int $selectedGameId = null;

    public string $gameName = '';

    public string $gameDescription = '';

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

    // Page modals / forms
    public bool $showPageModal = false;

    public ?int $selectedPageId = null;

    public bool $isPageEdit = false;

    public string $pageSlug = '';

    public string $pageTitle = '';

    public string $pageContent = '';

    public string $pageLocale = 'en';

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
    }

    public function confirmDelete(string $type, int $id): void
    {
        $this->deleteTargetType = $type;
        $this->deleteTargetId = $id;
        $this->showDeleteModal = true;
    }

    public function executeDelete(): void
    {
        if (!$this->deleteTargetId) return;

        if ($this->deleteTargetType === 'platform') {
            $this->deletePlatform($this->deleteTargetId);
        } elseif ($this->deleteTargetType === 'page') {
            $this->deletePage($this->deleteTargetId);
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
        /** @var \App\Modules\CMS\Models\GameTranslation|null $translation */
        $translation = $game->translations()->where('locale', $this->gameLocale)->first();

        $this->gameName = $translation !== null ? $translation->name : '';
        $this->gameDescription = $translation !== null ? $translation->description : '';
        $this->showGameModal = true;
    }

    public function saveGameTranslation(): void
    {
        $this->validate([
            'gameName' => 'required|string|max:255',
            'gameDescription' => 'nullable|string',
        ]);

        if (! $this->selectedGameId) {
            return;
        }

        DB::transaction(function (): void {
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

    // --- CMS PAGE ACTIONS ---
    public function openCreatePageModal(): void
    {
        $this->selectedPageId = null;
        $this->isPageEdit = false;
        $this->pageSlug = '';
        $this->pageTitle = '';
        $this->pageContent = '';
        $this->pageLocale = 'en';
        $this->showPageModal = true;
    }

    public function openEditPageModal(int $id): void
    {
        $this->selectedPageId = $id;
        $this->isPageEdit = true;
        $page = CmsPage::findOrFail($id);
        /** @var \App\Modules\CMS\Models\CmsPageTranslation|null $translation */
        $translation = $page->translations()->where('locale', $this->pageLocale)->first();

        $this->pageSlug = $page->slug;
        $this->pageTitle = $translation !== null ? $translation->title : '';
        $this->pageContent = $translation !== null ? $translation->content : '';
        $this->showPageModal = true;
    }

    public function saveCmsPage(): void
    {
        $this->validate([
            'pageSlug' => 'required|string|max:150|alpha_dash',
            'pageTitle' => 'required|string|max:255',
            'pageContent' => 'required|string',
        ]);

        $actor = Auth::user();
        if (! $actor) {
            return;
        }

        DB::transaction(function () use ($actor): void {
            if ($this->isPageEdit && $this->selectedPageId) {
                $page = CmsPage::findOrFail($this->selectedPageId);
                $page->update([
                    'slug' => $this->pageSlug,
                ]);
            } else {
                $page = CmsPage::create([
                    'uuid' => Str::uuid()->toString(),
                    'slug' => $this->pageSlug,
                    'created_by' => $actor->id,
                ]);
                $this->selectedPageId = (int) $page->id;
            }

            CmsPageTranslation::updateOrCreate([
                'page_id' => $this->selectedPageId,
                'locale' => $this->pageLocale,
            ], [
                'title' => $this->pageTitle,
                'content' => $this->pageContent,
            ]);
        });

        session()->flash('success', 'CMS page saved successfully.');
        $this->showPageModal = false;
    }

    public function publishPage(int $id): void
    {
        $page = CmsPage::findOrFail($id);
        $page->update(['published_at' => now()]);

        session()->flash('success', 'CMS page published successfully.');
    }

    public function deletePage(int $id): void
    {
        $page = CmsPage::findOrFail($id);
        $page->delete();

        session()->flash('success', 'CMS page deleted.');
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

    public function render()
    {
        $games = Game::with('translations')->paginate(10, ['*'], 'games_page');
        $pages = CmsPage::with('translations')->paginate(10, ['*'], 'pages_page');
        $platforms = Platform::paginate(10, ['*'], 'platforms_page');
        $landingSections = LandingSection::query()
            ->with('items')
            ->orderBy('sort_order')
            ->get();

        return view('livewire.admin.cms-admin', [
            'games' => $games,
            'pages' => $pages,
            'platforms' => $platforms,
            'landingSections' => $landingSections,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Games & Content Management System (CMS)',
        ]);
    }
}
