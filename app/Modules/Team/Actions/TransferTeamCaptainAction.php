<?php

declare(strict_types=1);

namespace App\Modules\Team\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Team\Models\Team;
use Illuminate\Support\Facades\DB;
use LogicException;

class TransferTeamCaptainAction
{
    public function execute(Team $team, User $newCaptain): void
    {
        if ($team->captain_user_id === $newCaptain->id) {
            throw new LogicException('User is already the captain.');
        }

        $member = $team->members()->where('user_id', $newCaptain->id)->first();

        if (! $member) {
            throw new LogicException('The new captain must be a current team member.');
        }

        DB::transaction(function () use ($team, $newCaptain, $member) {
            // Demote current captain if they are still a member
            $team->members()->where('user_id', $team->captain_user_id)->update(['role' => 'member']);

            // Promote new captain
            $member->update(['role' => 'captain']);

            // Update team record
            $team->captain_user_id = $newCaptain->id;
            $team->save();
        });
    }
}
