<?php

declare(strict_types=1);

namespace App\Shared\StateMachines;

use App\Shared\Exceptions\InvalidStateTransitionException;
use BackedEnum;
use InvalidArgumentException;

abstract class AbstractStateMachine
{
    /**
     * Define valid transitions as: from_value => [to_value, ...]
     *
     * @return array<string, string[]>
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
        $fromValue = $this->stateValue($from);
        $toValue = $this->stateValue($to);

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
            $fromValue = $this->stateValue($from);
            $toValue = $this->stateValue($to);

            throw new InvalidStateTransitionException($fromValue, $toValue, $this->machineName());
        }
    }

    private function stateValue(string|BackedEnum $state): string
    {
        $value = $state instanceof BackedEnum ? $state->value : $state;

        if (! is_string($value)) {
            throw new InvalidArgumentException('State machine values must be strings.');
        }

        return $value;
    }
}
