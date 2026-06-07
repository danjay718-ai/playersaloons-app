<?php

declare(strict_types=1);

namespace App\Shared\StateMachines;

use App\Shared\Exceptions\InvalidStateTransitionException;
use BackedEnum;

abstract class AbstractStateMachine
{
    /**
     * Define valid transitions as: from_value => [to_value, ...]
     *
     * @var array<string, string[]>
     */
    abstract protected function transitions(): array;

    /**
     * Return the short class name used in exception messages.
     */
    protected function machineName(): string
    {
        return class_basename(static::class);
    }

    /**
     * Check whether a transition from $from → $to is valid.
     */
    public function can(string|BackedEnum $from, string|BackedEnum $to): bool
    {
        $fromValue = $from instanceof BackedEnum ? $from->value : $from;
        $toValue   = $to instanceof BackedEnum ? $to->value : $to;

        return in_array($toValue, $this->transitions()[$fromValue] ?? [], true);
    }

    /**
     * Assert a transition is valid or throw InvalidStateTransitionException.
     *
     * @throws InvalidStateTransitionException
     */
    public function assertValidTransition(string|BackedEnum $from, string|BackedEnum $to): void
    {
        if (! $this->can($from, $to)) {
            $fromValue = $from instanceof BackedEnum ? $from->value : $from;
            $toValue   = $to instanceof BackedEnum ? $to->value : $to;

            throw new InvalidStateTransitionException($fromValue, $toValue, $this->machineName());
        }
    }
}
