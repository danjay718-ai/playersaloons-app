<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotComplianceBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
            $isBlocked = Cache::remember(
                "user:{$user->getKey()}:compliance_blocked",
                300, // 5 minutes
                fn () => $user->complianceBlocks()->active()->exists()
            );

            if ($isBlocked) {
                abort(403, 'Account access is restricted by compliance review. Contact support for assistance.');
            }
        }

        return $next($request);
    }
}
