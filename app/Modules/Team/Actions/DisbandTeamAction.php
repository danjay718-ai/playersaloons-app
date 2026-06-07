<?php

declare(strict_types=1);

namespace App\Modules\Team\Actions;

use App\Modules\Team\Models\Team;
use Illuminate\Support\Facades\DB;

class DisbandTeamAction
{
    public function execute(Team $team): void
    {
        DB::transaction(function () use ($team) {
            $team->status = 'disbanded';
            $team->save();

            // Additional logic like invalidating invitations or notifying members
            $team->delete();
        });
    }
}
