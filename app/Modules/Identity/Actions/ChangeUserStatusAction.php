<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\UserSuspended;
use App\Modules\Identity\Events\UserUnsuspended;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\StateMachines\UserStateMachine;
use App\Shared\Enums\UserStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class ChangeUserStatusAction
{
    public function __construct(private readonly UserStateMachine $stateMachine) {}

    public function execute(User $target, User $actor, UserStatus $status, string $reason, ?UserStatus $expectedStatus = null): void
    {
        if ($actor->is($target) || $target->hasRole('SUPER_ADMIN')
            || ! $actor->hasAnyRole(['ADMIN', 'SUPER_ADMIN'])
            || ! $actor->can('changeStatus', $target)
            || ! $actor->can($status === UserStatus::ACTIVE ? 'users.unsuspend' : 'users.suspend')) {
            throw new AuthorizationException;
        }
        $reason = trim($reason);
        Validator::make(['statusReason' => $reason], ['statusReason' => ['required', 'string', 'min:5', 'max:255']])->validate();

        DB::transaction(function () use ($target, $actor, $status, $reason, $expectedStatus): void {
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);
            if ($locked->hasRole('SUPER_ADMIN') || ! $actor->can('changeStatus', $locked)) {
                throw new AuthorizationException;
            }
            $from = $locked->status;
            if ($expectedStatus !== null && $from !== $expectedStatus) {
                throw new InvalidStateTransitionException($from->value, $status->value, UserStateMachine::class);
            }
            $this->stateMachine->transition($locked, $status);
            $description = match ($status) {
                UserStatus::BANNED => 'user_banned',
                UserStatus::SUSPENDED => 'user_suspended',
                UserStatus::ACTIVE => $from === UserStatus::BANNED ? 'user_unbanned' : 'user_unsuspended',
            };
            activity()->causedBy($actor)->performedOn($locked)->withProperties([
                'from_status' => $from->value, 'to_status' => $status->value,
                'reason' => $reason, 'source' => 'admin',
            ])->log($description);

            // Preserve the existing suspension wallet lifecycle. A ban changes
            // account access; it does not release independent wallet restrictions.
            if ($status === UserStatus::SUSPENDED) {
                UserSuspended::dispatch($locked->id, $reason, $actor->id);
            } elseif ($status === UserStatus::ACTIVE && $from === UserStatus::SUSPENDED) {
                UserUnsuspended::dispatch($locked->id, $actor->id);
            }
        });
    }
}
