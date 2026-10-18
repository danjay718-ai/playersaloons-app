<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Match\Events\MatchResultSubmitted;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchResultSubmission;
use App\Modules\Tournament\Services\TournamentTimezone;
use App\Shared\Enums\MatchStatus;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

// Persist the time-sensitive bell notification without waiting for a queue worker.
final class NotifyV2ResultResponseListener implements ShouldHandleEventsAfterCommit
{
    public function handle(MatchResultSubmitted $event): void
    {
        $match = GameMatch::query()->with([
            'tournament', 'playerARegistration.user.notificationPreference',
            'playerARegistration.rosterMembers.user.notificationPreference',
            'playerBRegistration.user.notificationPreference',
            'playerBRegistration.rosterMembers.user.notificationPreference',
        ])->find($event->matchId);
        if ($match === null || (int) $match->tournament->workflow_version !== 2
            || $match->status !== MatchStatus::WAITING_FOR_CONFIRMATION) {
            return;
        }
        $submission = MatchResultSubmission::query()->with('attempt')->find($event->submissionId);
        $attempt = $submission?->attempt;
        if ($submission === null || (int) $submission->match_id !== (int) $match->id
            || $attempt === null || $attempt->attempt_number !== $match->active_attempt_number
            || $attempt->status !== 'open' || $attempt->result_deadline_at === null
            || $attempt->result_deadline_at->isPast() || $attempt->submissions()->count() !== 1) {
            return;
        }
        $opponent = $match->playerARegistration?->includesUser($event->submittedByUserId)
            ? $match->playerBRegistration : $match->playerARegistration;
        if ($opponent === null) {
            return;
        }
        $minutes = max(1, (int) ceil(now()->diffInSeconds($attempt->result_deadline_at) / 60));
        $message = __('Your opponent submitted a result in :tournament. Submit your own result within :minutes minutes, by :deadline. Choose Won, Lost or Draw.', [
            'tournament' => $match->tournament->name,
            'minutes' => $minutes,
            'deadline' => $attempt->result_deadline_at->copy()->setTimezone(app(TournamentTimezone::class)->value())->format('M d, Y H:i:s T'),
        ]);
        collect([$opponent->user])->merge($opponent->rosterMembers->pluck('user'))->filter()->unique('id')
            ->each(fn ($user) => app(NotificationService::class)->send(
                $user, 'match_result_submitted', __('Submit your match result'), $message,
                "/tournaments/{$match->tournament->uuid}/view?activeTab=submit-results&match={$match->uuid}",
            ));
    }
}
