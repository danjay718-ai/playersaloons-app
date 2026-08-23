<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlayerRole
{
    /**
     * Roles that grant access to player-facing dashboards.
     *
     * @var list<string>
     */
    private const PLAYER_ROLES = [
        'PLAYER',
        'TEAM_CAPTAIN',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole(self::PLAYER_ROLES)) {
            abort(403, 'This area is for players only.');
        }

        return $next($request);
    }
}
