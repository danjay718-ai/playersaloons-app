<?php

declare(strict_types=1);

namespace App\Modules\Identity\Listeners;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Events\UserKycSubmitted;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyAdminsOfKycSubmissionListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function handle(UserKycSubmitted $event): void
    {
        $submitter = User::query()->find($event->userId);
        $username = $submitter->username ?? 'A user';

        User::query()
            ->role(['ADMIN', 'SUPER_ADMIN'])
            ->each(function (User $admin) use ($username): void {
                $this->notificationService->send(
                    $admin,
                    'kyc_submitted',
                    'New KYC Submission',
                    "{$username} has submitted a KYC document for review."
                );
            });
    }
}
