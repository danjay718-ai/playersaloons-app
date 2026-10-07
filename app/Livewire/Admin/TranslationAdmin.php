<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Localization\Models\TranslationString;
use App\Modules\Localization\Services\TranslationCatalogService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

final class TranslationAdmin extends AdminComponent
{
    use WithPagination;

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('translations.view'), 403);
    }

    public string $search = '';

    public string $localeFilter = 'en';

    /** @var list<string> */
    #[Locked]
    public array $hiddenLanguages = [];

    #[Locked]
    public string $editingLocale = 'en';

    public bool $missingOnly = false;

    public bool $showEditModal = false;

    public string $editingKey = '';

    public string $newKey = '';

    /** @var array<string, string|null> */
    public array $values = [];

    public bool $translationTableReady = true;

    protected $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->translationTableReady = Schema::hasTable('translation_strings');
        $stored = session()->get($this->visibilitySessionKey(), []);
        $supported = config('localization.supported', []);
        $this->hiddenLanguages = is_array($stored)
            ? array_values(array_unique(array_filter($stored, fn ($locale): bool => is_string($locale) && $locale !== 'en' && array_key_exists($locale, $supported))))
            : [];
    }

    public function toggleLanguage(string $locale): void
    {
        abort_unless($locale !== 'en' && array_key_exists($locale, config('localization.supported', [])), 422);
        if (in_array($locale, $this->hiddenLanguages, true)) {
            $this->hiddenLanguages = array_values(array_diff($this->hiddenLanguages, [$locale]));
        } else {
            $this->hiddenLanguages[] = $locale;
        }
        session()->put($this->visibilitySessionKey(), $this->hiddenLanguages);
        if (in_array($this->localeFilter, $this->hiddenLanguages, true)) {
            $this->localeFilter = 'en';
            $this->updatedLocaleFilter();
        }
    }

    public function showAllLanguages(): void
    {
        $this->hiddenLanguages = [];
        session()->forget($this->visibilitySessionKey());
    }

    private function visibilitySessionKey(): string
    {
        return 'translation_hidden_languages.'.$this->actor()->getAuthIdentifier();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLocaleFilter(): void
    {
        $this->assertSupportedLocale();
        $this->showEditModal = false;
        $this->editingKey = '';
        $this->values = [];
        $this->resetPage();
    }

    private function assertSupportedLocale(): void
    {
        abort_unless(array_key_exists($this->localeFilter, config('localization.supported', []))
            && ! in_array($this->localeFilter, $this->hiddenLanguages, true), 422);
    }

    public function updatedMissingOnly(): void
    {
        $this->resetPage();
    }

    public function syncFromJson(TranslationCatalogService $catalog): void
    {
        abort_unless($this->actor()->can('translations.manage'), 403);
        $count = $catalog->syncFromJsonFiles();

        session()->flash('success', "Synced {$count} translations from JSON files.");
    }

    public function syncFromDatabase(TranslationCatalogService $catalog): void
    {
        abort_unless($this->actor()->can('translations.manage'), 403);
        $catalog->syncFromDatabaseContent();
        $this->resetPage();

        session()->flash('success', __('Database phrases synced.'));
    }

    public function exportJson(TranslationCatalogService $catalog): void
    {
        abort_unless($this->actor()->can('translations.manage'), 403);
        $catalog->exportJsonFiles();

        session()->flash('success', 'Translation JSON files exported successfully.');
    }

    public function fillMissingWithEnglish(TranslationCatalogService $catalog): void
    {
        abort_unless($this->actor()->can('translations.manage'), 403);
        $count = $catalog->fillMissingWithEnglishFallback();
        $catalog->exportJsonFiles();

        session()->flash('success', "Filled {$count} missing translations with English fallback text and exported JSON.");
    }

    public function createKey(TranslationCatalogService $catalog): void
    {
        abort_unless($this->actor()->can('translations.manage'), 403);
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
        abort_unless($this->actor()->can('translations.manage'), 403);
        $this->assertSupportedLocale();
        $this->editingKey = $key;
        $this->editingLocale = $this->localeFilter;
        $this->values = [$this->editingLocale => TranslationString::query()
            ->where('key', $key)
            ->where('locale', $this->editingLocale)
            ->value('text')];

        $this->showEditModal = true;
    }

    public function saveKey(TranslationCatalogService $catalog): void
    {
        abort_unless($this->actor()->can('translations.manage'), 403);
        if ($this->editingKey === '') {
            return;
        }

        $this->validate([
            'values.'.$this->editingLocale => ['nullable', 'string'],
        ]);
        $catalog->saveTranslation($this->editingKey, $this->editingLocale, $this->values[$this->editingLocale] ?? null);
        $catalog->exportJsonFiles();

        $this->showEditModal = false;
        $this->editingKey = '';
        $this->values = [];

        session()->flash('success', 'Translation saved and exported.');
    }

    public function deleteKey(TranslationCatalogService $catalog, string $key): void
    {
        abort_unless($this->actor()->can('translations.delete'), 403);
        $catalog->deleteKey($key);
        $catalog->exportJsonFiles();

        session()->flash('success', 'Translation key deleted.');
    }

    public function render(): Renderable
    {
        $this->assertSupportedLocale();
        $languages = config('localization.supported', []);
        $visibleLanguages = array_diff_key($languages, array_flip($this->hiddenLanguages));
        $supportedLocales = array_keys($visibleLanguages);

        if (! $this->translationTableReady) {
            return view('livewire.admin.translation-admin', [
                'keys' => collect(),
                'rows' => collect(),
                'languages' => $languages,
                'visibleLanguages' => $visibleLanguages,
                'missingCounts' => [],
            ])->layout('components.layouts.admin', [
                'title' => 'Translations | GamersRival',
                'admin_title' => 'Translations',
            ]);
        }

        $baseQuery = TranslationString::query()
            ->select('id', 'key', 'text')
            ->where('locale', 'en')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($nested): void {
                    $nested->where('key', 'like', '%'.$this->search.'%')
                        ->orWhere('text', 'like', '%'.$this->search.'%')
                        ->orWhereIn('key', TranslationString::query()
                            ->select('key')
                            ->where('locale', $this->localeFilter)
                            ->where('text', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->missingOnly, function ($query): void {
                $query->whereNotIn('key', TranslationString::query()
                    ->select('key')
                    ->where('locale', $this->localeFilter)
                    ->whereNotNull('text')
                    ->where('text', '!=', ''));
            })
            ->orderBy('key');

        $keys = $baseQuery->paginate(20);
        $rows = TranslationString::query()
            ->whereIn('key', $keys->pluck('key')->all())
            ->whereIn('locale', ['en', $this->localeFilter])
            ->get()
            ->groupBy('key');

        $missingCounts = [];
        foreach ($supportedLocales as $locale) {
            if ($locale === 'en') {
                continue;
            }

            $missingCounts[$locale] = TranslationString::query()
                ->where('locale', 'en')
                ->whereNotIn('key', TranslationString::query()
                    ->select('key')
                    ->where('locale', $locale)
                    ->whereNotNull('text')
                    ->where('text', '!=', ''))
                ->count();
        }

        return view('livewire.admin.translation-admin', [
            'keys' => $keys,
            'rows' => $rows,
            'languages' => $languages,
            'visibleLanguages' => $visibleLanguages,
            'missingCounts' => $missingCounts,
        ])->layout('components.layouts.admin', [
            'title' => 'Translations | GamersRival',
            'admin_title' => 'Translations',
        ]);
    }
}
