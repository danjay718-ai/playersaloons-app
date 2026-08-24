<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized access to the admin panel.');
        }

        $hasStaffRole = $user->roles->whereNotIn('name', ['PLAYER', 'TEAM_CAPTAIN'])->isNotEmpty();
        if (! $hasStaffRole && ! $user->hasRole('SUPER_ADMIN')) {
            abort(403, 'Unauthorized access to the admin panel.');
        }

        return $next($request);
    }
}
