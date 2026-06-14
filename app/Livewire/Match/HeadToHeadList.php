<?php

declare(strict_types=1);

namespace App\Livewire\Match;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class HeadToHeadList extends Component
{
    // Head-to-Head properties
    public float $stakeAmount = 10.00;
    public string $selectedGame = 'Valorant';
    public array $challenges = [];
    public bool $isSearching = false;
    public ?array $matchedOpponent = null;

    public function mount()
    {
        // Initialize mock H2H challenges
        $this->challenges = [
            ['id' => 1, 'username' => 'ShadowBlade', 'game' => 'CS2', 'stake' => 15.00, 'avatar' => 'SB', 'status' => 'waiting'],
            ['id' => 2, 'username' => 'AlphaKnight', 'game' => 'FIFA 24', 'stake' => 25.00, 'avatar' => 'AK', 'status' => 'waiting'],
            ['id' => 3, 'username' => 'CyberPunk', 'game' => 'Tekken 8', 'stake' => 50.00, 'avatar' => 'CP', 'status' => 'waiting'],
        ];
    }

    public function createChallenge(): void
    {
        $user = Auth::user();
        if ($this->stakeAmount <= 0) {
            return;
        }

        $this->challenges[] = [
            'id' => count($this->challenges) + 1,
            'username' => $user->username,
            'game' => $this->selectedGame,
            'stake' => $this->stakeAmount,
            'avatar' => strtoupper(substr($user->username, 0, 2)),
            'status' => 'waiting',
        ];
    }

    public function findDuel(): void
    {
        $this->isSearching = true;
        $this->matchedOpponent = null;
    }

    public function cancelSearch(): void
    {
        $this->isSearching = false;
        $this->matchedOpponent = null;
    }

    public function simulateMatchFound(): void
    {
        $this->isSearching = false;
        $opponents = [
            ['username' => 'ViperZero', 'level' => 45, 'winrate' => '68%', 'game' => 'Valorant', 'avatar' => 'VZ'],
            ['username' => 'ShadowBlade', 'level' => 52, 'winrate' => '71%', 'game' => 'CS2', 'avatar' => 'SB'],
            ['username' => 'NeonSpecter', 'level' => 38, 'winrate' => '62%', 'game' => 'FIFA 24', 'avatar' => 'NS'],
        ];

        $this->matchedOpponent = $opponents[array_rand($opponents)];
    }

    public function render()
    {
        return view('livewire.match.head-to-head-list')->layout('components.layouts.dashboard', [
            'title' => 'Head-to-Head | PlayerSaloons',
            'dashboard_title' => 'HEAD-TO-HEAD DUELS',
        ]);
    }
}
