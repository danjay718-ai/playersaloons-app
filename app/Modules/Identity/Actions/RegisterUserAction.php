<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Models\Referral;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserProfile;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterUserAction
{
    /**
     * Register a new user and emit UserRegistered event.
     *
     * Creates the user and profile atomically in a transaction.
     * Wallet creation is handled by the CreateWalletListener
     * reacting to the UserRegistered event.
     *
     * @param  array{email: string, username: string, password: string, display_name?: string|null, accepted_terms_at?: mixed, accepted_privacy_policy_at?: mixed, accepted_cookie_policy_at?: mixed, age_confirmed_at?: mixed, newsletter_subscribed?: bool, newsletter_subscribed_at?: mixed, policy_acceptance_ip?: string|null, policy_acceptance_user_agent?: string|null, referrer_id?: int|null}  $data
     */
    public function execute(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = new User;
            $user->fill([
                'uuid' => Str::uuid()->toString(),
                'email' => $data['email'],
                'username' => $data['username'],
                'password' => $data['password'],   // already hashed by cast
                'status' => UserStatus::ACTIVE,
                'accepted_terms_at' => $data['accepted_terms_at'] ?? null,
                'accepted_privacy_policy_at' => $data['accepted_privacy_policy_at'] ?? null,
                'accepted_cookie_policy_at' => $data['accepted_cookie_policy_at'] ?? null,
                'age_confirmed_at' => $data['age_confirmed_at'] ?? null,
                'newsletter_subscribed' => $data['newsletter_subscribed'] ?? false,
                'newsletter_subscribed_at' => $data['newsletter_subscribed_at'] ?? null,
                'policy_acceptance_ip' => $data['policy_acceptance_ip'] ?? null,
                'policy_acceptance_user_agent' => $data['policy_acceptance_user_agent'] ?? null,
            ]);
            $user->save();

            $profile = new UserProfile;
            $profile->fill([
                'uuid'         => Str::uuid()->toString(),
                'user_id'      => $user->getKey(),
                'display_name' => $data['display_name'] ?? $data['username'],
                'country_code' => $data['country_code'] ?? null,
            ]);
            $profile->save();

            $user->assignRole('PLAYER');

            if (! empty($data['referrer_id'])) {
                $referrer = User::query()
                    ->whereKey($data['referrer_id'])
                    ->where('status', UserStatus::ACTIVE->value)
                    ->first();

                if ($referrer && $referrer->id !== $user->id) {
                    Referral::query()->create([
                        'uuid' => Str::uuid()->toString(),
                        'referrer_id' => $referrer->id,
                        'referred_user_id' => $user->id,
                        'status' => 'pending',
                    ]);
                }
            }

            return $user;
        });

        // Dispatch after transaction commits so queued listeners find the user in the DB.
        UserRegistered::dispatch((int) $user->getKey(), $data['email'], $data['username']);

        return $user;
    }
}
