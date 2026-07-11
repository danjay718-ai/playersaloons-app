<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\ComplianceBlock;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class ApplyComplianceBlockAction
{
    public function execute(User $target, User $actor, string $category, string $reason, ?Carbon $expiresAt = null): ComplianceBlock
    {
        if (! $actor->hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
            throw new AuthorizationException('Only admins may apply compliance blocks.');
        }

        if ($target->hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
            throw new LogicException('Staff administrator accounts cannot be blacklisted.');
        }

        if ($target->complianceBlocks()->active()->exists()) {
            throw new LogicException('This user already has an active compliance block.');
        }

        return DB::transaction(function () use ($target, $actor, $category, $reason, $expiresAt): ComplianceBlock {
            $block = $target->complianceBlocks()->create([
                'uuid' => Str::uuid()->toString(),
                'created_by' => $actor->id,
                'category' => $category,
                'reason' => $reason,
                'expires_at' => $expiresAt,
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($target)
                ->withProperties(['compliance_block_id' => $block->id, 'category' => $category, 'reason' => $reason])
                ->log('compliance_block_applied');

            return $block;
        });
    }
}
