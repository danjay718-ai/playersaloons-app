<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Stevebauman\Location\Facades\Location;
use App\Modules\Compliance\Models\BlockedCountry;
use Illuminate\Support\Facades\Cache;

class BlockRestrictedCountries
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass for admin and system routes to prevent lockout
        if ($request->is('admin*') || $request->is('livewire*') || $request->is('_debugbar*') || $request->is('broadcasting*')) {
            return $next($request);
        }

        $blockedCountries = Cache::remember('blocked_countries', 3600, function () {
            return BlockedCountry::pluck('message', 'country_code')->toArray();
        });

        if (empty($blockedCountries)) {
            return $next($request);
        }

        if ($position = Location::get($request->ip())) {
            $countryCode = strtoupper($position->countryCode);
            
            if (array_key_exists($countryCode, $blockedCountries)) {
                $message = $blockedCountries[$countryCode] ?? 'Our services are currently not available in your region due to regulatory restrictions.';
                
                return response()->view('errors.restricted-region', [
                    'message' => $message,
                    'countryCode' => $countryCode,
                    'countryName' => $position->countryName
                ], 403);
            }
        }

        return $next($request);
    }
}
