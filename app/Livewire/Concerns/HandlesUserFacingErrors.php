<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Modules\Operations\Services\ErrorIncidentReporter;
use Throwable;

trait HandlesUserFacingErrors
{
    /**
     * Report the technical exception while returning only a formal message and
     * support reference to the browser.
     *
     * @param  array<string, mixed>  $context
     */
    protected function safeError(Throwable $exception, string $message = 'We could not complete that action.', array $context = []): string
    {
        return app(ErrorIncidentReporter::class)->userMessage($exception, $message, $context);
    }
}
