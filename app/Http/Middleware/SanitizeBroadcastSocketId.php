<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeBroadcastSocketId
{
    public function handle(Request $request, Closure $next): Response
    {
        $socketId = $request->headers->get('X-Socket-ID');

        if ($socketId !== null && ! preg_match('/^\d+\.\d+$/', $socketId)) {
            $request->headers->remove('X-Socket-ID');
        }

        return $next($request);
    }
}
