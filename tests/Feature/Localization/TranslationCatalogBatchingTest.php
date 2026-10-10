<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Modules\Localization\Models\TranslationString;
use App\Modules\Localization\Services\TranslationCatalogService;
use Database\Seeders\TranslationStringSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TranslationCatalogBatchingTest extends TestCase
{
    use RefreshDatabase;

    public static function catalogSizes(): array
    {
        return [[251], [5000]];
    }

    #[DataProvider('catalogSizes')]
    public function test_startup_translation_seed_bounds_database_writes_and_preserves_existing_translations(int $keyCount): void
    {
        $directory = sys_get_temp_dir().'/translation-batches-'.Str::uuid();
        File::makeDirectory($directory);
        $this->app->useLangPath($directory);
        $now = now()->subDay();
        $keys = [];
        for ($index = 0; $index < $keyCount; $index++) {
            $keys[] = 'Catalog phrase '.$index.' '.str_repeat('x', 450);
        }
        foreach (array_chunk($keys, 100) as $batch) {
            DB::table('translation_strings')->insert(array_map(fn ($key) => [
                'key' => $key, 'locale' => 'en', 'text' => $key,
                'created_at' => $now, 'updated_at' => $now,
            ], $batch));
        }
        $saved = TranslationString::query()->create(['key' => $keys[0], 'locale' => 'fr', 'text' => 'Saved translation']);
        $deleted = TranslationString::query()->create(['key' => $keys[1], 'locale' => 'fr', 'text' => 'Deleted translation']);
        $deleted->delete();
        $savedTimestamp = $saved->updated_at->toDateTimeString();

        File::put($directory.'/en.json', json_encode(['New startup phrase' => 'New startup phrase']));
        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (str_contains($query->sql, 'translation_strings') && str_starts_with(strtolower($query->sql), 'insert')) {
                $writes[] = count($query->bindings);
            }
        });

        try {
            $this->seed(TranslationStringSeeder::class);
            $this->seed(TranslationStringSeeder::class);

            self::assertNotEmpty($writes);
            // Five columns per row: even a large existing catalog must use
            // bounded statements instead of one catalog-sized SQL query.
            self::assertLessThanOrEqual(500, max($writes));
            self::assertSame(($keyCount + 1) * count(config('localization.supported')), DB::table('translation_strings')->count());
            self::assertSame('Saved translation', $saved->fresh()->text);
            self::assertSame($savedTimestamp, $saved->fresh()->updated_at->toDateTimeString());
            self::assertTrue($deleted->fresh()->trashed());
            self::assertDatabaseHas('translation_strings', ['key' => $keys[$keyCount - 1], 'locale' => 'nl', 'text' => null]);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_large_json_catalog_is_synced_in_bounded_batches(): void
    {
        $directory = sys_get_temp_dir().'/translation-json-batches-'.Str::uuid();
        File::makeDirectory($directory);
        $this->app->useLangPath($directory);
        $translations = [];
        for ($index = 0; $index < 251; $index++) {
            $translations['Source '.$index] = 'Text '.$index;
        }
        File::put($directory.'/en.json', json_encode($translations));
        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (str_contains($query->sql, 'translation_strings') && str_starts_with(strtolower($query->sql), 'insert')) {
                $writes[] = count($query->bindings);
            }
        });

        try {
            self::assertSame(251, app(TranslationCatalogService::class)->syncFromJsonFiles());
            self::assertLessThanOrEqual(500, max($writes));
            self::assertDatabaseHas('translation_strings', ['key' => 'Source 250', 'locale' => 'en', 'text' => 'Text 250']);
            self::assertDatabaseHas('translation_strings', ['key' => 'Source 250', 'locale' => 'fr', 'text' => null]);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
