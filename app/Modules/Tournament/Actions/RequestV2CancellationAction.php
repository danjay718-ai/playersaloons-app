<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentCancellationRequest;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\RegistrationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class RequestV2CancellationAction
{
    public function __construct(
        private readonly CancelV2RegistrationAction $cancel,
        private readonly NotificationService $notifications,
    ) {}

    public function execute(TournamentRegistration $registration, User $requester): TournamentCancellationRequest
    {
        return DB::transaction(function () use ($registration, $requester): TournamentCancellationRequest {
            $tournamentId = TournamentRegistration::query()->whereKey($registration->id)->value('tournament_id');
            $tournament = Tournament::query()->lockForUpdate()->findOrFail($tournamentId);
            $locked = TournamentRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            if ((int) $tournament->workflow_version !== 2 || (int) $locked->user_id !== (int) $requester->id) {
                throw new LogicException('You cannot request cancellation for this registration.');
            }
            if ($locked->status !== RegistrationStatus::CONFIRMED) {
                throw new LogicException('This registration is not active.');
            }
            if ($tournament->start_at === null || now()->greaterThan($tournament->start_at->copy()->subMinutes(30))) {
                throw new LogicException('Cancellation requests close 30 minutes before tournament start.');
            }
            if (TournamentCancellationRequest::query()->where('tournament_id', $tournament->id)->where('status', 'pending')->exists()) {
                throw new LogicException('Another cancellation request is already pending for this occurrence.');
            }

            $eligible = $tournament->registrations()
                ->where('status', RegistrationStatus::CONFIRMED)
                ->where('user_id', '!=', $requester->id)
                ->pluck('user_id')
                ->unique()
                ->values();
            $request = TournamentCancellationRequest::query()->create([
                'uuid' => Str::uuid()->toString(),
                'tournament_id' => $tournament->id,
                'registration_id' => $locked->id,
                'requested_by' => $requester->id,
                'status' => $eligible->isEmpty() ? 'approved' : 'pending',
                'eligible_voter_count' => $eligible->count(),
                'eligible_voter_ids' => $eligible->map(static fn ($id): int => (int) $id)->all(),
                'required_approvals' => (int) ceil($eligible->count() / 2),
                'requested_at' => now(),
                'expires_at' => $tournament->start_at,
                'resolved_at' => $eligible->isEmpty() ? now() : null,
            ]);

            if ($eligible->isEmpty()) {
                $this->cancel->execute($locked);
            } else {
                User::query()->whereIn('id', $eligible)->each(fn (User $voter) => $this->notifications->send(
                    $voter,
                    'tournament_cancellation_vote',
                    'Cancellation vote requested',
                    "A player requested to cancel their entry in {$tournament->name}. Vote before tournament start.",
                    "/tournaments/{$tournament->uuid}/view",
                ));
            }

            return $request;
        }, 3);
    }
}
