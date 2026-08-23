<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Admin panel roles accepted at the route level.
     *
     * Individual Livewire components may apply tighter per-screen checks.
     *
     * @var list<string>
     */
    private const ADMIN_ROLES = [
        'SUPER_ADMIN',
        'ADMIN',
        'MODERATOR',
        'TOURNAMENT_ORGANIZER',
        'SUPPORT_AGENT',
        'FINANCE_OPERATOR',
        'KYC_REVIEWER',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole(self::ADMIN_ROLES)) {
            abort(403, 'Unauthorized access to the admin panel.');
        }

        return $next($request);
    }
}
