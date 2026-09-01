<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameTranslation;
use App\Modules\CMS\Models\LandingSection;
use App\Modules\CMS\Models\LandingSectionItem;
use App\Modules\CMS\Models\Platform;
use App\Modules\CMS\Models\PublicNavigationItem;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\SystemSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class CmsAdmin extends AdminComponent
{
    use WithFileUploads, WithPagination;

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('cms.view') || $this->actor()->can('cms.manage') || $this->actor()->can('games.view'), 403);
    }

    public string $tab = 'games'; // games | pages | platforms | landing | navigation | about

    #[Url(as: 'game_q')]
    public string $gameSearch = '';

    #[Url(as: 'game_records')]
    public string $gameRecordTab = 'active';

    #[Url(as: 'game_status')]
    public string $gameCatalogFilter = '';

    #[Url(as: 'game_platform')]
    public string $gamePlatformFilter = '';

    #[Url(as: 'game_template')]
    public string $gameTemplateFilter = '';

    #[Url(as: 'game_artwork')]
    public string $gameArtworkFilter = '';

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

    public string $gameSlug = '';

    public string $gameDescription = '';

    public string $gameBannerPath = '';

    public string $gameCardImagePath = '';

    public $gameCardImage = null;

    public $gameBannerImage = null;

    public bool $gameIsActive = true;

    public string $gameIdLabel = 'Game ID / In-Game Name';

    public string $gameIdExample = '';

    public string $gameIdInstructions = '';

    public string $gameConnectionMethod = 'player_invite';

    /** @var list<int> */
    public array $gamePlatformIds = [];

    public bool $removeGameCardImage = false;

    public bool $removeGameBannerImage = false;

    public string $gameLocale = 'en';

    /** @var array<string, mixed> */
    public array $gameDeleteImpact = [];

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

    public function setGameRecordTab(string $tab): void
    {
        if (! in_array($tab, ['active', 'archived'], true)) {
            return;
        }

        $this->gameRecordTab = $tab;
        $this->resetPage('games_page');
    }

    public function clearGameFilters(): void
    {
        $this->gameSearch = '';
        $this->gameCatalogFilter = '';
        $this->gamePlatformFilter = '';
        $this->gameTemplateFilter = '';
        $this->gameArtworkFilter = '';
        $this->resetPage('games_page');
    }

    public function updatingGameSearch(): void
    {
        $this->resetPage('games_page');
    }

    public function updatingGameCatalogFilter(): void
    {
        $this->resetPage('games_page');
    }

    public function updatingGamePlatformFilter(): void
    {
        $this->resetPage('games_page');
    }

    public function updatingGameTemplateFilter(): void
    {
        $this->resetPage('games_page');
    }

    public function updatingGameArtworkFilter(): void
    {
        $this->resetPage('games_page');
    }

    private function loadAboutSettings(): void
    {
        $settings = SystemSetting::query()->whereIn('key', ['about.title', 'about.subtitle', 'about.body'])->pluck('value', 'key');
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
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'updated_by' => Auth::id()]
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

        if ($type === 'game') {
            $this->loadGameDeleteImpact($id);
        }
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
        } elseif ($this->deleteTargetType === 'game') {
            $this->archiveGame($this->deleteTargetId);
        }

        $this->showDeleteModal = false;
        $this->deleteTargetId = null;
        $this->deleteTargetType = '';
        $this->gameDeleteImpact = [];
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
    public function updatedGameName(string $name): void
    {
        if ($this->selectedGameId === null) {
            $this->gameSlug = Str::slug($name);
        }
    }

    public function openGameCreateModal(): void
    {
        $this->resetGameForm();
        $this->showGameModal = true;
    }

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
        $game = Game::query()
            ->with(['translations', 'platforms:id'])
            ->findOrFail($gameId);
        /** @var GameTranslation|null $translation */
        $translation = $game->translations->firstWhere('locale', $this->gameLocale);

        $this->gameName = $translation !== null ? $translation->name : '';
        $this->gameSlug = $game->slug;
        $this->gameDescription = $translation !== null ? $translation->description : '';
        $this->gameBannerPath = (string) ($game->bannerUrl() ?? '');
        $this->gameCardImagePath = (string) ($game->cardArtworkUrl() ?? '');
        $this->gameIsActive = (bool) $game->is_active;
        $gameIdSettings = (array) ($game->game_id_settings['default'] ?? []);
        $this->gameIdLabel = (string) ($gameIdSettings['label'] ?? 'Game ID / In-Game Name');
        $this->gameIdExample = (string) ($gameIdSettings['example'] ?? '');
        $this->gameIdInstructions = (string) ($gameIdSettings['instructions'] ?? '');
        $this->gameConnectionMethod = (string) ($gameIdSettings['connection_method'] ?? 'player_invite');
        $this->gamePlatformIds = $game->platforms->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->removeGameCardImage = false;
        $this->removeGameBannerImage = false;
        $this->reset('gameCardImage', 'gameBannerImage');
        $this->showGameModal = true;
    }

    public function loadSelectedGameTranslation(): void
    {
        if ($this->selectedGameId === null) {
            return;
        }

        $translation = GameTranslation::query()
            ->where('game_id', $this->selectedGameId)
            ->where('locale', $this->gameLocale)
            ->first();

        $this->gameName = $translation?->name ?? '';
        $this->gameDescription = $translation?->description ?? '';
    }

    public function saveGameTranslation(): void
    {
        $newGameNeedsArtwork = $this->selectedGameId === null
            && $this->gameCardImage === null
            && $this->gameBannerImage === null;

        $this->validate([
            'gameName' => 'required|string|max:255',
            'gameSlug' => ['required', 'string', 'max:150', 'alpha_dash', Rule::unique('games', 'slug')->ignore($this->selectedGameId)],
            'gameDescription' => 'nullable|string',
            'gameBannerPath' => 'nullable|string|max:255',
            'gameCardImage' => [Rule::requiredIf($newGameNeedsArtwork), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp'],
            'gameBannerImage' => [Rule::requiredIf($newGameNeedsArtwork), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp'],
            'gameIsActive' => 'boolean',
            'gameIdLabel' => 'required|string|max:80',
            'gameIdExample' => 'nullable|string|max:120',
            'gameIdInstructions' => 'nullable|string|max:1000',
            'gameConnectionMethod' => 'required|in:player_invite,lobby_code,server_room,admin_instructions',
            'gamePlatformIds' => $this->selectedGameId === null
                ? ['required', 'array', 'min:1']
                : ['array'],
            'gamePlatformIds.*' => 'integer|exists:platforms,id',
            'removeGameCardImage' => 'boolean',
            'removeGameBannerImage' => 'boolean',
        ], [
            'gameCardImage.required' => 'Upload either a game card image or a hero cover.',
            'gameBannerImage.required' => 'Upload either a hero cover or a game card image.',
        ]);

        DB::transaction(function (): void {
            $game = $this->selectedGameId
                ? Game::query()->findOrFail($this->selectedGameId)
                : Game::query()->create([
                    'uuid' => Str::uuid()->toString(),
                    'slug' => Str::slug($this->gameSlug),
                    'is_active' => $this->gameIsActive,
                ]);

            $media = [
                'slug' => Str::slug($this->gameSlug),
                'is_active' => $this->gameIsActive,
                'card_image_path' => $this->removeGameCardImage ? null : ($this->gameCardImagePath !== '' ? $this->gameCardImagePath : null),
                'banner_path' => $this->removeGameBannerImage ? null : ($this->gameBannerPath !== '' ? $this->gameBannerPath : null),
                'game_id_settings' => [
                    'default' => [
                        'label' => trim($this->gameIdLabel),
                        'example' => trim($this->gameIdExample),
                        'instructions' => trim($this->gameIdInstructions),
                        'connection_method' => $this->gameConnectionMethod,
                    ],
                ],
            ];

            if ($this->gameCardImage) {
                $media['card_image_path'] = '/storage/'.$this->gameCardImage->store('games/cards', 'public');
            }

            if ($this->gameBannerImage) {
                $media['banner_path'] = '/storage/'.$this->gameBannerImage->store('games/banners', 'public');
            }

            $game->update($media);
            $game->platforms()->sync($this->gamePlatformIds);

            GameTranslation::updateOrCreate([
                'game_id' => $game->id,
                'locale' => $this->gameLocale,
            ], [
                'name' => $this->gameName,
                'description' => $this->gameDescription,
            ]);

            $this->selectedGameId = (int) $game->id;
        });

        session()->flash('success', 'Game saved successfully.');
        $this->showGameModal = false;
        $this->resetGameForm();
    }

    public function archiveGame(int $gameId): void
    {
        $game = Game::query()->findOrFail($gameId);
        $game->update(['is_active' => false]);
        $game->delete();

        session()->flash('success', 'Game archived. Historical tournaments and player records were preserved.');
    }

    public function restoreGame(int $gameId): void
    {
        Game::onlyTrashed()->findOrFail($gameId)->restore();

        session()->flash('success', 'Game restored as disabled. Activate it when it is ready for display.');
    }

    private function loadGameDeleteImpact(int $gameId): void
    {
        $game = Game::query()->findOrFail($gameId);
        $tournamentQuery = DB::table('tournaments')->where('game_id', $gameId);
        $tournamentCount = (clone $tournamentQuery)->count();
        $tournaments = $tournamentQuery
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'name', 'status']);
        $registrationCounts = DB::table('tournament_registrations')
            ->whereIn('tournament_id', $tournaments->pluck('id'))
            ->selectRaw('tournament_id, COUNT(*) as total')
            ->groupBy('tournament_id')
            ->pluck('total', 'tournament_id');
        $directPlayers = DB::table('tournament_registrations')
            ->join('tournaments', 'tournaments.id', '=', 'tournament_registrations.tournament_id')
            ->where('tournaments.game_id', $gameId)
            ->whereNotNull('user_id')
            ->pluck('tournament_registrations.user_id');
        $rosterPlayers = DB::table('tournament_registration_members')
            ->join('tournament_registrations', 'tournament_registrations.id', '=', 'tournament_registration_members.registration_id')
            ->join('tournaments', 'tournaments.id', '=', 'tournament_registrations.tournament_id')
            ->where('tournaments.game_id', $gameId)
            ->pluck('tournament_registration_members.user_id');
        $playerIds = $directPlayers->merge($rosterPlayers)->unique()->values();
        $players = User::query()
            ->with('profile')
            ->whereKey($playerIds)
            ->orderBy('username')
            ->limit(50)
            ->get()
            ->map(fn (User $user): array => [
                'username' => $user->username,
                'display_name' => $user->profile?->display_name ?: $user->username,
            ]);

        $this->gameDeleteImpact = [
            'name' => $game->localizedName('en'),
            'tournament_count' => $tournamentCount,
            'remaining_tournament_count' => max(0, $tournamentCount - $tournaments->count()),
            'player_count' => $playerIds->count(),
            'players' => $players->all(),
            'remaining_player_count' => max(0, $playerIds->count() - $players->count()),
            'tournaments' => $tournaments->map(fn ($tournament): array => [
                'name' => (string) $tournament->name,
                'status' => (string) $tournament->status,
                'registrations' => (int) ($registrationCounts[$tournament->id] ?? 0),
            ])->all(),
        ];
    }

    private function resetGameForm(): void
    {
        $this->selectedGameId = null;
        $this->gameName = '';
        $this->gameSlug = '';
        $this->gameDescription = '';
        $this->gameBannerPath = '';
        $this->gameCardImagePath = '';
        $this->gameLocale = 'en';
        $this->gameIsActive = true;
        $this->gameIdLabel = 'Game ID / In-Game Name';
        $this->gameIdExample = '';
        $this->gameIdInstructions = '';
        $this->gameConnectionMethod = 'player_invite';
        $this->gamePlatformIds = [];
        $this->removeGameCardImage = false;
        $this->removeGameBannerImage = false;
        $this->reset('gameCardImage', 'gameBannerImage');
        $this->resetValidation();
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
                'games' => Game::query()
                    ->when($this->gameRecordTab === 'archived', fn ($query) => $query->onlyTrashed(), fn ($query) => $query->withoutTrashed())
                    ->with([
                        'translations:id,game_id,locale,name,description',
                        'platforms:id,name',
                        'tournamentDefaults:id,game_id,default_platform_id,tournament_banner_path',
                    ])
                    ->when($this->gameSearch !== '', function ($query): void {
                        $term = '%'.$this->gameSearch.'%';
                        $query->where(function ($games) use ($term): void {
                            $games->where('slug', 'like', $term)
                                ->orWhereHas('translations', fn ($translations) => $translations
                                    ->where('name', 'like', $term)
                                    ->orWhere('description', 'like', $term))
                                ->orWhereHas('platforms', fn ($platforms) => $platforms->where('name', 'like', $term));
                        });
                    })
                    ->when($this->gameCatalogFilter !== '', fn ($query) => $query->where('is_active', $this->gameCatalogFilter === 'enabled'))
                    ->when($this->gamePlatformFilter !== '', fn ($query) => $query->whereHas('platforms', fn ($platforms) => $platforms->whereKey($this->gamePlatformFilter)))
                    ->when($this->gameTemplateFilter === 'configured', fn ($query) => $query->has('tournamentDefaults'))
                    ->when($this->gameTemplateFilter === 'missing', fn ($query) => $query->doesntHave('tournamentDefaults'))
                    ->when($this->gameArtworkFilter === 'complete', fn ($query) => $query->whereNotNull('card_image_path')->whereNotNull('banner_path'))
                    ->when($this->gameArtworkFilter === 'missing_card', fn ($query) => $query->whereNull('card_image_path'))
                    ->when($this->gameArtworkFilter === 'missing_banner', fn ($query) => $query->whereNull('banner_path'))
                    ->orderBy('slug')
                    ->paginate(10, ['id', 'uuid', 'slug', 'banner_path', 'card_image_path', 'is_active', 'deleted_at'], 'games_page'),
                'gamePlatforms' => Platform::query()->orderBy('name')->get(['id', 'name']),
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
