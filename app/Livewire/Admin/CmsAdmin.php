<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\CMS\Models\CmsPage;
use App\Modules\CMS\Models\CmsPageTranslation;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameTranslation;
use App\Modules\CMS\Models\Platform;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\WithPagination;

class CmsAdmin extends AdminComponent
{
    use WithPagination;

    public string $tab = 'games'; // games | pages

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

    protected $paginationTheme = 'tailwind';

    public function setTab(string $tabName): void
    {
        $this->tab = $tabName;
        $this->resetPage();
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
        $translation = $game->translations()->where('locale', $this->gameLocale)->first();

        $this->gameName = $translation?->name ?? '';
        $this->gameDescription = $translation?->description ?? '';
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
        $translation = $page->translations()->where('locale', $this->pageLocale)->first();

        $this->pageSlug = $page->slug;
        $this->pageTitle = $translation?->title ?? '';
        $this->pageContent = $translation?->content ?? '';
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
        $page->published_at = now();
        $page->save();

        session()->flash('success', 'CMS page published successfully.');
    }

    public function deletePage(int $id): void
    {
        $page = CmsPage::findOrFail($id);
        $page->delete();

        session()->flash('success', 'CMS page deleted.');
    }

    public function render()
    {
        $games = Game::with('translations')->paginate(10, ['*'], 'games_page');
        $pages = CmsPage::with('translations')->paginate(10, ['*'], 'pages_page');
        $platforms = Platform::paginate(10, ['*'], 'platforms_page');

        return view('livewire.admin.cms-admin', [
            'games' => $games,
            'pages' => $pages,
            'platforms' => $platforms,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Games & Content Management System (CMS)',
        ]);
    }
}
