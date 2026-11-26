<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Events\MatchDisputeResolved;
use App\Modules\Match\Models\DisputeAiAnalysis;
use App\Modules\Match\Models\ExternalMatchGame;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\DisputeResolution;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Dedicated system action for AI auto-resolution.
 * Never delegates to admin-only ResolveDisputeAction and NEVER issues strikes or bans.
 * Must be invoked inside an active transaction that already holds Tournament -> GameMatch -> MatchDispute locks.
 */
final class ResolveDisputeByAiAction
{
    public function __construct(
        private readonly MatchStateMachine $stateMachine,
    ) {}

    public function executeLocked(
        MatchDispute $dispute,
        GameMatch $match,
        DisputeAiAnalysis $analysis,
        DisputeResolution $resolution
    ): void {
        if (! in_array($resolution, [DisputeResolution::PLAYER_A, DisputeResolution::PLAYER_B], true)) {
            throw new LogicException('AI auto-resolution may only award Player A or Player B.');
        }

        if ($dispute->status === DisputeStatus::RESOLVED) {
            throw new LogicException('Dispute is already resolved.');
        }

        $winnerRegistrationId = $resolution === DisputeResolution::PLAYER_A
            ? $match->player_a_registration_id
            : $match->player_b_registration_id;

        if ($winnerRegistrationId === null) {
            throw new LogicException('Winner player registration is missing on the match.');
        }

        $now = Carbon::now();

        $dispute->status = DisputeStatus::RESOLVED;
        $dispute->resolution = $resolution;
        $dispute->resolved_by = null;
        $dispute->resolved_at = $now;
        $dispute->save();

        $match->winner_registration_id = $winnerRegistrationId;
        $match->resolution_reason = 'ai_auto_resolution';
        $this->stateMachine->transition($match, MatchStatus::COMPLETED);

        ExternalMatchGame::query()
            ->where('match_id', $match->id)
            ->whereNotIn('status', [ExternalMatchGame::STATUS_COMPLETED, ExternalMatchGame::STATUS_DRAW])
            ->update([
                'status' => ExternalMatchGame::STATUS_COMPLETED,
                'next_poll_at' => null,
                'updated_at' => $now,
            ]);

        $analysis->forceFill([
            'auto_resolve_eligible' => true,
            'auto_resolve_blocked_reason' => null,
            'auto_resolved_at' => $now,
        ])->save();

        activity()
            ->performedOn($dispute)
            ->event('ai_auto_resolved')
            ->withProperties([
                'match_id' => $match->id,
                'match_uuid' => $match->uuid,
                'dispute_id' => $dispute->id,
                'ai_analysis_id' => $analysis->id,
                'provider' => $analysis->provider,
                'model' => $analysis->model,
                'confidence' => (float) $analysis->confidence,
                'resolution' => $resolution->value,
                'winner_registration_id' => $winnerRegistrationId,
            ])
            ->log('Dispute auto-resolved by AI assistant.');

        MatchCompleted::dispatch($match->id, $match->tournament_id, $winnerRegistrationId, true);
        MatchDisputeResolved::dispatch($dispute->id, $match->id);
    }
}
