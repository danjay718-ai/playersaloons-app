<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerFollow extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'follower_user_id',
        'followed_user_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function followed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followed_user_id');
    }
}
