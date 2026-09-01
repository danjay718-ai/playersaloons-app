<?php

declare(strict_types=1);

namespace App\Modules\Match\Services;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Events\MatchDisputed;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchAttempt;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class V2StalledMatchService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function armForTournament(int $tournamentId): void
    {
        $tournament = Tournament::query()->find($tournamentId);
        if ($tournament === null || (int) $tournament->workflow_version !== 2) {
            return;
        }

        $rounds = $tournament->rounds()->with(['matches.playerARegistration.user', 'matches.playerBRegistration.user'])->orderBy('round_number')->get();
        foreach ($rounds as $round) {
            $actionable = $round->matches->filter(fn (GameMatch $match) => $match->player_a_registration_id !== null && $match->player_b_registration_id !== null
                && ! in_array($match->status, [MatchStatus::COMPLETED, MatchStatus::FORFEITED], true));
            $unfinished = $round->matches->filter(fn (GameMatch $match) => ! in_array($match->status, [MatchStatus::COMPLETED, MatchStatus::FORFEITED], true));
            if ($actionable->count() !== 1 || $unfinished->count() !== 1) {
                continue;
            }

            $match = $actionable->first();
            if ($match->stalled_deadline_at !== null) {
                return;
            }
            $thirtyMinuteDeadline = now()->addMinutes(30);
            $deadline = $match->round_deadline_at !== null && $match->round_deadline_at->lessThan($thirtyMinuteDeadline)
                ? $match->round_deadline_at
                : $thirtyMinuteDeadline;
            $match->forceFill(['stalled_deadline_at' => $deadline])->save();
            $attempt = MatchAttempt::query()->firstOrCreate(
                ['match_id' => $match->id, 'attempt_number' => $match->active_attempt_number],
                ['uuid' => Str::uuid()->toString(), 'status' => 'open'],
            );
            $attempt->update(['stalled_deadline_at' => $deadline]);

            foreach ([$match->playerARegistration?->user, $match->playerBRegistration?->user] as $user) {
                if ($user !== null) {
                    $this->notifications->send($user, 'last_match_timer', 'Final unresolved match timer', 'This is the final unresolved match in the round. Submit a valid result within 30 minutes.', "/matches/{$match->uuid}");
                }
            }

            return;
        }
    }

    public function expire(int $matchId): void
    {
        DB::transaction(function () use ($matchId): void {
            $tournamentId = GameMatch::query()->whereKey($matchId)->value('tournament_id');
            Tournament::query()->lockForUpdate()->findOrFail($tournamentId);
            $match = GameMatch::query()->with(['round.bracket.tournament', 'playerARegistration.user'])->lockForUpdate()->find($matchId);
            if ($match === null || $match->stalled_deadline_at?->isFuture()
                || in_array($match->status, [MatchStatus::COMPLETED, MatchStatus::FORFEITED], true)) {
                return;
            }

            $attempt = $match->attempts()->where('attempt_number', $match->active_attempt_number)->first();
            if ($attempt?->result_deadline_at?->isFuture()) {
                $match->forceFill(['stalled_deadline_at' => $attempt->result_deadline_at])->save();

                return;
            }
            if ($attempt?->submissions()->count() === 1) {
                $match->forceFill(['stalled_deadline_at' => now()->addMinute()])->save();

                return;
            }
            if ($match->status === MatchStatus::DISPUTED) {
                $match->forceFill(['stalled_deadline_at' => null])->save();

                return;
            }

            $maxRound = Round::query()->where('bracket_id', $match->round->bracket_id)->max('round_number');
            if ((int) $match->round->round_number === (int) $maxRound) {
                $this->holdUnresolvedFinal($match, $attempt);

                return;
            }

            $match->forceFill([
                'status' => MatchStatus::FORFEITED,
                'completed_at' => now(),
                'winner_registration_id' => null,
                'resolution_reason' => 'double_no_show',
            ])->save();
            $attempt?->update(['status' => 'expired', 'resolution' => 'double_no_show', 'resolved_at' => now()]);
            $this->advanceSurvivingBranch($match);
        }, 3);
    }

    public function armRoundDeadline(int $matchId): void
    {
        DB::transaction(function () use ($matchId): void {
            $tournamentId = GameMatch::query()->whereKey($matchId)->value('tournament_id');
            $tournament = Tournament::query()->lockForUpdate()->find($tournamentId);
            $match = GameMatch::query()->lockForUpdate()->find($matchId);
            if ($tournament === null || $match === null || (int) $tournament->workflow_version !== 2
                || ! $tournament->round_duration_seconds) {
                return;
            }

            $roundStartedAt = GameMatch::query()->where('round_id', $match->round_id)
                ->whereNotNull('started_at')->min('started_at');
            if ($roundStartedAt === null) {
                return;
            }
            $deadline = Carbon::parse($roundStartedAt)
                ->addSeconds((int) $tournament->round_duration_seconds);
            GameMatch::query()->where('round_id', $match->round_id)
                ->whereNull('round_deadline_at')->update(['round_deadline_at' => $deadline, 'updated_at' => now()]);
        }, 3);
    }

    public function expireRoundDeadline(int $matchId): void
    {
        DB::transaction(function () use ($matchId): void {
            $tournamentId = GameMatch::query()->whereKey($matchId)->value('tournament_id');
            Tournament::query()->lockForUpdate()->findOrFail($tournamentId);
            $match = GameMatch::query()->with(['round.bracket.tournament', 'playerARegistration.user', 'playerBRegistration.user'])
                ->lockForUpdate()->find($matchId);
            if ($match === null || $match->round_deadline_at?->isFuture()
                || in_array($match->status, [MatchStatus::COMPLETED, MatchStatus::FORFEITED], true)) {
                return;
            }

            $attempt = $match->attempts()->where('attempt_number', $match->active_attempt_number)->first();
            if ($attempt?->result_deadline_at?->isFuture()) {
                $match->forceFill(['round_deadline_at' => $attempt->result_deadline_at])->save();

                return;
            }
            if ($attempt?->submissions()->count() === 1) {
                $match->forceFill(['round_deadline_at' => now()->addMinute()])->save();

                return;
            }
            if ($match->status === MatchStatus::DISPUTED) {
                $match->forceFill(['round_deadline_at' => null])->save();

                return;
            }

            $maxRound = Round::query()->where('bracket_id', $match->round->bracket_id)->max('round_number');
            if ((int) $match->round->round_number === (int) $maxRound) {
                $this->holdUnresolvedFinal($match, $attempt);

                return;
            }

            $match->forceFill([
                'status' => MatchStatus::FORFEITED,
                'completed_at' => now(),
                'winner_registration_id' => null,
                'round_deadline_at' => null,
                'stalled_deadline_at' => null,
                'resolution_reason' => 'round_duration_double_no_show',
            ])->save();
            $attempt?->update(['status' => 'expired', 'resolution' => 'round_duration_double_no_show', 'resolved_at' => now()]);
            $this->advanceSurvivingBranch($match);
        }, 3);
    }

    public function escalateNoChampion(int $matchId): void
    {
        DB::transaction(function () use ($matchId): void {
            $tournamentId = GameMatch::query()->whereKey($matchId)->value('tournament_id');
            Tournament::query()->lockForUpdate()->findOrFail($tournamentId);
            $match = GameMatch::query()->with(['tournament', 'playerARegistration.user'])->lockForUpdate()->find($matchId);
            if ($match === null || $match->final_resolution_eligible_at?->isFuture()
                || $match->final_resolution_notified_at !== null
                || in_array($match->status, [MatchStatus::COMPLETED, MatchStatus::FORFEITED], true)) {
                return;
            }

            $dispute = MatchDispute::query()->firstOrCreate(
                ['match_id' => $match->id, 'status' => DisputeStatus::OPEN],
                [
                    'uuid' => Str::uuid()->toString(),
                    'opened_by' => $match->playerARegistration?->user_id,
                    'reason' => 'The final remained unresolved for 24 hours after the occurrence ended. Admin may select a winner or complete it with no champion.',
                ],
            );
            $match->forceFill([
                'status' => MatchStatus::DISPUTED,
                'final_resolution_notified_at' => now(),
                'resolution_reason' => 'final_admin_resolution_due',
            ])->save();
            $match->attempts()->where('attempt_number', $match->active_attempt_number)->where('status', 'open')->update([
                'status' => 'disputed', 'resolution' => 'final_admin_resolution_due', 'resolved_at' => now(), 'updated_at' => now(),
            ]);
            MatchDisputed::dispatch($match->id, $dispute->id, (int) $dispute->opened_by);
        }, 3);
    }

    private function holdUnresolvedFinal(GameMatch $match, ?MatchAttempt $attempt): void
    {
        $eligibleAt = ($match->tournament->end_at ?? now())->copy()->addDay();
        $match->forceFill([
            'status' => MatchStatus::IN_PROGRESS,
            'stalled_deadline_at' => null,
            'round_deadline_at' => null,
            'final_resolution_eligible_at' => $eligibleAt,
            'resolution_reason' => 'final_awaiting_results',
        ])->save();
        $attempt?->update(['stalled_deadline_at' => null]);
        $match->tournament->forceFill(['payout_status' => 'held'])->save();

        foreach ([$match->playerARegistration?->user, $match->playerBRegistration?->user] as $user) {
            if ($user !== null) {
                $this->notifications->send($user, 'final_awaiting_results', 'Final remains ongoing', 'The final remains open for valid result submissions. If unresolved 24 hours after the occurrence ends, an administrator must resolve it.', "/matches/{$match->uuid}");
            }
        }
    }

    private function advanceSurvivingBranch(GameMatch $match): void
    {
        $roundMatches = $match->round->matches()->orderBy('id')->pluck('id')->values();
        $position = $roundMatches->search($match->id);
        $nextRound = Round::query()->where('bracket_id', $match->round->bracket_id)
            ->where('round_number', $match->round->round_number + 1)->first();
        if ($position === false || $nextRound === null) {
            return;
        }
        $next = $nextRound->matches()->orderBy('id')->get()->get(intdiv((int) $position, 2));
        if ($next === null) {
            return;
        }
        $survivor = $next->player_a_registration_id ?? $next->player_b_registration_id;
        if ($survivor === null) {
            $next->forceFill(['status' => MatchStatus::FORFEITED, 'completed_at' => now(), 'resolution_reason' => 'empty_bracket_branch'])->save();
            $this->advanceSurvivingBranch($next->fresh(['round']));

            return;
        }
        $next->forceFill([
            'winner_registration_id' => $survivor,
            'status' => MatchStatus::COMPLETED,
            'completed_at' => now(),
            'resolution_reason' => 'opponent_double_no_show',
        ])->save();
        MatchCompleted::dispatch($next->id, $next->tournament_id, (int) $survivor);
    }
}
