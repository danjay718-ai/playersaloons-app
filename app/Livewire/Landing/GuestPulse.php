<?php

declare(strict_types=1);

namespace App\Livewire\Landing;

use App\Modules\CMS\Services\GuestCompetitionService;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GuestPulse extends Component
{
    #[Locked]
    public string $placement = 'header';

    public function render(GuestCompetitionService $competitions)
    {
        $view = $this->placement === 'activity' && config('features.landing.version') !== 'v3'
            ? 'livewire.landing.guest-pulse-v4'
            : 'livewire.landing.guest-pulse';

        return view($view, [
            'summary' => $this->placement === 'header' ? $competitions->openSummary() : null,
            'activity' => $this->placement === 'activity' ? collect($competitions->activity()) : collect(),
            'winSummary' => $this->placement === 'activity' ? $competitions->winSummary() : null,
        ]);
    }
}
