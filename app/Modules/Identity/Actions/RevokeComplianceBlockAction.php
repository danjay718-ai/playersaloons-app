<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\ComplianceBlock;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

class RevokeComplianceBlockAction
{
    public function execute(ComplianceBlock $block, User $actor, string $reason): void
    {
        if (! $actor->hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
            throw new AuthorizationException('Only admins may revoke compliance blocks.');
        }

        if ($block->revoked_at !== null) {
            throw new LogicException('This compliance block has already been revoked.');
        }

        DB::transaction(function () use ($block, $actor, $reason): void {
            $block->update([
                'revoked_by' => $actor->id,
                'revoked_at' => now(),
                'revocation_reason' => $reason,
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($block->user)
                ->withProperties(['compliance_block_id' => $block->id, 'reason' => $reason])
                ->log('compliance_block_revoked');
        });
    }
}
