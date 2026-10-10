<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Localization\Services\LanguageRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(app(LanguageRegistry::class)->available());
        $fallback = (string) config('localization.default', config('app.fallback_locale', 'en'));
        if (! in_array($fallback, $supported, true)) {
            $fallback = 'en';
        }
        $sessionLocale = $request->hasSession()
            ? $request->session()->get('locale')
            : null;
        $user = $request->user();
        $userLocale = $user === null ? null : $user->locale;
        $locale = $userLocale ?? $sessionLocale ?? $fallback;

        if (! in_array($locale, $supported, true)) {
            $locale = $fallback;
        }

        App::setLocale($locale);

        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }
}
