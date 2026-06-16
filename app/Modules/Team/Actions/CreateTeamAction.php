<?php

declare(strict_types=1);

namespace App\Modules\Team\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Team\Events\TeamCreated;
use App\Modules\Team\Models\Team;
use App\Modules\Team\Models\TeamMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTeamAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $captain): Team
    {
        return DB::transaction(function () use ($data, $captain) {
            /** @var Team $team */
            $team = Team::create([
                'uuid' => Str::uuid()->toString(),
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::random(6),
                'logo_path' => $data['logo_path'] ?? null,
                'captain_user_id' => $captain->id,
                'status' => 'active',
            ]);

            TeamMember::create([
                'team_id' => $team->id,
                'user_id' => $captain->id,
                'role' => 'captain',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            TeamCreated::dispatch($team->id, $team->name, $captain->id);

            return $team;
        });
    }
}
