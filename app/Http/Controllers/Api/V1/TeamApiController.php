<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamResource;
use App\Modules\Identity\Models\User;
use App\Modules\Team\Actions\CreateTeamAction;
use App\Modules\Team\Actions\InviteToTeamAction;
use App\Modules\Team\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LogicException;

class TeamApiController extends Controller
{
    /**
     * Display the specified team.
     */
    public function show(string $uuid): TeamResource
    {
        $team = Team::query()
            ->where('uuid', $uuid)
            ->with(['captain', 'members.user'])
            ->firstOrFail();

        return new TeamResource($team);
    }

    /**
     * Create a new team.
     */
    public function create(
        Request $request,
        CreateTeamAction $action
    ): JsonResponse {
        Gate::authorize('create', Team::class);

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
            'logo_path' => ['nullable', 'string', 'max:500'],
        ]);

        $team = $action->execute(
            $request->only(['name', 'logo_path']),
            $request->user()
        );

        return (new TeamResource($team->load(['captain', 'members.user'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Invite a member to the team.
     */
    public function invite(
        string $uuid,
        Request $request,
        InviteToTeamAction $action
    ): JsonResponse {
        $team = Team::query()->where('uuid', $uuid)->firstOrFail();

        Gate::authorize('invite', $team);

        $request->validate([
            'invited_user_uuid' => ['required', 'string', 'exists:users,uuid'],
        ]);

        $invitedUser = User::query()
            ->where('uuid', $request->input('invited_user_uuid'))
            ->firstOrFail();

        try {
            $invitation = $action->execute($team, $invitedUser, $request->user());

            return response()->json([
                'message' => 'Invitation sent successfully.',
                'invitation' => [
                    'uuid' => $invitation->uuid,
                    'status' => $invitation->status->value ?? $invitation->status,
                ],
            ], 201);
        } catch (LogicException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
