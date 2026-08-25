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

    /**
     * Staff/Admin roles that should be gracefully redirected to the admin panel.
     *
     * @var list<string>
     */
    private const ADMIN_ROLES = [
        'SUPER_ADMIN',
        'ADMIN',
        'MODERATOR',
        'FINANCE_OPERATOR',
        'KYC_REVIEWER',
        'SUPPORT_AGENT',
        'TOURNAMENT_ORGANIZER',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest('/login');
        }

        if (! $user->hasAnyRole(self::PLAYER_ROLES)) {
            if ($user->hasAnyRole(self::ADMIN_ROLES)) {
                return redirect('/admin');
            }

            abort(403, 'This area is for players only.');
        }

        return $next($request);
    }
}
