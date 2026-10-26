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
        return view('livewire.landing.guest-pulse', [
            'summary' => $this->placement === 'header' ? $competitions->openSummary() : null,
            'activity' => $this->placement === 'activity' ? collect($competitions->activity()) : collect(),
        ]);
    }
}
