<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Modules\Identity\Actions\UpdateProfileAction;
use Illuminate\Http\Request;

class ProfileApiController extends Controller
{
    /**
     * Display the authenticated user's profile.
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('profile'));
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(
        Request $request,
        UpdateProfileAction $action
    ): UserResource {
        $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'timezone' => ['nullable', 'string', 'timezone'],
        ]);

        $action->execute(
            $request->user(),
            $request->only(['display_name', 'bio', 'country_code', 'timezone'])
        );

        return new UserResource($request->user()->load('profile'));
    }
}
