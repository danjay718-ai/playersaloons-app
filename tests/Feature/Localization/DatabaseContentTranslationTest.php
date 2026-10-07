<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Modules\CMS\Models\PolicyPage;
use App\Modules\Localization\Models\TranslationString;
use App\Modules\Localization\Services\TranslationCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DatabaseContentTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_synced_database_copy_uses_existing_translator_and_preserves_markup(): void
    {
        $directory = sys_get_temp_dir().'/database-translations-'.Str::uuid();
        File::makeDirectory($directory);
        $this->app->useLangPath($directory);
        $this->app->forgetInstance('translation.loader');
        $this->app->forgetInstance('translator');

        try {
            PolicyPage::query()->create([
                'uuid' => Str::uuid()->toString(),
                'slug' => 'test-policy',
                'title' => 'Accounts & payments',
                'summary' => 'Account terms summary.',
                'content' => '<p>Keep your account safe.</p><p><strong>Payment rules</strong></p><code>Untranslated example</code>',
                'is_active' => true,
                'published_at' => now(),
            ]);

            $catalog = app(TranslationCatalogService::class);
            $this->assertGreaterThan(0, $catalog->syncFromDatabaseContent());
            foreach ([
                'Accounts & payments' => 'Comptes et paiements',
                'Account terms summary.' => 'Résumé des conditions du compte.',
                'Keep your account safe.' => 'Protégez votre compte.',
                'Payment rules' => 'Règles de paiement',
            ] as $key => $text) {
                $catalog->saveTranslation($key, 'fr', $text);
            }
            $this->assertSame(0, $catalog->syncFromDatabaseContent());
            $this->assertDatabaseHas('translation_strings', [
                'key' => 'Payment rules', 'locale' => 'fr', 'text' => 'Règles de paiement',
            ]);
            $this->assertDatabaseMissing('translation_strings', ['key' => 'Untranslated example']);
            $catalog->exportJsonFiles();

            $this->withSession(['locale' => 'fr'])->get('/policies/test-policy')
                ->assertOk()
                ->assertSee('Comptes et paiements')
                ->assertSee('Résumé des conditions du compte.')
                ->assertSee('<p>Protégez votre compte.</p>', false)
                ->assertSee('<strong>Règles de paiement</strong>', false)
                ->assertSee('<code>Untranslated example</code>', false)
                ->assertDontSee('Keep your account safe.');

            $this->withSession(['locale' => 'en'])->get('/policies/test-policy')
                ->assertOk()->assertSee('Accounts &amp; payments', false)
                ->assertSee('Keep your account safe.');
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_sync_does_not_restore_deleted_phrases(): void
    {
        PolicyPage::query()->create([
            'uuid' => Str::uuid()->toString(), 'slug' => 'test-policy',
            'title' => 'Deleted phrase', 'content' => '<p>Policy body.</p>',
        ]);
        $catalog = app(TranslationCatalogService::class);
        $catalog->syncFromDatabaseContent();
        TranslationString::query()->where('key', 'Deleted phrase')->delete();
        $catalog->syncFromDatabaseContent();

        $this->assertSame(0, TranslationString::query()->where('key', 'Deleted phrase')->count());
    }
}
