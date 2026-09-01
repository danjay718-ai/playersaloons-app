<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\OpenDisputeAction;
use App\Modules\Match\Actions\SubmitMatchResultAction;
use App\Modules\Match\Actions\SubmitV2MatchResultAction;
use App\Modules\Match\Models\GameMatch;
use App\Shared\Enums\MatchOutcome;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class MatchApiController extends Controller
{
    /**
     * Display the specified match.
     */
    public function show(string $uuid): MatchResource
    {
        $match = GameMatch::query()
            ->where('uuid', $uuid)
            ->with(['tournament', 'round', 'playerARegistration.user', 'playerBRegistration.user', 'winnerRegistration.user'])
            ->firstOrFail();
        abort_if((int) $match->tournament->workflow_version === 2 && ! config('features.tournament_v2.enabled'), 404);

        return new MatchResource($match);
    }

    /**
     * Submit a result for the specified match.
     */
    public function submitResult(
        string $uuid,
        Request $request,
        SubmitMatchResultAction $action,
        SubmitV2MatchResultAction $v2Action,
    ): JsonResponse {
        $match = GameMatch::query()
            ->where('uuid', $uuid)
            ->with('tournament')
            ->firstOrFail();

        Gate::authorize('submitResult', $match);

        if ((int) $match->tournament->workflow_version === 2) {
            try {
                $data = $request->validate([
                    'outcome' => ['required', 'in:win,loss,draw'],
                    'notes' => ['nullable', 'string', 'max:500'],
                ]);
                $submission = $v2Action->execute(
                    $match,
                    (int) $request->user()->id,
                    MatchOutcome::from($data['outcome']),
                    $data['notes'] ?? null,
                );

                return response()->json([
                    'message' => 'Result submitted successfully.',
                    'submission' => ['id' => $submission->id, 'outcome' => $submission->outcome],
                ]);
            } catch (InvalidArgumentException|InvalidStateTransitionException|\LogicException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        $winnerUserUuid = $request->input('winner_user_uuid');
        if (! $winnerUserUuid) {
            return response()->json(['message' => 'The winner_user_uuid field is required.'], 422);
        }

        $winnerUser = User::query()->where('uuid', $winnerUserUuid)->first();
        if (! $winnerUser) {
            return response()->json(['message' => 'The selected winner_user_uuid is invalid.'], 422);
        }

        $registration = $match->tournament->registrations()
            ->where('user_id', $winnerUser->id)
            ->first();

        if (! $registration) {
            return response()->json(['message' => 'Winner must be one of the match participants.'], 422);
        }

        try {
            $submission = $action->execute(
                $match,
                $request->user()->id,
                $registration->id,
                $request->input('notes')
            );

            return response()->json([
                'message' => 'Result submitted successfully.',
                'submission' => [
                    'uuid' => $submission->uuid ?? null,
                    'winner_user_uuid' => $winnerUserUuid,
                ],
            ], 200);
        } catch (InvalidArgumentException|InvalidStateTransitionException|\LogicException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Open a dispute for the specified match.
     */
    public function dispute(
        string $uuid,
        Request $request,
        OpenDisputeAction $action
    ): JsonResponse {
        $match = GameMatch::query()->where('uuid', $uuid)->firstOrFail();

        Gate::authorize('dispute', $match);

        try {
            $dispute = $action->execute($match, $request->user()->id);

            return response()->json([
                'message' => 'Dispute opened successfully.',
                'dispute' => [
                    'uuid' => $dispute->uuid,
                    'status' => $dispute->status->value ?? $dispute->status,
                ],
            ], 201);
        } catch (InvalidArgumentException|InvalidStateTransitionException|\LogicException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
