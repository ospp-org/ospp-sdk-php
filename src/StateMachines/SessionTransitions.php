<?php

declare(strict_types=1);

namespace Ospp\Protocol\StateMachines;

use Ospp\Protocol\Enums\SessionStatus;

final class SessionTransitions
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['authorized', 'failed'],
        'authorized' => ['active', 'failed'],
        'active' => ['stopping', 'failed'],
        'stopping' => ['completed', 'failed'],
        'completed' => [],
        'failed' => [],
    ];

    /** @var array<string, int> Default timeouts in seconds */
    private const DEFAULT_TIMEOUTS = [
        'pending' => 30,
        'authorized' => 30,
        'active' => 3600,
        'stopping' => 30,
    ];

    public function canTransition(SessionStatus $from, SessionStatus $to): bool
    {
        $allowed = self::TRANSITIONS[$from->value];

        return in_array($to->value, $allowed, true);
    }

    /**
     * @return list<SessionStatus>
     */
    public function allowedTransitions(SessionStatus $from): array
    {
        $allowed = self::TRANSITIONS[$from->value];

        return array_map(
            fn (string $s) => SessionStatus::from($s),
            $allowed,
        );
    }

    /**
     * Get the default timeout for a session state.
     *
     * @return int|null Timeout in seconds, or null if no timeout applies
     */
    public function getTimeout(SessionStatus $status): ?int
    {
        return self::DEFAULT_TIMEOUTS[$status->value] ?? null;
    }

    /**
     * @return array<string, int>
     */
    public function getTimeoutTable(): array
    {
        return self::DEFAULT_TIMEOUTS;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getTransitionTable(): array
    {
        return self::TRANSITIONS;
    }

    public function transitionCount(): int
    {
        $count = 0;
        foreach (self::TRANSITIONS as $targets) {
            $count += count($targets);
        }

        return $count;
    }
}
