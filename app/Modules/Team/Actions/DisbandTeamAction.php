<?php

declare(strict_types=1);

namespace App\Modules\Team\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Team\Events\TeamDeleted;
use App\Modules\Team\Models\Team;
use Illuminate\Support\Facades\DB;

class DisbandTeamAction
{
    public function execute(Team $team, User $disbandedBy): void
    {
        DB::transaction(function () use ($team, $disbandedBy) {
            $teamId = $team->id;
            $team->status = 'disbanded';
            $team->save();
            $team->delete();

            TeamDeleted::dispatch($teamId, $disbandedBy->id);
        });
    }
}
