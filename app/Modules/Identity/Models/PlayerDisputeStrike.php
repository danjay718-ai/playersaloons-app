<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Tournament\Models\Tournament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlayerDisputeStrike extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid', 'user_id', 'tournament_id', 'match_id', 'dispute_id', 'issued_by',
        'strike_number', 'reason', 'balance_penalty', 'permanent_ban_applied', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'strike_number' => 'integer',
            'balance_penalty' => 'decimal:2',
            'permanent_ban_applied' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(MatchDispute::class, 'dispute_id');
    }
}
