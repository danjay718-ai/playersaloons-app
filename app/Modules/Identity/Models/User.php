<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Community\Models\Notification;
use App\Modules\Identity\Services\UserPresenceService;
use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Wallet\Models\Wallet;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Shared\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $uuid
 * @property string $email
 * @property string $username
 * @property string $password
 * @property string $locale
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $accepted_terms_at
 * @property Carbon|null $accepted_privacy_policy_at
 * @property Carbon|null $accepted_cookie_policy_at
 * @property Carbon|null $age_confirmed_at
 * @property bool $newsletter_subscribed
 * @property Carbon|null $newsletter_subscribed_at
 * @property string|null $policy_acceptance_ip
 * @property string|null $policy_acceptance_user_agent
 * @property UserStatus $status
 * @property string|null $two_factor_secret
 * @property array<int, string>|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property-read Wallet|null $wallet
 * @property-read UserProfile|null $profile
 * @property-read Collection<int, KycSubmission> $kycSubmissions
 * @property-read Collection<int, Notification> $notifications
 * @property-read Collection<int, StreamChannel> $streamChannels
 * @property-read Collection<int, ComplianceBlock> $complianceBlocks
 * @property-read Collection<int, Referral> $referrals
 */
class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, InteractsWithMedia, Notifiable, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'email',
        'username',
        'password',
        'email_verified_at',
        'status',
        'locale',
        'last_login_at',
        'accepted_terms_at',
        'accepted_privacy_policy_at',
        'accepted_cookie_policy_at',
        'age_confirmed_at',
        'newsletter_subscribed',
        'newsletter_subscribed_at',
        'policy_acceptance_ip',
        'policy_acceptance_user_agent',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'accepted_terms_at' => 'datetime',
            'accepted_privacy_policy_at' => 'datetime',
            'accepted_cookie_policy_at' => 'datetime',
            'age_confirmed_at' => 'datetime',
            'newsletter_subscribed' => 'boolean',
            'newsletter_subscribed_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the user's profile.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Get the user's wallet.
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get the user's KYC submissions.
     */
    public function kycSubmissions(): HasMany
    {
        return $this->hasMany(KycSubmission::class);
    }

    /**
     * Get the user's notifications.
     *
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the user's stream channels.
     *
     * @return HasMany<StreamChannel, $this>
     */
    public function streamChannels(): HasMany
    {
        return $this->hasMany(StreamChannel::class);
    }

    public function complianceBlocks(): HasMany
    {
        return $this->hasMany(ComplianceBlock::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /**
     * Determine if the user is currently online (active within last 5 minutes).
     */
    public function isOnline(): bool
    {
        return app(UserPresenceService::class)->isOnline((int) $this->getKey());
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
