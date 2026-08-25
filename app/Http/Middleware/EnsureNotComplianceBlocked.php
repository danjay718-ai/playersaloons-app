<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotComplianceBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
            // This must be checked live. A compliance block is a security
            // action and must take effect for an already-active session on
            // its very next request, not after a cache TTL expires.
            if ($user->complianceBlocks()->active()->exists()) {
                return redirect()->route('account.restricted');
            }
        }

        return $next($request);
    }
}
