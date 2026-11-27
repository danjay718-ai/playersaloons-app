<?php

declare(strict_types=1);

namespace App\Modules\Match\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $match_dispute_id
 * @property int $analysis_version
 * @property int $evidence_count
 * @property string $provider
 * @property string $model
 * @property string $status
 * @property string|null $suggested_resolution
 * @property string|null $confidence
 * @property string|null $summary
 * @property array<int, string>|null $evidence_findings_json
 * @property array<int, string>|null $red_flags_json
 * @property string|null $prompt_hash
 * @property array<string, mixed>|null $raw_response_json
 * @property bool $auto_resolve_eligible
 * @property string|null $auto_resolve_blocked_reason
 * @property Carbon|null $auto_resolved_at
 * @property bool|null $admin_agreed
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MatchDispute $dispute
 */
class DisputeAiAnalysis extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const SUGGESTED_PLAYER_A = 'player_a';

    public const SUGGESTED_PLAYER_B = 'player_b';

    public const SUGGESTED_REMATCH = 'rematch';

    public const SUGGESTED_NEEDS_HUMAN = 'needs_human_review';

    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'match_dispute_id',
        'analysis_version',
        'evidence_count',
        'provider',
        'model',
        'status',
        'suggested_resolution',
        'confidence',
        'summary',
        'evidence_findings_json',
        'red_flags_json',
        'prompt_hash',
        'raw_response_json',
        'auto_resolve_eligible',
        'auto_resolve_blocked_reason',
        'auto_resolved_at',
        'admin_agreed',
        'error_message',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'analysis_version' => 'integer',
            'evidence_count' => 'integer',
            'confidence' => 'decimal:4',
            'evidence_findings_json' => 'array',
            'red_flags_json' => 'array',
            'raw_response_json' => 'array',
            'auto_resolve_eligible' => 'boolean',
            'auto_resolved_at' => 'datetime',
            'admin_agreed' => 'boolean',
        ];
    }

    /** @return BelongsTo<MatchDispute, $this> */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(MatchDispute::class, 'match_dispute_id');
    }

    public function suggestedOutcomeLabel(?string $playerAName = null, ?string $playerBName = null): string
    {
        return match ($this->suggested_resolution) {
            self::SUGGESTED_PLAYER_A => $playerAName ? __('Player A (:name)', ['name' => $playerAName]) : __('Player A'),
            self::SUGGESTED_PLAYER_B => $playerBName ? __('Player B (:name)', ['name' => $playerBName]) : __('Player B'),
            self::SUGGESTED_REMATCH => __('Rematch / Draw'),
            self::SUGGESTED_NEEDS_HUMAN => __('Needs Human Review'),
            default => ucfirst(str_replace('_', ' ', (string) $this->suggested_resolution)),
        };
    }

    public function escalationReasonSummary(): ?string
    {
        if ($this->auto_resolved_at !== null || $this->status === self::STATUS_PENDING) {
            return null;
        }

        if ($this->status === self::STATUS_FAILED) {
            $detail = trim((string) $this->error_message);

            return $detail !== ''
                ? __('AI review failed (:detail) — escalated for manual admin review.', ['detail' => $detail])
                : __('AI review failed — escalated for manual admin review.');
        }

        $reason = (string) ($this->auto_resolve_blocked_reason ?? '');
        if ($reason !== '') {
            if (str_starts_with($reason, 'red_flags_present:')) {
                $flags = str_replace('_', ' ', substr($reason, strlen('red_flags_present:')));

                return __('AI flagged issues in the evidence (:flags) — escalated for admin verification.', ['flags' => $flags]);
            }

            return match ($reason) {
                'shadow_mode_enabled' => __('AI is in Shadow/Advisory mode — auto-resolve is off, so admin ruling is required.'),
                'ai_disputes_disabled' => __('AI auto-resolve is disabled — manual admin review required.'),
                'awaiting_opponent_evidence' => __('Only one side has submitted evidence so far — waiting or ready for manual admin ruling.'),
                'awaiting_opponent_response_window' => __('Opponent response window is still open before auto-resolve; admin can still rule now.'),
                'analysis_stale_evidence_changed' => __('New evidence was uploaded after the AI review — re-run AI or review manually.'),
                'suggested_resolution_requires_human' => __('AI could not verify a clear winner from the submitted evidence — escalated to admin.'),
                'confidence_below_threshold' => __('AI confidence (:pct%) is below the auto-resolve threshold.', ['pct' => number_format((float) $this->confidence * 100, 1)]),
                'exceeds_max_prize_cap' => __('Entry fee or prize pool exceeds the AI auto-resolve cap — admin confirmation required.'),
                'tournament_final_requires_human' => __('Tournament final matches always require manual admin review.'),
                'analysis_failed' => __('AI review failed — escalated for manual admin review.'),
                'analysis_not_ready' => __('AI review is still pending.'),
                default => ucfirst(str_replace('_', ' ', $reason)),
            };
        }

        $redFlags = is_array($this->red_flags_json) ? $this->red_flags_json : [];
        if ($redFlags !== []) {
            return __('AI flagged issues in the evidence (:flags) — escalated for admin verification.', [
                'flags' => implode(', ', $redFlags),
            ]);
        }

        return match ($this->suggested_resolution) {
            self::SUGGESTED_NEEDS_HUMAN => __('AI could not verify a clear winner from the submitted evidence — escalated to admin.'),
            self::SUGGESTED_REMATCH => __('AI recommended a rematch — requires admin confirmation.'),
            default => __('AI review completed in advisory mode — awaiting admin ruling.'),
        };
    }

    public function shortEscalationLabel(): string
    {
        if ($this->status === self::STATUS_PENDING) {
            return __('AI Reviewing…');
        }

        if ($this->status === self::STATUS_FAILED) {
            return __('AI Failed · Escalated');
        }

        if ($this->auto_resolved_at !== null) {
            return __('AI Auto-Resolved');
        }

        $reason = (string) ($this->auto_resolve_blocked_reason ?? '');
        if (str_starts_with($reason, 'red_flags_present:') || ! empty($this->red_flags_json)) {
            return __('AI Reviewed · Red Flags');
        }

        return match ($reason) {
            'tournament_final_requires_human' => __('AI Reviewed · Final'),
            'exceeds_max_prize_cap' => __('AI Reviewed · Prize Cap'),
            'confidence_below_threshold' => __('AI Reviewed · Low Confidence'),
            'shadow_mode_enabled' => __('AI Reviewed · Advisory'),
            'awaiting_opponent_evidence', 'awaiting_opponent_response_window' => __('AI Reviewed · Waiting Window'),
            'analysis_stale_evidence_changed' => __('AI Reviewed · New Evidence'),
            'suggested_resolution_requires_human' => __('AI Reviewed · Inconclusive'),
            default => $this->suggested_resolution === self::SUGGESTED_NEEDS_HUMAN
                ? __('AI Reviewed · Inconclusive')
                : __('AI Reviewed · Escalated'),
        };
    }
}
