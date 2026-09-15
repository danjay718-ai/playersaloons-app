<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentRegistrationMember extends Model
{
    protected $fillable = ['registration_id', 'user_id', 'role'];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'registration_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
