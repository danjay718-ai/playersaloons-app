<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\UserRegistered;
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
     * @param  array{email: string, username: string, password: string, display_name?: string}  $data
     */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = new User;
            $user->fill([
                'uuid' => Str::uuid()->toString(),
                'email' => $data['email'],
                'username' => $data['username'],
                'password' => $data['password'],   // already hashed by cast
                'status' => UserStatus::ACTIVE,
            ]);
            $user->save();

            $profile = new UserProfile;
            $profile->fill([
                'uuid' => Str::uuid()->toString(),
                'user_id' => $user->getKey(),
                'display_name' => $data['display_name'] ?? $data['username'],
            ]);
            $profile->save();

            $user->assignRole('PLAYER');

            UserRegistered::dispatch((int) $user->getKey(), $data['email'], $data['username']);

            return $user;
        });
    }
}
