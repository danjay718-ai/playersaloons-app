<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateProfileAction
{
    /**
     * Update a user's profile fields.
     *
     * @param  array{display_name?: string|null, bio?: string|null, country_code?: string|null, timezone?: string|null}  $data
     */
    public function execute(User $user, array $data): UserProfile
    {
        return DB::transaction(function () use ($user, $data): UserProfile {
            /** @var UserProfile $profile */
            $profile = $user->profile()->firstOrCreate(
                ['user_id' => $user->getKey()],
                [
                    'uuid' => Str::uuid()->toString(),
                    'display_name' => (string) $user->getAttribute('username'),
                ]
            );

            $profile->fill(array_filter($data, fn (mixed $value): bool => $value !== null));
            $profile->save();

            return $profile;
        });
    }
}
