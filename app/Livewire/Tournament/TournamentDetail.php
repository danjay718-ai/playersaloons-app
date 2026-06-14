<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Modules\Tournament\Actions\CheckinParticipantAction;
use App\Modules\Tournament\Actions\RegisterForTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentCheckin;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\CheckinStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TournamentDetail extends Component
{
    public string $uuid;

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    private function getActiveTournamentQuery()
    {
        return Tournament::query()->whereIn('status', [
            TournamentStatus::REGISTRATION_OPEN->value,
            TournamentStatus::REGISTRATION_CLOSED->value,
            TournamentStatus::CHECKIN_OPEN->value,
            TournamentStatus::CHECKIN_CLOSED->value,
            TournamentStatus::BRACKET_GENERATED->value,
            TournamentStatus::ONGOING->value,
        ]);
    }

    public function register(RegisterForTournamentAction $action)
    {
        if (! Auth::check()) {
            return redirect()->to('/login');
        }

        if (! Auth::user()->hasRole('PLAYER')) {
            session()->flash('error', 'Only players can join tournaments.');
            return;
        }

        $tournament = $this->getActiveTournamentQuery()->where('uuid', $this->uuid)->firstOrFail();
        $user = Auth::user();

        try {
            $action->execute($tournament, $user);
            session()->flash('message', 'Successfully registered for this tournament!');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function checkin(CheckinParticipantAction $action)
    {
        if (! Auth::check()) {
            return redirect()->to('/login');
        }

        $tournament = $this->getActiveTournamentQuery()->where('uuid', $this->uuid)->firstOrFail();
        $user = Auth::user();

        try {
            $action->execute($tournament, $user);
            session()->flash('message', 'Successfully checked in!');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $tournament = $this->getActiveTournamentQuery()
            ->where('uuid', $this->uuid)
            ->with([
                'game.translations',
                'registrations' => function ($q) {
                    $q->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])
                        ->with('user.profile');
                },
                'brackets.rounds.matches.playerARegistration.user',
                'brackets.rounds.matches.playerBRegistration.user',
                'brackets.rounds.matches.winnerRegistration.user',
            ])
            ->firstOrFail();

        $user = Auth::user();
        $isRegistered = false;
        $isCheckedIn = false;
        $userRegistration = null;

        if ($user) {
            $userRegistration = TournamentRegistration::query()
                ->where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->whereNotIn('status', [RegistrationStatus::CANCELLED, RegistrationStatus::REFUNDED])
                ->first();

            if ($userRegistration) {
                $isRegistered = true;

                $isCheckedIn = TournamentCheckin::query()
                    ->where('registration_id', $userRegistration->id)
                    ->where('status', CheckinStatus::CHECKED_IN)
                    ->exists();
            }
        }

        // Bracket rounds sorted
        $rounds = collect();
        if ($tournament->brackets->isNotEmpty()) {
            $rounds = $tournament->brackets->first()->rounds()->orderBy('round_number')->get();
        }

        return view('livewire.tournament.tournament-detail', [
            'tournament' => $tournament,
            'isRegistered' => $isRegistered,
            'isCheckedIn' => $isCheckedIn,
            'userRegistration' => $userRegistration,
            'rounds' => $rounds,
        ])->layout('components.layouts.dashboard', ['title' => $tournament->name.' | PlayerSaloons', 'dashboard_title' => 'TOURNAMENT DETAILS']);
    }
}
