<?php

declare(strict_types=1);

namespace App\Livewire\Match;

use Livewire\Component;

class LeaderboardList extends Component
{
    public function render()
    {
        return view('livewire.match.leaderboard-list', [
            'topPlayers' => [
                ['rank' => 1, 'name' => 'SaloonsKing', 'level' => 64, 'wins' => 142, 'losses' => 28, 'cash' => '$1,820.00', 'tier' => 'Challenger', 'avatar' => 'SK'],
                ['rank' => 2, 'name' => 'ViperZero', 'level' => 58, 'wins' => 128, 'losses' => 35, 'cash' => '$1,540.00', 'tier' => 'Diamond', 'avatar' => 'VZ'],
            ]
        ])->layout('components.layouts.dashboard', [
            'title' => 'Leaderboards | PlayerSaloons',
            'dashboard_title' => 'GLOBAL LEADERBOARDS',
        ]);
    }
}
