<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Localization\Actions\AddTranslationLanguageAction;
use App\Modules\Localization\Actions\ImportTranslationsAction;
use App\Modules\Localization\Actions\ResetTranslationsAction;
use App\Modules\Localization\Models\TranslationString;
use App\Modules\Localization\Services\LanguageRegistry;
use App\Modules\Localization\Services\TranslationCatalogService;
use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Operations\Services\AdminDeletionService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

final class TranslationAdmin extends AdminComponent
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $localeFilter = 'en';

    #[Locked]
    public string $editingLocale = 'en';

    public bool $missingOnly = false;

    public bool $showEditModal = false;

    #[Locked]
    public string $editingKey = '';

    public string $newKey = '';

    public array $values = [];

    #[Locked]
    public bool $translationTableReady = true;

    #[Locked]
    public bool $resetTableReady = true;

    #[Locked]
    public bool $languageTableReady = true;

    public ?TemporaryUploadedFile $importFile = null;

    public string $importLocale = '';

    public string $resetLocale = 'all';

    public string $resetConfirmation = '';

    public string $code = '';

    public string $english = '';

    public string $native = '';

    public string $flag = 'un';

    protected $paginationTheme = 'tailwind';

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('translations.view'), 403);
    }

    public function mount(): void
    {
        $this->translationTableReady = Schema::hasTable('translation_strings');
        $this->resetTableReady = Schema::hasTable('translation_language_resets');
        $this->languageTableReady = Schema::hasTable('translation_languages') && Schema::hasColumn('translation_languages', 'flag');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMissingOnly(): void
    {
        $this->resetPage();
    }

    public function updatedLocaleFilter(): void
    {
        $this->assertSupportedLocale();
        $this->reset('showEditModal', 'editingKey', 'values');
        $this->resetPage();
    }

    private function assertSupportedLocale(): void
    {
        abort_unless(array_key_exists($this->localeFilter, app(LanguageRegistry::class)->supported()), 422);
    }

    public function setLanguageAvailability(string $locale, bool $enabled): void
    {
        abort_unless($this->actor()->can('translations.manage'), 403);
        abort_unless($locale !== 'en' && array_key_exists($locale, app(LanguageRegistry::class)->supported()), 422);
        SystemSetting::query()->updateOrCreate(['key' => 'language.disabled.'.$locale], [
            'value' => $enabled ? '0' : '1', 'updated_by' => $this->actor()->id,
        ]);
        Cache::forget('localization.disabled_languages');
    }

    public function syncFromJson(TranslationCatalogService $catalog): void
    {
        abort_unless($this->actor()->can('translations.manage'), 403);
        $count = $catalog->syncFromJsonFiles();
        session()->flash('success', __('Synced :count English phrases.', ['count' => $count]));
    }

    public function syncFromDatabase(TranslationCatalogService $catalog): void
    {
        abort_unless($this->actor()->can('translations.manage'), 403);
        $catalog->syncFromDatabaseContent();
        $this->resetPage();
        session()->flash('success', __('Database phrases synced.'));
    }

    public function exportEnglish(TranslationCatalogService $catalog)
    {
        abort_unless($this->actor()->can('translations.manage'), 403);
        $export = $catalog->englishExport();

        return response()->streamDownload(function () use ($export): void {
            echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }, 'english-translations.json', ['Content-Type' => 'application/json']);
    }

    public function importTranslations(ImportTranslationsAction $import): void
    {
        abort_unless($this->actor()->can('translations.manage'), 403);
        $this->validate([
            'importFile' => ['required', 'file', 'extensions:json', 'max:10240'],
            'importLocale' => ['required', Rule::in(array_diff(array_keys(app(LanguageRegistry::class)->supported()), ['en']))],
        ]);
        $count = $import->execute($this->actor(), $this->importLocale, $this->importFile->get());
        $this->reset('importFile');
        $this->localeFilter = $this->importLocale;
        $this->updatedLocaleFilter();
        session()->flash('success', __('Imported :count translations. New English phrases added since export remain untranslated.', ['count' => $count]));
    }

    public function addLanguage(AddTranslationLanguageAction $add): void
    {
        $add->execute($this->actor(), strtolower(trim($this->code)), trim($this->english), trim($this->native) ?: trim($this->english), $this->flag);
        $this->dispatch('translation-language-added', locale: strtolower(trim($this->code)));
        $this->reset('code', 'english', 'native', 'flag');
        session()->flash('success', __('Language added. Export English and import its translations to get started.'));
    }

    public function resetTranslations(ResetTranslationsAction $reset): void
    {
        $count = $reset->execute($this->actor(), $this->resetLocale, $this->resetConfirmation);
        $this->reset('resetConfirmation', 'showEditModal', 'editingKey', 'values');
        $this->resetPage();
        $this->dispatch('translation-reset-completed');
        session()->flash('success', __('Translations reset. :count stored entries removed. English phrases are unchanged.', ['count' => $count]));
    }

    public function createKey(TranslationCatalogService $catalog): void
    {
        abort_unless($this->actor()->can('translations.manage'), 403);
        $this->validate(['newKey' => ['required', 'string', 'max:500']]);
        $catalog->createKey($this->newKey);
        $this->reset('newKey');
        $this->resetPage();
        session()->flash('success', __('English phrase created.'));
    }

    public function editKey(string $key): void
    {
        abort_unless($this->actor()->can('translations.manage'), 403);
        $this->assertSupportedLocale();
        abort_unless(TranslationString::query()->where('key', $key)->where('locale', 'en')->exists(), 404);
        $this->editingKey = $key;
        $this->editingLocale = $this->localeFilter;
        $this->values = [$this->editingLocale => TranslationString::query()->where('key', $key)
            ->where('locale', $this->editingLocale)->value('text')];
        $this->showEditModal = true;
    }

    public function saveKey(TranslationCatalogService $catalog): void
    {
        abort_unless($this->actor()->can('translations.manage'), 403);
        if ($this->editingKey === '') {
            return;
        }
        $this->validate(['values.'.$this->editingLocale => [$this->editingLocale === 'en' ? 'required' : 'nullable', 'string', 'max:20000']]);
        $catalog->saveTranslation($this->editingKey, $this->editingLocale, $this->values[$this->editingLocale] ?? null);
        $this->reset('showEditModal', 'editingKey', 'values');
        session()->flash('success', __('Translation saved.'));
    }

    public function deleteKey(AdminDeletionService $deletion, string $key): void
    {
        $row = TranslationString::query()->where('key', $key)->where('locale', 'en')->firstOrFail();
        $deletion->delete('translations', [$row->id], $this->actor());
        session()->flash('success', __('English phrase deleted.'));
    }

    public function render(): Renderable
    {
        $this->assertSupportedLocale();
        $catalog = app(TranslationCatalogService::class);
        $languages = $catalog->supportedLanguages();
        $availableLanguages = app(LanguageRegistry::class)->available();
        $keys = collect();
        $rows = collect();
        $missingCounts = [];
        if ($this->translationTableReady) {
            $statistics = $catalog->statistics();
            $keys = $catalog->sourceQuery()->select('id', 'key', 'text')
                ->when($this->search !== '', function ($query): void {
                    $query->where(function ($nested): void {
                        $nested->where('key', 'like', '%'.$this->search.'%')->orWhere('text', 'like', '%'.$this->search.'%')
                            ->orWhereIn('key', TranslationString::query()->select('key')->where('locale', $this->localeFilter)
                                ->where('text', 'like', '%'.$this->search.'%'));
                    });
                })
                ->when($this->missingOnly, function ($query): void {
                    $query->whereNotIn('key', TranslationString::query()->select('key')->where('locale', $this->localeFilter)
                        ->whereNotNull('text')->where('text', '!=', ''));
                })
                ->orderBy('key')->paginate(20, total: $this->search === '' && ! $this->missingOnly ? $statistics['total'] : null);
            $rows = TranslationString::query()->whereIn('key', $keys->pluck('key')->all())
                ->whereIn('locale', ['en', $this->localeFilter])->get()->groupBy('key');
            $missingCounts = $statistics['missing'];
        }

        return view('livewire.admin.translation-admin', compact('keys', 'rows', 'languages', 'missingCounts', 'availableLanguages'))
            ->layout('components.layouts.admin', ['title' => 'Translations | GamersRival', 'admin_title' => 'Translations']);
    }
}
