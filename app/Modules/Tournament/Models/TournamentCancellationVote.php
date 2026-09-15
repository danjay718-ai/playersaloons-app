<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TournamentCancellationVote extends Model
{
    public $timestamps = false;

    protected $fillable = ['request_id', 'voter_id', 'approved', 'voted_at'];

    protected function casts(): array
    {
        return ['approved' => 'boolean', 'voted_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(TournamentCancellationRequest::class, 'request_id');
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_id')->withTrashed();
    }
}
