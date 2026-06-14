<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class MyTournamentsList extends Component
{
    use WithPagination;

    public string $tSubTab = 'active'; // active or history

    protected $queryString = [
        'tSubTab' => ['except' => 'active'],
    ];

    public function render()
    {
        $user = Auth::user();

        $query = Tournament::query()
            ->whereHas('registrations', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]);
            })
            ->with('game.translations');

        if ($this->tSubTab === 'active') {
            $query->whereNotIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value]);
        } else {
            $query->whereIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value]);
        }

        return view('livewire.tournament.my-tournaments-list', [
            'tournaments' => $query->orderBy('created_at', 'desc')->paginate(10),
        ])->layout('components.layouts.dashboard', ['title' => 'My Tournaments | PlayerSaloons', 'dashboard_title' => 'MY TOURNAMENTS']);
    }
}
