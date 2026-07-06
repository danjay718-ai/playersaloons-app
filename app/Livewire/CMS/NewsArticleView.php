<?php

declare(strict_types=1);

namespace App\Livewire\CMS;

use App\Modules\CMS\Models\CmsPage;
use Livewire\Component;

class NewsArticleView extends Component
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
            ->where('type', 'news')
            ->where('slug', $this->slug)
            ->whereNotNull('published_at')
            ->firstOrFail();

        return view('livewire.cms.article-view', [
            'article' => $article,
            'type' => 'news',
            'label' => 'News',
            'indexUrl' => '/news',
        ])->layout('components.layouts.landing', [
            'title' => $article->localizedTitle().' | PlayerSaloons News',
        ]);
    }
}
