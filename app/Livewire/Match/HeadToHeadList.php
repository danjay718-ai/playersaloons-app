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
use App\Modules\Wallet\Exceptions\InsufficientBalanceException;
use App\Shared\Enums\HeadToHeadChallengeStatus;
use App\Shared\Enums\HeadToHeadMatchStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class HeadToHeadList extends Component
{
    use WithFileUploads;

    public float $stakeAmount = 10.00;

    public string $gameId = '';

    public string $platformId = '';

    public string $gameHandle = '';

    public string $region = '';

    public string $matchTimerMinutes = '30';

    public string $activeTab = 'open';

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

        try {
            $action->execute($this->user(), $this->challengePayload());
            session()->flash('h2h_status', 'Challenge posted and stake locked.');
        } catch (Throwable $e) {
            $this->flashH2HError($e);
        }
    }

    public function findDuel(HeadToHeadMatchmakerService $matchmaker, AcceptHeadToHeadChallengeAction $accept): void
    {
        $this->validateChallengeInput();

        try {
            $challenge = $matchmaker->findOpponentChallenge(
                (int) $this->user()->getKey(),
                (int) $this->gameId,
                $this->stakeAmount,
                $this->platformId !== '' ? (int) $this->platformId : null,
                $this->region !== '' ? $this->region : null
            );

            if (! $challenge) {
                app(CreateHeadToHeadChallengeAction::class)->execute($this->user(), $this->challengePayload());
                session()->flash('h2h_status', 'No matching duel found. Your challenge is now waiting.');

                return;
            }

            $accept->execute($challenge, $this->user(), $this->gameHandle, (int) $this->gameId);
            session()->flash('h2h_status', 'Duel matched. Game handles are now visible.');
        } catch (Throwable $e) {
            $this->flashH2HError($e);
        }
    }

    public function acceptChallenge(int $challengeId, AcceptHeadToHeadChallengeAction $action): void
    {
        $this->validate([
            'gameHandle' => ['required', 'string', 'max:100'],
        ]);

        try {
            $challenge = HeadToHeadChallenge::query()->findOrFail($challengeId);
            $action->execute($challenge, $this->user(), $this->gameHandle, (int) $this->gameId);
            session()->flash('h2h_status', 'Challenge accepted and stake locked.');
        } catch (Throwable $e) {
            $this->flashH2HError($e);
        }
    }

    public function cancelChallenge(int $challengeId, CancelHeadToHeadChallengeAction $action): void
    {
        try {
            $challenge = HeadToHeadChallenge::query()->findOrFail($challengeId);
            $action->execute($challenge, $this->user());
            session()->flash('h2h_status', 'Challenge cancelled and stake refunded.');
        } catch (Throwable $e) {
            $this->flashH2HError($e);
        }
    }

    public function submitResult(int $matchId, SubmitHeadToHeadResultAction $action): void
    {
        $this->validate([
            'resultWinnerUserId' => ['required', 'integer'],
            'resultNotes' => ['nullable', 'string', 'max:1000'],
            'resultProof' => ['nullable', 'image', 'max:4096'],
        ]);

        try {
            $match = HeadToHeadMatch::query()->findOrFail($matchId);
            $action->execute($match, $this->user(), (int) $this->resultWinnerUserId, $this->resultNotes ?: null, $this->resultProof);

            $this->reset('resultNotes', 'resultProof');
            session()->flash('h2h_status', 'Result submitted. Waiting for opponent confirmation.');
        } catch (Throwable $e) {
            $this->flashH2HError($e);
        }
    }

    public function confirmResult(int $matchId, ConfirmHeadToHeadResultAction $action): void
    {
        try {
            $match = HeadToHeadMatch::query()->findOrFail($matchId);
            $action->execute($match, $this->user());
            session()->flash('h2h_status', 'Result confirmed. Winner payout released.');
        } catch (Throwable $e) {
            $this->flashH2HError($e);
        }
    }

    public function disputeResult(int $matchId, DisputeHeadToHeadResultAction $action): void
    {
        $this->validate([
            'disputeNotes' => ['nullable', 'string', 'max:1000'],
            'disputeProof' => ['nullable', 'image', 'max:4096'],
        ]);

        try {
            $match = HeadToHeadMatch::query()->findOrFail($matchId);
            $action->execute($match, $this->user(), $this->disputeNotes ?: null, $this->disputeProof);

            $this->reset('disputeNotes', 'disputeProof');
            session()->flash('h2h_status', 'Result disputed. Stake remains locked for admin review.');
        } catch (Throwable $e) {
            $this->flashH2HError($e);
        }
    }

    public function render()
    {
        $user = $this->user();

        $waitingChallenges = collect();
        $historyMatches = collect();

        // ALWAYS load active matches to show the global badge across all games
        $activeMatches = HeadToHeadMatch::query()
            ->with(['creator', 'opponent', 'winner', 'resultSubmitter', 'disputer', 'game.translations', 'platform'])
            ->where(function ($query) use ($user) {
                $query->where('creator_user_id', $user->getKey())
                    ->orWhere('opponent_user_id', $user->getKey());
            })
            ->whereIn('status', [
                HeadToHeadMatchStatus::IN_PROGRESS,
                HeadToHeadMatchStatus::WAITING_FOR_CONFIRMATION,
                HeadToHeadMatchStatus::DISPUTED,
            ])
            ->latest()
            ->get();

        if ($this->activeTab === 'open') {
            $waitingChallenges = HeadToHeadChallenge::query()
                ->with(['creator', 'game.translations', 'platform'])
                ->where('status', HeadToHeadChallengeStatus::WAITING->value)
                ->where('game_id', (int) $this->gameId)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->latest()
                ->take(24)
                ->get();
        }

        if ($this->activeTab === 'history') {
            $historyMatches = HeadToHeadMatch::query()
                ->with(['creator', 'opponent', 'winner', 'resultSubmitter', 'disputer', 'game.translations', 'platform'])
                ->where('game_id', (int) $this->gameId)
                ->where(function ($query) use ($user) {
                    $query->where('creator_user_id', $user->getKey())
                        ->orWhere('opponent_user_id', $user->getKey());
                })
                ->whereIn('status', [
                    HeadToHeadMatchStatus::COMPLETED,
                    HeadToHeadMatchStatus::CANCELLED,
                    HeadToHeadMatchStatus::EXPIRED,
                ])
                ->latest()
                ->take(12)
                ->get();
        }

        return view('livewire.match.head-to-head-list', [
            'games' => Game::query()->with('translations')->where('is_active', true)->get(),
            'platforms' => Platform::query()->where('is_active', true)->get(),
            'waitingChallenges' => $waitingChallenges,
            'activeMatches' => $activeMatches,
            'historyMatches' => $historyMatches,
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

    /**
     * @return array{game_id:int, platform_id:int|null, stake_amount:float, creator_game_handle:string, region:string|null, match_timer_minutes:int|null}
     */
    private function challengePayload(): array
    {
        return [
            'game_id' => (int) $this->gameId,
            'platform_id' => $this->platformId !== '' ? (int) $this->platformId : null,
            'stake_amount' => $this->stakeAmount,
            'creator_game_handle' => $this->gameHandle,
            'region' => $this->region !== '' ? $this->region : null,
            'match_timer_minutes' => $this->matchTimerMinutes !== '' ? (int) $this->matchTimerMinutes : null,
        ];
    }

    private function flashH2HError(Throwable $e): void
    {
        $message = match (true) {
            $e instanceof InsufficientBalanceException => 'Insufficient wallet balance for this stake.',
            str_contains($e->getMessage(), 'does not have a wallet') => 'Wallet not found. Please open your Wallet page or contact support before joining H2H duels.',
            default => $e->getMessage(),
        };

        session()->flash('h2h_error', $message);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
