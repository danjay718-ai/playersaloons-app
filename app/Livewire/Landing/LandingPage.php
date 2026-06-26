<?php

declare(strict_types=1);

namespace App\Livewire\Landing;

use App\Modules\CMS\Services\LandingPageContentService;
use Livewire\Component;

class LandingPage extends Component
{
    public function render(LandingPageContentService $content)
    {
        return view('livewire.landing.landing-page', $content->data())
            ->layout('components.layouts.landing', [
                'title' => 'PlayerSaloons | Play. Win. Cash.',
            ]);
    }
}
