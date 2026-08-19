<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\CMS\Models\CmsPage;
use App\Modules\CMS\Models\CmsPageTranslation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class CmsContentAdmin extends AdminComponent
{
    use WithFileUploads;
    use WithPagination;

    public ?int $selectedPageId = null;

    public bool $isPageEdit = false;

    public string $pageSlug = '';

    public string $pageType = 'blog';

    public string $pageTitle = '';

    public string $pageExcerpt = '';

    public string $pageContent = '';

    public string $pageFeaturedImagePath = '';

    public bool $pageIsFeatured = false;

    public string $pageLocale = 'en';

    public ?TemporaryUploadedFile $featuredImage = null;

    protected $paginationTheme = 'tailwind';

    public function updatedPageTitle($value): void
    {
        if (! $this->isPageEdit) {
            $this->pageSlug = Str::slug($value);
        }
    }

    public function createContent(string $type = 'blog'): void
    {
        $this->resetEditor();
        $this->pageType = in_array($type, ['blog', 'news', 'page'], true) ? $type : 'blog';
        $this->dispatch('cms-content-selected', content: '');
    }

    public function editContent(int $id): void
    {
        $this->selectedPageId = $id;
        $this->isPageEdit = true;

        $page = CmsPage::findOrFail($id);
        /** @var CmsPageTranslation|null $translation */
        $translation = $page->translations()->where('locale', $this->pageLocale)->first();

        $this->pageSlug = $page->slug;
        $this->pageType = (string) $page->type;
        $this->pageTitle = $translation !== null ? $translation->title : '';
        $this->pageExcerpt = $translation !== null ? (string) $translation->excerpt : '';
        $this->pageContent = $translation !== null ? $translation->content : '';
        $this->pageFeaturedImagePath = (string) $page->featured_image_path;
        $this->pageIsFeatured = (bool) $page->is_featured;
        $this->featuredImage = null;

        $this->dispatch('cms-content-selected', content: $this->pageContent);
    }

    public function saveContent(): void
    {
        $this->validate([
            'pageSlug' => 'required|string|max:150|alpha_dash|unique:cms_pages,slug,'.$this->selectedPageId,
            'pageType' => 'required|string|in:page,blog,news',
            'pageTitle' => 'required|string|max:255',
            'pageExcerpt' => 'nullable|string|max:500',
            'pageContent' => 'required|string',
            'pageIsFeatured' => 'boolean',
            'featuredImage' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:width=800,height=500',
        ]);

        $actor = Auth::user();
        if (! $actor) {
            return;
        }

        $featuredImagePath = $this->pageFeaturedImagePath;
        if ($this->featuredImage) {
            $path = $this->featuredImage->store('articles', 'public');
            $featuredImagePath = '/storage/'.$path;
        }

        DB::transaction(function () use ($actor, $featuredImagePath): void {
            if ($this->isPageEdit && $this->selectedPageId) {
                $page = CmsPage::findOrFail($this->selectedPageId);
                $page->update([
                    'slug' => $this->pageSlug,
                    'type' => $this->pageType,
                    'featured_image_path' => $featuredImagePath !== '' ? $featuredImagePath : null,
                    'is_featured' => $this->pageIsFeatured,
                ]);
            } else {
                $page = CmsPage::create([
                    'uuid' => Str::uuid()->toString(),
                    'slug' => $this->pageSlug,
                    'type' => $this->pageType,
                    'featured_image_path' => $featuredImagePath !== '' ? $featuredImagePath : null,
                    'is_featured' => $this->pageIsFeatured,
                    'created_by' => $actor->id,
                ]);
                $this->selectedPageId = (int) $page->id;
                $this->isPageEdit = true;
            }

            CmsPageTranslation::updateOrCreate([
                'page_id' => $this->selectedPageId,
                'locale' => $this->pageLocale,
            ], [
                'title' => $this->pageTitle,
                'excerpt' => $this->pageExcerpt !== '' ? $this->pageExcerpt : null,
                'content' => $this->pageContent,
            ]);
        });

        $this->pageFeaturedImagePath = $featuredImagePath;
        $this->featuredImage = null;
        session()->flash('success', 'Content saved successfully.');
    }

    public function publishContent(int $id): void
    {
        CmsPage::findOrFail($id)->update(['published_at' => now()]);

        session()->flash('success', 'Content published successfully.');
    }

    public function unpublishContent(int $id): void
    {
        CmsPage::findOrFail($id)->update(['published_at' => null]);

        session()->flash('success', 'Content moved back to draft.');
    }

    public function deleteContent(int $id): void
    {
        $page = CmsPage::findOrFail($id);
        $page->delete();

        if ($this->selectedPageId === $id) {
            $this->resetEditor();
            $this->dispatch('cms-content-selected', content: '');
        }

        session()->flash('success', 'Content deleted.');
    }

    public function render()
    {
        $pages = CmsPage::query()
            ->with(['translations', 'creator'])
            ->latest('updated_at')
            ->paginate(12, ['*'], 'content_page');

        return view('livewire.admin.cms-content-admin', [
            'pages' => $pages,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Blog & News',
        ]);
    }

    private function resetEditor(): void
    {
        $this->reset([
            'selectedPageId',
            'isPageEdit',
            'pageSlug',
            'pageTitle',
            'pageExcerpt',
            'pageContent',
            'pageFeaturedImagePath',
            'pageIsFeatured',
            'featuredImage',
        ]);

        $this->pageLocale = 'en';
        $this->pageType = 'blog';
    }
}
