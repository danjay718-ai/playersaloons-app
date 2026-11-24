<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Verified third-party gameplay account linked via OAuth.
 * Tokens are encrypted at rest and hidden from model serialization.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $provider
 * @property string $external_user_id
 * @property string $external_username
 * @property string $access_token
 * @property string|null $refresh_token
 * @property string|null $scopes
 * @property Carbon|null $token_expires_at
 * @property Carbon $verified_at
 * @property Carbon|null $last_synced_at
 * @property-read User|null $user
 */
class ExternalProviderAccount extends Model
{
    public const PROVIDER_PLAYSTRATEGY = 'playstrategy';

    public const PROVIDER_GOOGLE = 'google';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'provider',
        'external_user_id',
        'external_username',
        'access_token',
        'refresh_token',
        'scopes',
        'token_expires_at',
        'verified_at',
        'last_synced_at',
    ];

    /**
     * Never expose encrypted OAuth tokens in JSON/array serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
