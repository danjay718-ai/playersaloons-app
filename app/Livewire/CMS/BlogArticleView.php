<?php

declare(strict_types=1);

namespace App\Livewire\CMS;

use App\Modules\CMS\Models\CmsPage;
use Livewire\Component;

class BlogArticleView extends Component
{
    public string $slug;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function render()
    {
        $article = CmsPage::query()
            ->with(['translations', 'creator'])
            ->where('type', 'blog')
            ->where('slug', $this->slug)
            ->whereNotNull('published_at')
            ->firstOrFail();

        return view('livewire.cms.article-view', [
            'article' => $article,
            'type' => 'blog',
            'label' => 'Blog',
            'indexUrl' => '/blog',
        ])->layout('components.layouts.landing', [
            'title' => $article->localizedTitle().' | PlayerSaloons Blog',
        ]);
    }
}
