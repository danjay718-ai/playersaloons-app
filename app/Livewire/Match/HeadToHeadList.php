<?php

declare(strict_types=1);

namespace App\Livewire\Match;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\AcceptHeadToHeadChallengeAction;
use App\Modules\Match\Actions\CancelHeadToHeadChallengeAction;
use App\Modules\Match\Actions\ConfirmHeadToHeadResultAction;
use App\Modules\Match\Actions\CreateHeadToHeadChallengeAction;
use App\Modules\Match\Actions\DisputeHeadToHeadResultAction;
use App\Modules\Match\Actions\SubmitHeadToHeadResultAction;
use App\Modules\Match\Models\HeadToHeadChallenge;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Match\Services\HeadToHeadMatchmakerService;
use App\Shared\Enums\HeadToHeadChallengeStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class HeadToHeadList extends Component
{
    use WithFileUploads;

    public float $stakeAmount = 10.00;

    public string $gameId = '';

    public string $platformId = '';

    public string $gameHandle = '';

    public string $region = '';

    public string $matchTimerMinutes = '30';

    public ?int $resultWinnerUserId = null;

    public string $resultNotes = '';

    public ?TemporaryUploadedFile $resultProof = null;

    public string $disputeNotes = '';

    public ?TemporaryUploadedFile $disputeProof = null;

    public function mount(): void
    {
        $this->gameId = (string) Game::query()->where('is_active', true)->value('id');
    }

    public function createChallenge(CreateHeadToHeadChallengeAction $action): void
    {
        $this->validateChallengeInput();

        $action->execute($this->user(), [
            'game_id' => (int) $this->gameId,
            'platform_id' => $this->platformId !== '' ? (int) $this->platformId : null,
            'stake_amount' => $this->stakeAmount,
            'creator_game_handle' => $this->gameHandle,
            'region' => $this->region !== '' ? $this->region : null,
            'match_timer_minutes' => $this->matchTimerMinutes !== '' ? (int) $this->matchTimerMinutes : null,
        ]);

        session()->flash('h2h_status', 'Challenge posted and stake locked.');
    }

    public function findDuel(HeadToHeadMatchmakerService $matchmaker, AcceptHeadToHeadChallengeAction $accept): void
    {
        $this->validateChallengeInput();

        $challenge = $matchmaker->findOpponentChallenge(
            (int) $this->user()->getKey(),
            (int) $this->gameId,
            $this->stakeAmount,
            $this->platformId !== '' ? (int) $this->platformId : null,
            $this->region !== '' ? $this->region : null
        );

        if (! $challenge) {
            $this->createChallenge(app(CreateHeadToHeadChallengeAction::class));
            session()->flash('h2h_status', 'No matching duel found. Your challenge is now waiting.');

            return;
        }

        $accept->execute($challenge, $this->user(), $this->gameHandle);
        session()->flash('h2h_status', 'Duel matched. Game handles are now visible.');
    }

    public function acceptChallenge(int $challengeId, AcceptHeadToHeadChallengeAction $action): void
    {
        $this->validate([
            'gameHandle' => ['required', 'string', 'max:100'],
        ]);

        $challenge = HeadToHeadChallenge::query()->findOrFail($challengeId);
        $action->execute($challenge, $this->user(), $this->gameHandle);

        session()->flash('h2h_status', 'Challenge accepted and stake locked.');
    }

    public function cancelChallenge(int $challengeId, CancelHeadToHeadChallengeAction $action): void
    {
        $challenge = HeadToHeadChallenge::query()->findOrFail($challengeId);
        $action->execute($challenge, $this->user());

        session()->flash('h2h_status', 'Challenge cancelled and stake refunded.');
    }

    public function submitResult(int $matchId, SubmitHeadToHeadResultAction $action): void
    {
        $this->validate([
            'resultWinnerUserId' => ['required', 'integer'],
            'resultNotes' => ['nullable', 'string', 'max:1000'],
            'resultProof' => ['nullable', 'image', 'max:4096'],
        ]);

        $match = HeadToHeadMatch::query()->findOrFail($matchId);
        $action->execute($match, $this->user(), (int) $this->resultWinnerUserId, $this->resultNotes ?: null, $this->resultProof);

        $this->reset('resultNotes', 'resultProof');
        session()->flash('h2h_status', 'Result submitted. Waiting for opponent confirmation.');
    }

    public function confirmResult(int $matchId, ConfirmHeadToHeadResultAction $action): void
    {
        $match = HeadToHeadMatch::query()->findOrFail($matchId);
        $action->execute($match, $this->user());

        session()->flash('h2h_status', 'Result confirmed. Winner payout released.');
    }

    public function disputeResult(int $matchId, DisputeHeadToHeadResultAction $action): void
    {
        $this->validate([
            'disputeNotes' => ['nullable', 'string', 'max:1000'],
            'disputeProof' => ['nullable', 'image', 'max:4096'],
        ]);

        $match = HeadToHeadMatch::query()->findOrFail($matchId);
        $action->execute($match, $this->user(), $this->disputeNotes ?: null, $this->disputeProof);

        $this->reset('disputeNotes', 'disputeProof');
        session()->flash('h2h_status', 'Result disputed. Stake remains locked for admin review.');
    }

    public function render()
    {
        $user = $this->user();

        $waitingChallenges = HeadToHeadChallenge::query()
            ->with(['creator', 'game.translations', 'platform'])
            ->where('status', HeadToHeadChallengeStatus::WAITING->value)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->take(12)
            ->get();

        $myMatches = HeadToHeadMatch::query()
            ->with(['creator', 'opponent', 'winner', 'resultSubmitter', 'disputer', 'game.translations', 'platform'])
            ->where(function ($query) use ($user) {
                $query->where('creator_user_id', $user->getKey())
                    ->orWhere('opponent_user_id', $user->getKey());
            })
            ->latest()
            ->take(8)
            ->get();

        return view('livewire.match.head-to-head-list', [
            'games' => Game::query()->with('translations')->where('is_active', true)->get(),
            'platforms' => Platform::query()->where('is_active', true)->get(),
            'waitingChallenges' => $waitingChallenges,
            'myMatches' => $myMatches,
        ])->layout('components.layouts.dashboard', [
            'title' => 'Head-to-Head | PlayerSaloons',
            'dashboard_title' => 'HEAD-TO-HEAD DUELS',
        ]);
    }

    private function validateChallengeInput(): void
    {
        $this->validate([
            'gameId' => ['required', 'integer', 'exists:games,id'],
            'platformId' => ['nullable', 'integer', 'exists:platforms,id'],
            'stakeAmount' => ['required', 'numeric', 'min:1', 'max:1000'],
            'gameHandle' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:50'],
            'matchTimerMinutes' => ['nullable', 'integer', 'in:15,30,60'],
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
