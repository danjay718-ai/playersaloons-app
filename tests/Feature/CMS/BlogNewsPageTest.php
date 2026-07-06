<?php

declare(strict_types=1);

namespace Tests\Feature\CMS;

use App\Livewire\Admin\CmsContentAdmin;
use App\Modules\CMS\Models\CmsPage;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Database\Seeders\LandingPageSeeder;
use Database\Seeders\PublicNavigationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BlogNewsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LandingPageSeeder::class);
        $this->seed(PublicNavigationSeeder::class);
    }

    public function test_admin_can_create_blog_post_from_inline_content_editor(): void
    {
        $admin = $this->adminUser();
        Storage::fake('public');

        Livewire::actingAs($admin)
            ->test(CmsContentAdmin::class)
            ->call('createContent', 'blog')
            ->set('pageType', 'blog')
            ->set('pageSlug', 'season-one-guide')
            ->set('pageTitle', 'Season One Guide')
            ->set('pageExcerpt', 'A quick guide for the first competitive season.')
            ->set('pageIsFeatured', true)
            ->set('pageContent', '<p>Learn how to join tournaments and cash out winnings.</p>')
            ->call('saveContent')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cms_pages', [
            'slug' => 'season-one-guide',
            'type' => 'blog',
            'is_featured' => true,
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('cms_page_translations', [
            'locale' => 'en',
            'title' => 'Season One Guide',
            'excerpt' => 'A quick guide for the first competitive season.',
            'content' => '<p>Learn how to join tournaments and cash out winnings.</p>',
        ]);
    }

    public function test_admin_content_page_exposes_only_blog_and_news_controls(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get('/admin/cms/content')
            ->assertOk()
            ->assertSee('Blog & News')
            ->assertSee('Content Library')
            ->assertSee('Click to upload image')
            ->assertSee('Blog')
            ->assertSee('News')
            ->assertDontSee('Games Catalog')
            ->assertDontSee('Landing Page</button>', false);
    }

    public function test_public_blog_and_news_pages_show_only_published_articles_for_that_type(): void
    {
        $blog = $this->article('blog', 'season-one-guide', 'Season One Guide', true);
        $news = $this->article('news', 'platform-launch', 'Platform Launch', true);
        $draft = $this->article('blog', 'draft-blog', 'Draft Blog', false);

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Season One Guide')
            ->assertDontSee('Platform Launch')
            ->assertDontSee('Draft Blog');

        $this->get('/news')
            ->assertOk()
            ->assertSee('Platform Launch')
            ->assertDontSee('Season One Guide');

        $this->get('/blog/'.$blog->slug)
            ->assertOk()
            ->assertSee('Season One Guide')
            ->assertSee('Published body for Season One Guide', false);

        $this->get('/news/'.$news->slug)
            ->assertOk()
            ->assertSee('Platform Launch');

        $this->get('/blog/'.$news->slug)->assertNotFound();
        $this->get('/blog/'.$draft->slug)->assertNotFound();
    }

    private function article(string $type, string $slug, string $title, bool $published): CmsPage
    {
        $page = CmsPage::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => $slug,
            'type' => $type,
            'featured_image_path' => '/images/articles/'.$slug.'.webp',
            'is_featured' => true,
            'published_at' => $published ? now() : null,
        ]);

        $page->translations()->create([
            'locale' => 'en',
            'title' => $title,
            'excerpt' => 'Excerpt for '.$title,
            'content' => '<p>Published body for '.$title.'</p>',
        ]);

        return $page;
    }

    private function adminUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $user->assignRole('ADMIN');

        return $user;
    }
}
