<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Localization\Models\TranslationString;
use App\Modules\Localization\Services\TranslationCatalogService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Schema;
use Livewire\WithPagination;

final class TranslationAdmin extends AdminComponent
{
    use WithPagination;

    public string $search = '';

    public string $localeFilter = 'all';

    public bool $missingOnly = false;

    public bool $showEditModal = false;

    public string $editingKey = '';

    public string $newKey = '';

    /** @var array<string, string|null> */
    public array $values = [];

    public bool $translationTableReady = true;

    protected $paginationTheme = 'tailwind';

    public function mount(TranslationCatalogService $catalog): void
    {
        $this->translationTableReady = Schema::hasTable('translation_strings');

        if (! $this->translationTableReady) {
            return;
        }

        if (TranslationString::query()->doesntExist()) {
            $catalog->syncFromJsonFiles();
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLocaleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedMissingOnly(): void
    {
        $this->resetPage();
    }

    public function syncFromJson(TranslationCatalogService $catalog): void
    {
        $count = $catalog->syncFromJsonFiles();

        session()->flash('success', "Synced {$count} translations from JSON files.");
    }

    public function exportJson(TranslationCatalogService $catalog): void
    {
        $catalog->exportJsonFiles();

        session()->flash('success', 'Translation JSON files exported successfully.');
    }

    public function fillMissingWithEnglish(TranslationCatalogService $catalog): void
    {
        $count = $catalog->fillMissingWithEnglishFallback();
        $catalog->exportJsonFiles();

        session()->flash('success', "Filled {$count} missing translations with English fallback text and exported JSON.");
    }

    public function createKey(TranslationCatalogService $catalog): void
    {
        $this->validate([
            'newKey' => ['required', 'string', 'max:500'],
        ]);

        $catalog->createKey($this->newKey);
        $catalog->exportJsonFiles();

        $this->newKey = '';
        $this->resetPage();

        session()->flash('success', 'Translation key created.');
    }

    public function editKey(string $key): void
    {
        $this->editingKey = $key;
        $this->values = [];

        foreach (array_keys(config('localization.supported', [])) as $locale) {
            $this->values[$locale] = TranslationString::query()
                ->where('key', $key)
                ->where('locale', $locale)
                ->value('text');
        }

        $this->showEditModal = true;
    }

    public function saveKey(TranslationCatalogService $catalog): void
    {
        if ($this->editingKey === '') {
            return;
        }

        $catalog->saveKey($this->editingKey, $this->values);
        $catalog->exportJsonFiles();

        $this->showEditModal = false;
        $this->editingKey = '';
        $this->values = [];

        session()->flash('success', 'Translation saved and exported.');
    }

    public function deleteKey(TranslationCatalogService $catalog, string $key): void
    {
        $catalog->deleteKey($key);
        $catalog->exportJsonFiles();

        session()->flash('success', 'Translation key deleted.');
    }

    public function render(): Renderable
    {
        $supportedLocales = array_keys(config('localization.supported', []));

        if (! $this->translationTableReady) {
            return view('livewire.admin.translation-admin', [
                'keys' => collect(),
                'rows' => collect(),
                'languages' => config('localization.supported', []),
                'missingCounts' => [],
            ])->layout('components.layouts.admin', [
                'title' => 'Translations | PlayerSaloons',
                'admin_title' => 'Translations',
            ]);
        }

        $baseQuery = TranslationString::query()
            ->select('key')
            ->where('locale', 'en')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($nested): void {
                    $nested->where('key', 'like', '%'.$this->search.'%')
                        ->orWhere('text', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->missingOnly && $this->localeFilter !== 'all', function ($query): void {
                $query->whereIn('key', TranslationString::query()
                    ->select('key')
                    ->where('locale', $this->localeFilter)
                    ->where(function ($missing): void {
                        $missing->whereNull('text')->orWhere('text', '');
                    }));
            })
            ->orderBy('key');

        $keys = $baseQuery->paginate(20);
        $rows = TranslationString::query()
            ->whereIn('key', $keys->pluck('key')->all())
            ->get()
            ->groupBy('key');

        $missingCounts = [];
        foreach ($supportedLocales as $locale) {
            if ($locale === 'en') {
                continue;
            }

            $missingCounts[$locale] = TranslationString::query()
                ->where('locale', $locale)
                ->where(function ($query): void {
                    $query->whereNull('text')->orWhere('text', '');
                })
                ->count();
        }

        return view('livewire.admin.translation-admin', [
            'keys' => $keys,
            'rows' => $rows,
            'languages' => config('localization.supported', []),
            'missingCounts' => $missingCounts,
        ])->layout('components.layouts.admin', [
            'title' => 'Translations | PlayerSaloons',
            'admin_title' => 'Translations',
        ]);
    }
}
