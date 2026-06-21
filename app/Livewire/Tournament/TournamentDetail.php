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
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

class TournamentDetail extends Component
{
    public string $uuid;

    public string $layout = 'components.layouts.dashboard';

    #[Url]
    public string $activeTab = 'overview';

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        
        $user = Auth::user();
        if ($user && $user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER'])) {
            $this->layout = 'components.layouts.admin';
        } else {
            $this->layout = 'components.layouts.dashboard';
        }
    }

    private function getTournamentQuery()
    {
        if (Auth::check() && Auth::user()->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER'])) {
            return Tournament::query();
        }

        return Tournament::query()->where('status', '!=', TournamentStatus::DRAFT->value);
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

        $tournament = $this->getTournamentQuery()->where('uuid', $this->uuid)->firstOrFail();
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

        $tournament = $this->getTournamentQuery()->where('uuid', $this->uuid)->firstOrFail();
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
        $tournament = $this->getTournamentQuery()
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

        $hasLost = false;
        if ($user && $userRegistration) {
            $hasLost = \App\Modules\Match\Models\GameMatch::where('tournament_id', $tournament->id)
                ->where(function ($query) use ($userRegistration) {
                    $query->where('player_a_registration_id', $userRegistration->id)
                          ->orWhere('player_b_registration_id', $userRegistration->id);
                })
                ->whereIn('status', [\App\Shared\Enums\MatchStatus::COMPLETED, \App\Shared\Enums\MatchStatus::FORFEITED])
                ->whereNotNull('winner_registration_id')
                ->where('winner_registration_id', '!=', $userRegistration->id)
                ->exists();
        }

        // Bracket rounds sorted
        $rounds = collect();
        if ($tournament->brackets->isNotEmpty()) {
            $rounds = $tournament->brackets->first()->rounds()->orderBy('round_number')->get();
        }

        $activityLogs = Activity::query()
            ->where('subject_type', Tournament::class)
            ->where('subject_id', $tournament->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.tournament.tournament-detail', [
            'tournament' => $tournament,
            'isRegistered' => $isRegistered,
            'isCheckedIn' => $isCheckedIn,
            'userRegistration' => $userRegistration,
            'rounds' => $rounds,
            'activityLogs' => $activityLogs,
            'hasLost' => $hasLost,
        ])->layout($this->layout, ['title' => $tournament->name.' | PlayerSaloons', 'dashboard_title' => 'TOURNAMENT DETAILS']);
    }
}
