<?php

declare(strict_types=1);

namespace App\Modules\Operations\Services;

use App\Modules\Operations\Models\ErrorIncident;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use WeakMap;

/**
 * Persists sanitized, grouped error incidents without becoming a new failure
 * source. Laravel's normal log channel remains the fallback and source of truth
 * when the application database is unavailable.
 */
final class ErrorIncidentReporter
{
    /** @var WeakMap<Throwable, string>|null */
    private static ?WeakMap $captured = null;

    /**
     * Capture an exception once per PHP process/request and return its public
     * reference ID. Repeated fingerprints within 15 minutes are grouped.
     *
     * @param  array<string, mixed>  $context
     */
    public function capture(Throwable $exception, array $context = [], ?string $source = null): ?string
    {
        self::$captured ??= new WeakMap;

        if (self::$captured->offsetExists($exception)) {
            $reference = self::$captured[$exception];

            return $reference !== '' ? $reference : null;
        }

        // Prevent recursive capture if persistence itself fails.
        self::$captured[$exception] = '';

        try {
            $request = app()->bound('request') ? request() : null;
            $statusCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $fingerprint = $this->fingerprint($exception, $request instanceof Request ? $request : null);
            $now = now();

            $incident = ErrorIncident::query()
                ->where('fingerprint', $fingerprint)
                ->whereNull('resolved_at')
                ->where('last_seen_at', '>=', $now->copy()->subMinutes(15))
                ->latest('last_seen_at')
                ->first();

            if ($incident) {
                $incident->forceFill([
                    'occurrences' => $incident->occurrences + 1,
                    'last_seen_at' => $now,
                    'context' => $this->safeContext($context),
                ])->save();
            } else {
                $incident = ErrorIncident::query()->create([
                    'reference_id' => 'ERR-'.Str::upper((string) Str::ulid()),
                    'fingerprint' => $fingerprint,
                    'level' => $statusCode >= 500 ? 'error' : 'warning',
                    'source' => $source ?? (app()->runningInConsole() ? 'console' : 'http'),
                    'status_code' => $statusCode,
                    'exception_class' => $exception::class,
                    'message' => $this->sanitizeMessage($exception),
                    'route' => $request instanceof Request ? $request->route()?->getName() : null,
                    'method' => $request instanceof Request ? $request->method() : null,
                    'path' => $request instanceof Request ? '/'.ltrim($request->path(), '/') : null,
                    'user_id' => Auth::id(),
                    'ip_hash' => $request instanceof Request && $request->ip()
                        ? hash_hmac('sha256', $request->ip(), (string) config('app.key'))
                        : null,
                    'context' => $this->safeContext($context),
                    'stack_trace' => Str::limit($exception->getTraceAsString(), 65000, '\n…'),
                    'occurrences' => 1,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ]);
            }

            return self::$captured[$exception] = $incident->reference_id;
        } catch (Throwable $persistenceFailure) {
            Log::warning('Error incident could not be persisted; standard exception logging remains active.', [
                'incident_exception' => $exception::class,
                'persistence_exception' => $persistenceFailure::class,
            ]);

            return null;
        }
    }

    /**
     * Report a caught exception through Laravel and return a safe user message.
     *
     * @param  array<string, mixed>  $context
     */
    public function userMessage(Throwable $exception, string $message, array $context = []): string
    {
        $reference = $this->capture($exception, $context);
        report($exception);

        return $reference ? "{$message} Reference: {$reference}" : $message;
    }

    private function fingerprint(Throwable $exception, ?Request $request): string
    {
        return hash('sha256', implode('|', [
            $exception::class,
            $exception->getFile(),
            (string) $exception->getLine(),
            (string) ($request?->route()?->getName() ?? $request?->path()),
        ]));
    }

    private function sanitizeMessage(Throwable $exception): string
    {
        if ($exception instanceof QueryException) {
            $sqlState = is_array($exception->errorInfo) ? ($exception->errorInfo[0] ?? 'unknown') : 'unknown';

            return "Database query failed (SQLSTATE {$sqlState}). SQL and bindings were omitted to protect sensitive data.";
        }

        $message = $exception->getMessage() ?: 'No exception message provided.';
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [REDACTED]', $message) ?? $message;
        $message = preg_replace('/(password|token|secret|authorization)(\s*[=:]\s*)[^\s,;]+/i', '$1$2[REDACTED]', $message) ?? $message;

        return Str::limit($message, 4000, '…');
    }

    /** @param array<string, mixed> $context */
    private function safeContext(array $context): array
    {
        return collect($context)
            ->reject(fn (mixed $value, string $key): bool => preg_match('/password|token|secret|cookie|authorization|document/i', $key) === 1)
            ->map(fn (mixed $value): string|int|float|bool|null => is_scalar($value) || $value === null ? $value : '[omitted]')
            ->all();
    }
}
