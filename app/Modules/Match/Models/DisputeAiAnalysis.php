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
}
