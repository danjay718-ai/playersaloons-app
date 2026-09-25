<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Mail\SystemNotificationMail;
use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Events\HeadToHeadMatchDisputed;
use App\Modules\Match\Events\MatchDisputed;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Operations\Models\SystemSetting;
use App\Shared\Enums\CompetitionType;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Mail;

final class NotifyAdminsOfDisputeListener implements ShouldQueueAfterCommit
{
    public string $queue = 'mail';

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(MatchDisputed|HeadToHeadMatchDisputed $event): void
    {
        $details = $event instanceof MatchDisputed
            ? $this->tournamentMatchDetails($event->matchId)
            : $this->headToHeadMatchDetails($event->matchId);
        if ($details === null) {
            return;
        }

        User::query()->role(['ADMIN', 'SUPER_ADMIN'])->each(function (User $admin) use ($details): void {
            $this->notifications->send(
                $admin,
                'admin_match_dispute',
                'Match dispute requires review',
                $details['message'],
                $details['action_url'],
            );
        });

        $settings = SystemSetting::query()
            ->whereIn('key', ['notifications.dispute_email', 'notifications.dispute_name'])
            ->pluck('value', 'key');
        $recipient = trim((string) ($settings['notifications.dispute_email'] ?? 'info@playersaloons.com'));
        $recipientName = trim((string) ($settings['notifications.dispute_name'] ?? 'PlayerSaloons Disputes'));
        if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            Mail::to($recipient, $recipientName)->queue(new SystemNotificationMail(
                'Match dispute requires review',
                $details['message'],
                $details['action_url'],
                'Review disputed match',
            ));
        }
    }

    /** @return array{message: string, action_url: string}|null */
    private function tournamentMatchDetails(int $matchId): ?array
    {
        $match = GameMatch::query()->with('tournament:id,name,competition_type')->find($matchId);
        if ($match === null) {
            return null;
        }

        $competition = $match->tournament->competition_type === CompetitionType::HEAD_TO_HEAD
            ? 'head-to-head competition'
            : 'tournament';

        return [
            'message' => "Match #{$match->id} in {$competition} '{$match->tournament->name}' has been disputed and requires an administrator review.",
            'action_url' => "/admin/matches?filter=disputes&match={$match->id}",
        ];
    }

    /** @return array{message: string, action_url: string}|null */
    private function headToHeadMatchDetails(int $matchId): ?array
    {
        $match = HeadToHeadMatch::query()->with('game.translations')->find($matchId);
        if ($match === null) {
            return null;
        }

        $game = $match->game?->translations->first()?->name ?? $match->game?->slug ?? 'Unknown game';

        return [
            'message' => "Head-to-head match #{$match->id} for {$game} has been disputed and requires an administrator review.",
            'action_url' => "/admin/matches?h2h_match={$match->id}",
        ];
    }
}
