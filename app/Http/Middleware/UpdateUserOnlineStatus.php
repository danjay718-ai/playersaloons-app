<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\Services\UserPresenceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserOnlineStatus
{
    public function __construct(private readonly UserPresenceService $presence) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $this->presence->markOnline((int) Auth::id());
        }

        return $next($request);
    }
}
