<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\PlayerDisputeStrike;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\Activity;
use App\Shared\Enums\UserStatus;

final class UserStatusDetails
{
    public function forUser(User $user): ?array
    {
        $descriptions = match ($user->status) {
            UserStatus::BANNED => ['user_banned'],
            UserStatus::SUSPENDED => ['user_suspended'],
            UserStatus::ACTIVE => ['user_unbanned', 'user_unsuspended'],
        };
        $audit = Activity::query()->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->id)->whereIn('description', $descriptions)
            ->with('causer')->latest('created_at')->latest('id')->first();
        // Older automatic bans only recorded their evidence in the strike table.
        $strike = $user->status === UserStatus::BANNED
            ? PlayerDisputeStrike::query()->where('user_id', $user->id)
                ->where('permanent_ban_applied', true)->with('issuer')
                ->oldest('created_at')->oldest('id')->first()
            : null;
        if ($strike !== null && ($audit === null || $strike->created_at->greaterThan($audit->created_at))) {
            return [
                'reason' => $strike->reason,
                'source' => __('Automatic dispute ban'),
                'actor' => $strike->issuer?->username,
                'at' => $strike->created_at,
                'strike_number' => $strike->strike_number,
            ];
        }
        if ($audit === null) {
            return null;
        }

        return [
            'reason' => $audit->properties->get('reason'),
            'source' => $audit->properties->get('source') === 'dispute_strikes' ? __('Automatic dispute ban') : __('Administrator'),
            'actor' => $audit->causer instanceof User ? $audit->causer->username : null,
            'at' => $audit->created_at,
            'strike_number' => $audit->properties->get('strike_number'),
        ];
    }
}
