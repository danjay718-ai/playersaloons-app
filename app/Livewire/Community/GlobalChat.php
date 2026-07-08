<?php

declare(strict_types=1);

namespace App\Livewire\Community;

use Livewire\Component;

class GlobalChat extends Component
{
    public function render()
    {
        return view('livewire.community.global-chat')->layout('components.layouts.dashboard', [
            'title' => 'Comms Hub | PlayerSaloons',
            'dashboard_title' => 'COMMS HUB',
        ]);
    }
}
