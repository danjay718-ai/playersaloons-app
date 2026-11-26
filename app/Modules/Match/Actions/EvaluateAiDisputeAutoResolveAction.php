<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Models\DisputeAiAnalysis;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\Models\MatchEvidence;
use App\Modules\Match\Services\AiDisputeSettings;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\DisputeResolution;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchStatus;
use App\Shared\Support\DecimalMoney;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EvaluateAiDisputeAutoResolveAction
{
    public function __construct(
        private readonly AiDisputeSettings $settings,
        private readonly ResolveDisputeByAiAction $resolveByAi,
    ) {}

    public function execute(int $disputeId): bool
    {
        return (bool) DB::transaction(function () use ($disputeId): bool {
            $matchId = MatchDispute::query()->whereKey($disputeId)->value('match_id');
            if ($matchId === null) {
                return false;
            }

            $tournamentId = GameMatch::query()->whereKey($matchId)->value('tournament_id');
            if ($tournamentId === null) {
                return false;
            }

            // Lock ordering invariant: Tournament -> GameMatch -> MatchDispute
            $tournament = Tournament::query()->lockForUpdate()->find($tournamentId);
            $match = GameMatch::query()->lockForUpdate()->find($matchId);
            $dispute = MatchDispute::query()->lockForUpdate()->find($disputeId);

            if (! $tournament instanceof Tournament || ! $match instanceof GameMatch || ! $dispute instanceof MatchDispute) {
                return false;
            }

            if (! in_array($dispute->status, [DisputeStatus::OPEN, DisputeStatus::UNDER_REVIEW], true) || $match->status !== MatchStatus::DISPUTED) {
                return false;
            }

            $latestAnalysis = DisputeAiAnalysis::query()
                ->where('match_dispute_id', $dispute->id)
                ->orderByDesc('analysis_version')
                ->first();

            if (! $latestAnalysis instanceof DisputeAiAnalysis) {
                return false;
            }

            if (! $this->settings->enabled()) {
                return $this->block($latestAnalysis, 'ai_disputes_disabled');
            }

            if ($this->settings->mode() !== AiDisputeSettings::MODE_AUTO_RESOLVE) {
                return $this->block($latestAnalysis, 'shadow_mode_enabled');
            }

            $hasPendingAnalysis = DisputeAiAnalysis::query()
                ->where('match_dispute_id', $dispute->id)
                ->where('status', DisputeAiAnalysis::STATUS_PENDING)
                ->exists();

            if ($hasPendingAnalysis || $latestAnalysis->status !== DisputeAiAnalysis::STATUS_COMPLETED) {
                return $this->block($latestAnalysis, 'analysis_not_ready');
            }

            // Concurrency & freshness guard under MatchDispute lock
            $evidenceCount = MatchEvidence::query()->where('dispute_id', $dispute->id)->count();
            if ($evidenceCount < 1 || $evidenceCount !== (int) $latestAnalysis->evidence_count) {
                return $this->block($latestAnalysis, 'analysis_stale_evidence_changed');
            }

            // Fairness wait rule: require both players OR elapsed response window since 1st upload
            if ($evidenceCount < 2) {
                $firstEvidenceAt = MatchEvidence::query()
                    ->where('dispute_id', $dispute->id)
                    ->orderBy('created_at')
                    ->value('created_at');

                if ($firstEvidenceAt === null) {
                    return $this->block($latestAnalysis, 'awaiting_opponent_evidence');
                }

                $windowDeadline = Carbon::parse($firstEvidenceAt)
                    ->addMinutes($this->settings->evidenceWindowMinutes());

                if (now()->lessThan($windowDeadline)) {
                    return $this->block($latestAnalysis, 'awaiting_opponent_response_window');
                }
            }

            // Only strictly clear player_a / player_b rulings are eligible
            $resolution = match ($latestAnalysis->suggested_resolution) {
                DisputeAiAnalysis::SUGGESTED_PLAYER_A => DisputeResolution::PLAYER_A,
                DisputeAiAnalysis::SUGGESTED_PLAYER_B => DisputeResolution::PLAYER_B,
                default => null,
            };

            if ($resolution === null) {
                return $this->block($latestAnalysis, 'suggested_resolution_requires_human');
            }

            if ((float) $latestAnalysis->confidence < $this->settings->minConfidence()) {
                return $this->block($latestAnalysis, 'confidence_below_threshold');
            }

            $redFlags = is_array($latestAnalysis->red_flags_json) ? $latestAnalysis->red_flags_json : [];
            if ($redFlags !== []) {
                return $this->block($latestAnalysis, 'red_flags_present:'.implode(',', array_slice($redFlags, 0, 3)));
            }

            // Financial cap in locked platform currency (integer minor units)
            $capMinor = DecimalMoney::toMinor($this->settings->maxPrizeCap());
            $entryFeeMinor = DecimalMoney::toMinor(number_format((float) $tournament->entry_fee, 2, '.', ''));
            $prizePoolAmount = max(
                (float) ($tournament->advertised_prize_pool ?? 0),
                (float) $tournament->prize_pool,
                (float) ($tournament->prize_1st ?? 0) + (float) ($tournament->prize_2nd ?? 0)
            );
            $prizePoolMinor = DecimalMoney::toMinor(number_format($prizePoolAmount, 2, '.', ''));

            if ($prizePoolMinor > $capMinor || $entryFeeMinor > $capMinor) {
                return $this->block($latestAnalysis, 'exceeds_max_prize_cap');
            }

            // Finals protection: never auto-resolve tournament finals or single-round finals
            if ($this->isTournamentFinal($tournament, $match)) {
                return $this->block($latestAnalysis, 'tournament_final_requires_human');
            }

            $this->resolveByAi->executeLocked($dispute, $match, $latestAnalysis, $resolution);

            return true;
        });
    }

    private function block(DisputeAiAnalysis $analysis, string $reason): bool
    {
        $analysis->forceFill([
            'auto_resolve_eligible' => false,
            'auto_resolve_blocked_reason' => mb_substr($reason, 0, 191),
        ])->save();

        return false;
    }

    private function isTournamentFinal(Tournament $tournament, GameMatch $match): bool
    {
        if ((int) $tournament->max_participants <= 2) {
            return true;
        }

        $matchRoundNumber = Round::query()->whereKey($match->round_id)->value('round_number');
        if ($matchRoundNumber === null) {
            return true;
        }

        $maxRoundNumber = Round::query()
            ->whereHas('bracket', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->max('round_number');

        if ($maxRoundNumber === null || (int) $maxRoundNumber <= 1) {
            return true;
        }

        return (int) $matchRoundNumber >= (int) $maxRoundNumber;
    }
}
