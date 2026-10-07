<?php

declare(strict_types=1);

namespace App\Modules\Localization\Observers;

use App\Modules\Localization\Services\TranslationCatalogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class CompetitionTranslationObserver
{
    public function __construct(private readonly TranslationCatalogService $catalog) {}

    public function created(Model $model): void
    {
        $this->sync($model);
    }

    public function updated(Model $model): void
    {
        if (! $model->wasChanged(['name', 'description', 'rules', 'settings_json', 'overrides_json'])) {
            return;
        }
        $this->sync($model);
    }

    private function sync(Model $model): void
    {
        // Models can also be seeded before the localization migration is installed.
        if (! Schema::hasTable('translation_strings')) {
            return;
        }

        $this->catalog->syncCompetitionContent($model);
    }
}
