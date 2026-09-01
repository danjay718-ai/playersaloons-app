<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserGameAccount;
use App\Modules\Team\Models\Team;
use App\Modules\Tournament\Events\TournamentFilled;
use App\Modules\Tournament\Events\TournamentSeatReserved;
use App\Modules\Tournament\Exceptions\TournamentAlreadyRegisteredException;
use App\Modules\Tournament\Exceptions\TournamentFullException;
use App\Modules\Tournament\Exceptions\TournamentNotOpenForRegistrationException;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Tournament\Models\TournamentRegistrationMember;
use App\Modules\Tournament\Models\TournamentTeam;
use App\Modules\Tournament\Models\TournamentTeamMember;
use App\Modules\Wallet\Exceptions\InsufficientBalanceException;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Support\DecimalMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterForTournamentAction
{
    public function __construct(private readonly WalletService $walletService) {}

    /**
     * Register a user for a tournament, collecting entry fee if applicable.
     *
     * @throws TournamentNotOpenForRegistrationException
     * @throws TournamentAlreadyRegisteredException
     * @throws TournamentFullException
     * @throws InsufficientBalanceException
     */
    public function execute(
        Tournament $tournament,
        User $user,
        ?Team $team = null,
        ?string $gameIdValue = null,
        string $readyMode = 'auto',
        ?TournamentTeam $tournamentTeam = null,
    ): TournamentRegistration {
        if ($tournament->status !== TournamentStatus::REGISTRATION_OPEN) {
            throw new TournamentNotOpenForRegistrationException(
                $tournament->name,
                $tournament->status->value
            );
        }

        $gameIdValue = trim((string) ($gameIdValue ?: $user->username));
        if ($gameIdValue === '' || mb_strlen($gameIdValue) > 191) {
            throw new \LogicException('Enter a valid Game ID / In-Game Name.');
        }
        if (! in_array($readyMode, ['auto', 'confirm_each_match'], true)) {
            throw new \LogicException('Choose a valid match readiness option.');
        }

        return DB::transaction(function () use ($tournament, $user, $team, $gameIdValue, $readyMode, $tournamentTeam): TournamentRegistration {
            // Lock tournament row to prevent race conditions on participant count
            /** @var Tournament|null $locked */
            $locked = Tournament::query()->where('id', $tournament->getKey())->lockForUpdate()->first();
            if ($locked === null) {
                throw new \RuntimeException('Tournament not found.');
            }

            if (($locked->team_size ?? 1) > 1) {
                if ($tournamentTeam !== null) {
                    $tournamentTeam = TournamentTeam::query()->with('members')->lockForUpdate()->findOrFail($tournamentTeam->getKey());
                    if ((int) $tournamentTeam->tournament_id !== (int) $locked->getKey()
                        || (int) $tournamentTeam->leader_user_id !== (int) $user->getKey()
                        || $tournamentTeam->status !== 'ready'
                        || $tournamentTeam->members->count() !== (int) $locked->team_size) {
                        throw new \LogicException('This tournament team is not ready for registration.');
                    }
                } elseif ($team !== null) {
                    if ($team->status !== 'active' || (int) $team->captain_user_id !== (int) $user->getKey()) {
                        throw new \LogicException('Only the Squad Leader (captain) may create its tournament team.');
                    }
                    if ($team->members()->where('status', 'active')->count() < (int) $locked->team_size) {
                        throw new \LogicException("Your squad needs at least {$locked->team_size} active members.");
                    }
                    if (TournamentRegistration::query()->where('tournament_id', $locked->getKey())->where('team_id', $team->getKey())->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])->exists()) {
                        throw new \LogicException('This squad already has a registered tournament team.');
                    }
                    $tournamentTeam = $this->snapshotSquadLineup($locked, $team, $user, $gameIdValue, $readyMode);
                } else {
                    throw new \LogicException('Form a tournament team or use Find a Team before registering.');
                }
            } elseif ($team !== null) {
                throw new \LogicException('Teams cannot register for a solo tournament.');
            }

            // Check for duplicate registration
            $existing = TournamentRegistration::query()
                ->where('tournament_id', $locked->getKey())
                ->where('user_id', $user->getKey())
                ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])
                ->first();

            if ($existing instanceof TournamentRegistration) {
                throw new TournamentAlreadyRegisteredException((int) $user->getKey(), (int) $locked->getKey());
            }

            // Check capacity
            $activeCount = TournamentRegistration::query()
                ->where('tournament_id', $locked->getKey())
                ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])
                ->count();
            $registrationAttempt = TournamentRegistration::query()
                ->where('tournament_id', $locked->getKey())
                ->where('user_id', $user->getKey())
                ->count() + 1;

            if ($activeCount >= ($locked->max_participants ?? 0)) {
                throw new TournamentFullException($locked->name, $locked->max_participants ?? 0);
            }

            $entryFee = (string) ($locked->entry_fee ?? '0.00');
            $isFree = DecimalMoney::toMinor($entryFee) <= 0;
            $paymentStatus = $isFree ? PaymentStatus::FREE : PaymentStatus::PAID;

            // Collect entry fee via wallet if applicable
            if (! $isFree) {
                /** @var Wallet|null $wallet */
                $wallet = $user->wallet;
                if ($wallet === null) {
                    throw new \RuntimeException('User does not have a wallet.');
                }

                $this->walletService->debit(
                    $wallet,
                    $entryFee,
                    LedgerType::ENTRY_FEE,
                    TournamentRegistration::class,
                    (string) $locked->getKey(),
                    "Entry fee for tournament: {$locked->name}",
                    "tournament-entry:{$locked->id}:user:{$user->id}:attempt:{$registrationAttempt}",
                );
            }

            // The schema preserves one registration identity per tournament and
            // player. A cancelled player re-enters by reactivating that audit
            // row; each payment/refund cycle still has a distinct idempotency
            // key via registrationAttempt.
            $registrationValues = [
                'tournament_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
                'team_id' => $team?->getKey(),
                'tournament_team_id' => $tournamentTeam?->getKey(),
                'game_id_value' => $gameIdValue,
                'ready_mode' => $tournamentTeam?->members->every(fn ($member) => $member->ready_mode === 'auto') ? 'auto' : $readyMode,
                'status' => RegistrationStatus::CONFIRMED,
                'payment_status' => $paymentStatus,
                'registered_at' => now(),
                'locked_at' => $locked->extra_registration_started_at !== null ? now() : null,
            ];
            $historicalRegistration = TournamentRegistration::query()
                ->where('tournament_id', $locked->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();
            if ($historicalRegistration !== null) {
                $historicalRegistration->fill($registrationValues);
                $historicalRegistration->save();
                $registration = $historicalRegistration;
            } else {
                $registration = TournamentRegistration::query()->create([
                    'uuid' => Str::uuid()->toString(),
                    ...$registrationValues,
                ]);
            }

            if ($locked->platform_id !== null) {
                UserGameAccount::query()->updateOrCreate([
                    'user_id' => $user->getKey(),
                    'game_id' => $locked->game_id,
                    'platform_id' => $locked->platform_id,
                ], [
                    'game_id_value' => $gameIdValue,
                ]);
            }

            if ($tournamentTeam !== null) {
                $roster = $tournamentTeam->members;
                foreach ($roster as $member) {
                    TournamentRegistrationMember::query()->create([
                        'registration_id' => $registration->id,
                        'user_id' => $member->user_id,
                        'role' => $member->role,
                    ]);
                }
            }

            TournamentSeatReserved::dispatch((int) $locked->getKey(), (int) $registration->getKey(), (int) $user->getKey());

            // Check if tournament is now full
            $newCount = $activeCount + 1;
            if ($newCount >= ($locked->max_participants ?? 0)) {
                TournamentFilled::dispatch((int) $locked->getKey(), (int) ($locked->max_participants ?? 0));
            }

            return $registration;
        });
    }

    private function snapshotSquadLineup(
        Tournament $tournament,
        Team $squad,
        User $leader,
        string $leaderGameId,
        string $leaderReadyMode,
    ): TournamentTeam {
        $members = $squad->members()
            ->where('status', 'active')
            ->with('user:id,username')
            ->orderByRaw("CASE WHEN role = 'captain' THEN 0 WHEN role = 'co_captain' THEN 1 ELSE 2 END")
            ->limit((int) $tournament->team_size)
            ->get();

        $accountIds = UserGameAccount::query()
            ->whereIn('user_id', $members->pluck('user_id'))
            ->where('game_id', $tournament->game_id)
            ->where('platform_id', $tournament->platform_id)
            ->pluck('game_id_value', 'user_id');

        $team = TournamentTeam::query()->create([
            'uuid' => Str::uuid()->toString(),
            'tournament_id' => $tournament->getKey(),
            'source_team_id' => $squad->getKey(),
            'leader_user_id' => $leader->getKey(),
            'name' => $squad->name,
            'status' => 'ready',
        ]);

        TournamentTeamMember::query()->insert($members->map(fn ($member): array => [
            'tournament_id' => $tournament->getKey(),
            'tournament_team_id' => $team->getKey(),
            'user_id' => $member->user_id,
            'role' => (int) $member->user_id === (int) $leader->getKey() ? 'leader' : 'member',
            'game_id_value' => (int) $member->user_id === (int) $leader->getKey()
                ? $leaderGameId
                : (string) ($accountIds[$member->user_id] ?? $member->user?->username ?? ''),
            'ready_mode' => (int) $member->user_id === (int) $leader->getKey() ? $leaderReadyMode : 'auto',
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());

        return $team->load('members');
    }
}
