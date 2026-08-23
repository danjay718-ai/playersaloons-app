<?php

use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureNotComplianceBlocked;
use App\Http\Middleware\EnsurePlayerRole;
use App\Http\Middleware\SanitizeBroadcastSocketId;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TranslateRenderedHtml;
use App\Http\Middleware\UpdateUserOnlineStatus;
use App\Modules\Operations\Services\ErrorIncidentReporter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->appendToGroup('web', SanitizeBroadcastSocketId::class);
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->appendToGroup('web', UpdateUserOnlineStatus::class);
        $middleware->appendToGroup('web', TranslateRenderedHtml::class);
        $middleware->alias([
            'compliance.clear' => EnsureNotComplianceBlocked::class,
            'role.admin' => EnsureAdminRole::class,
            'role.player' => EnsurePlayerRole::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            AuthenticationException::class,
        ]);

        // Central capture covers HTTP, Livewire, console, and queue exceptions.
        // The reporter is failure-safe so database outages still reach Laravel logs.
        $exceptions->report(function (Throwable $exception): void {
            app(ErrorIncidentReporter::class)->capture($exception);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($exception instanceof AuthenticationException) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json(['message' => 'Unauthenticated.'], 401);
                }

                return redirect()->guest(route('login'));
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;

            $isServerError = $status >= 500;

            if (! $isServerError && ! in_array($status, [403, 404, 419], true)) {
                return null;
            }

            // Avoid filling the incident store with ordinary missing URLs/session
            // expiry noise while still guaranteeing formal public responses.
            $referenceId = $status === 403 || $isServerError
                ? app(ErrorIncidentReporter::class)->capture($exception)
                : null;

            if ($request->is('api/*') || $request->expectsJson()) {
                $message = match ($status) {
                    403 => 'You are not authorized to perform this action.',
                    404 => 'The requested resource was not found.',
                    419 => 'Your session has expired. Please try again.',
                    default => 'We could not complete your request due to an internal error.',
                };

                return response()->json(array_filter([
                    'message' => $message,
                    'reference_id' => $referenceId,
                ]), $status);
            }

            $errorView = $isServerError ? 'errors.500' : "errors.{$status}";

            return response()->view($errorView, [
                'referenceId' => $referenceId,
            ], $status);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
