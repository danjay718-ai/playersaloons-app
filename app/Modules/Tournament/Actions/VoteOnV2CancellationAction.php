<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentCancellationRequest;
use App\Modules\Tournament\Models\TournamentCancellationVote;
use Illuminate\Support\Facades\DB;
use LogicException;

final class VoteOnV2CancellationAction
{
    public function __construct(
        private readonly CancelV2RegistrationAction $cancel,
        private readonly NotificationService $notifications,
    ) {}

    public function execute(TournamentCancellationRequest $request, User $voter, bool $approved): TournamentCancellationRequest
    {
        return DB::transaction(function () use ($request, $voter, $approved): TournamentCancellationRequest {
            $tournamentId = TournamentCancellationRequest::query()->whereKey($request->id)->value('tournament_id');
            Tournament::query()->lockForUpdate()->findOrFail($tournamentId);
            $locked = TournamentCancellationRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== 'pending' || now()->greaterThanOrEqualTo($locked->expires_at)) {
                throw new LogicException('This cancellation vote is no longer open.');
            }
            if ((int) $locked->requested_by === (int) $voter->id) {
                throw new LogicException('The requesting player cannot vote.');
            }
            $eligible = in_array((int) $voter->id, array_map('intval', $locked->eligible_voter_ids ?? []), true);
            if (! $eligible) {
                throw new LogicException('Only eligible joined players may vote.');
            }
            if (TournamentCancellationVote::query()->where('request_id', $locked->id)->where('voter_id', $voter->id)->exists()) {
                throw new LogicException('Cancellation votes are final and cannot be changed.');
            }

            TournamentCancellationVote::query()->create([
                'request_id' => $locked->id,
                'voter_id' => $voter->id,
                'approved' => $approved,
                'voted_at' => now(),
            ]);
            $approvals = $locked->votes()->where('approved', true)->count();
            $cast = $locked->votes()->count();
            $remaining = $locked->eligible_voter_count - $cast;

            if ($approvals >= $locked->required_approvals) {
                $locked->update(['status' => 'approved', 'resolved_at' => now()]);
                $this->cancel->execute($locked->registration);
                $this->notifications->send($locked->requester, 'tournament_cancellation_approved', 'Cancellation approved', 'Your tournament cancellation request was approved and processed.', "/tournaments/{$locked->tournament->uuid}/view");
            } elseif ($approvals + $remaining < $locked->required_approvals) {
                $locked->update(['status' => 'rejected', 'resolved_at' => now()]);
                $this->notifications->send($locked->requester, 'tournament_cancellation_rejected', 'Cancellation rejected', 'Your tournament cancellation request did not receive enough approvals.', "/tournaments/{$locked->tournament->uuid}/view");
            }

            return $locked->fresh() ?? $locked;
        }, 3);
    }
}
