<?php

declare(strict_types=1);

namespace App\Livewire\CMS;

use Livewire\Component;

class ContentHub extends Component
{
    public function render()
    {
        return view('livewire.cms.content-hub')
            ->layout('components.layouts.landing', [
                'title' => 'Updates | PlayerSaloons',
            ]);
    }
}
