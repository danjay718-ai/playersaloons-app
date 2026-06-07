<?php

declare(strict_types=1);

namespace App\Modules\Team\Jobs;

use App\Modules\Team\Models\TeamInvitation;
use App\Shared\Enums\TeamInvitationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireTeamInvitationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $expiredCount = TeamInvitation::where('status', TeamInvitationStatus::PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => TeamInvitationStatus::EXPIRED]);

        if ($expiredCount > 0) {
            Log::info("Expired {$expiredCount} team invitations.");
        }
    }
}
