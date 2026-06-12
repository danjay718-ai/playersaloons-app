<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TournamentCollection;
use App\Http\Resources\TournamentResource;
use App\Modules\Tournament\Actions\CheckinParticipantAction;
use App\Modules\Tournament\Actions\RegisterForTournamentAction;
use App\Modules\Tournament\Exceptions\CheckinNotOpenException;
use App\Modules\Tournament\Exceptions\TournamentAlreadyRegisteredException;
use App\Modules\Tournament\Exceptions\TournamentFullException;
use App\Modules\Tournament\Exceptions\TournamentNotOpenForRegistrationException;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Wallet\Exceptions\InsufficientBalanceException;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentApiController extends Controller
{
    /**
     * Display a listing of the tournaments.
     */
    public function index(Request $request): TournamentCollection
    {
        $query = Tournament::query()->with('game');

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->input('status')));
        }

        if ($request->filled('game_uuid')) {
            $query->whereHas('game', function ($q) use ($request) {
                $q->where('uuid', $request->input('game_uuid'));
            });
        }

        $perPage = (int) $request->query('per_page', '15');
        $tournaments = $query->paginate($perPage);

        return new TournamentCollection($tournaments);
    }

    /**
     * Display the specified tournament.
     */
    public function show(string $uuid): TournamentResource
    {
        $tournament = Tournament::query()->where('uuid', $uuid)->with('game')->firstOrFail();

        return new TournamentResource($tournament);
    }

    /**
     * Register the authenticated user for the tournament.
     */
    public function register(
        string $uuid,
        Request $request,
        RegisterForTournamentAction $action
    ): JsonResponse {
        $tournament = Tournament::query()->where('uuid', $uuid)->firstOrFail();
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $registration = $action->execute($tournament, $user);

            return response()->json([
                'message' => 'Successfully registered for the tournament.',
                'registration' => [
                    'uuid' => $registration->uuid,
                    'status' => $registration->status->value ?? $registration->status,
                ],
            ], 201);
        } catch (TournamentNotOpenForRegistrationException|TournamentAlreadyRegisteredException|TournamentFullException|InsufficientBalanceException|InvalidStateTransitionException|\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check in the authenticated user for the tournament.
     */
    public function checkin(
        string $uuid,
        Request $request,
        CheckinParticipantAction $action
    ): JsonResponse {
        $tournament = Tournament::query()->where('uuid', $uuid)->firstOrFail();
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $checkin = $action->execute($tournament, $user);

            return response()->json([
                'message' => 'Successfully checked in for the tournament.',
                'checkin' => [
                    'status' => $checkin->status->value ?? $checkin->status,
                    'checked_in_at' => $checkin->checked_in_at?->toIso8601String(),
                ],
            ], 200);
        } catch (CheckinNotOpenException|InvalidStateTransitionException|\LogicException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
