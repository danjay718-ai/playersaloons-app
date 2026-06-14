<?php

declare(strict_types=1);

namespace App\Livewire\Stream;

use Livewire\Component;

class StreamList extends Component
{
    public function render()
    {
        return view('livewire.stream.stream-list')->layout('components.layouts.dashboard', [
            'title' => 'Streams | PlayerSaloons',
            'dashboard_title' => 'LIVE BROADCASTS',
        ]);
    }
}
