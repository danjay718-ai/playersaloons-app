<?php

declare(strict_types=1);

namespace App\Modules\Team\Actions;

use App\Modules\Team\Models\Team;

class UpdateTeamAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Team $team, array $data): Team
    {
        if (isset($data['name'])) {
            $team->name = $data['name'];
        }

        if (array_key_exists('logo_path', $data)) {
            $team->logo_path = $data['logo_path'];
        }

        $team->save();

        return $team;
    }
}
