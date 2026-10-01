<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Events\BroadcastTournamentUpdated;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentCancellationRequest;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Tournament\Services\V2CancellationPolicy;
use App\Shared\Enums\RegistrationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class RequestV2CancellationAction
{
    public function __construct(private readonly CancelV2RegistrationAction $cancel) {}

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
            if (! V2CancellationPolicy::isOpen($tournament)) {
                throw new LogicException(__('Cancellation is available only before the tournament starts.'));
            }
            $request = TournamentCancellationRequest::query()
                ->where('registration_id', $locked->id)->where('status', 'pending')->lockForUpdate()->first();
            if ($request !== null) {
                $request->update(['status' => 'approved', 'resolved_at' => now()]);
            }
            $request ??= TournamentCancellationRequest::query()->create([
                'uuid' => Str::uuid()->toString(),
                'tournament_id' => $tournament->id,
                'registration_id' => $locked->id,
                'requested_by' => $requester->id,
                'status' => 'approved',
                'eligible_voter_count' => 0,
                'eligible_voter_ids' => [],
                'required_approvals' => 0,
                'requested_at' => now(),
                'expires_at' => $tournament->start_at,
                'resolved_at' => now(),
            ]);

            $this->cancel->execute($locked, $request);
            BroadcastTournamentUpdated::dispatch((string) $tournament->uuid, 'registration_cancelled');

            return $request;
        }, 3);
    }
}
