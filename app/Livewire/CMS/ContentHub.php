<?php

declare(strict_types=1);

namespace App\Livewire\CMS;

use App\Modules\CMS\Models\CmsPage;
use Livewire\Component;

class ContentHub extends Component
{
    public function render()
    {
        $articles = CmsPage::query()
            ->with(['translations', 'creator'])
            ->whereIn('type', ['blog', 'news'])
            ->whereNotNull('published_at')
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->get();

        return view('livewire.cms.content-hub', [
            'articles' => $articles,
        ])->layout('components.layouts.landing', [
            'title' => 'Updates | GamersRival',
        ]);
    }
}
