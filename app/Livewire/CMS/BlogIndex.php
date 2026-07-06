<?php

declare(strict_types=1);

namespace App\Livewire\CMS;

use App\Modules\CMS\Models\CmsPage;
use Livewire\Component;

class BlogIndex extends Component
{
    public function render()
    {
        $posts = CmsPage::query()
            ->with('translations')
            ->where('type', 'blog')
            ->whereNotNull('published_at')
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->get();

        return view('livewire.cms.article-index', [
            'articles' => $posts,
            'type' => 'blog',
            'label' => 'Blog',
            'title' => 'Blog',
            'intro' => 'Platform updates, competitive guides, and behind-the-scenes notes from PlayerSaloons.',
        ])->layout('components.layouts.landing', [
            'title' => 'Blog | PlayerSaloons',
        ]);
    }
}
