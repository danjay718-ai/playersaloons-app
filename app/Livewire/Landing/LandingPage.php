<?php

declare(strict_types=1);

namespace App\Livewire\Landing;

use App\Modules\CMS\Services\LandingPageContentService;
use App\Modules\Community\Models\PlayerReview;
use Livewire\Component;

class LandingPage extends Component
{
    public function render(LandingPageContentService $content)
    {
        return view('livewire.landing.landing-page', array_merge($content->data(), [
            'playerReviews' => PlayerReview::query()->with('user.profile')->where('status', 'approved')->latest('moderated_at')->limit(6)->get(),
        ]))
            ->layout('components.layouts.landing', [
                'title' => 'PlayerSaloons | Play. Win. Cash.',
            ]);
    }
}
