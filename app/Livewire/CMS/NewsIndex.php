<?php

declare(strict_types=1);

namespace App\Livewire\CMS;

use App\Modules\CMS\Models\CmsPage;
use Livewire\Component;

class NewsIndex extends Component
{
    public function render()
    {
        $posts = CmsPage::query()
            ->with('translations')
            ->where('type', 'news')
            ->whereNotNull('published_at')
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->get();

        return view('livewire.cms.article-index', [
            'articles' => $posts,
            'type' => 'news',
            'label' => 'News',
            'title' => 'News',
            'intro' => 'Official announcements, release notes, and operational updates for the PlayerSaloons platform.',
        ])->layout('components.layouts.landing', [
            'title' => 'News | PlayerSaloons',
        ]);
    }
}
