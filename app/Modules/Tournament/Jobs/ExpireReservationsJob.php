<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Jobs;

use App\Modules\Tournament\Actions\CancelRegistrationAction;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\RegistrationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireReservationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CancelRegistrationAction $action): void
    {
        // Find pending registrations that have not been paid within 15 minutes of registration
        $expiredRegistrations = TournamentRegistration::query()->where('status', RegistrationStatus::PENDING)
            ->where('registered_at', '<', now()->subMinutes(15))
            ->get();

        $count = 0;

        /** @var TournamentRegistration $registration */
        foreach ($expiredRegistrations as $registration) {
            if ($registration->user === null) {
                continue;
            }

            try {
                $action->execute($registration, $registration->user);
                $count++;
            } catch (\Throwable $e) {
                Log::error("Failed to expire reservation {$registration->uuid}: {$e->getMessage()}");
            }
        }

        if ($count > 0) {
            Log::info("Expired {$count} tournament seat reservations.");
        }
    }
}
