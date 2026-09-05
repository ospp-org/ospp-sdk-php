<?php

declare(strict_types=1);

namespace Ospp\Protocol\StateMachines;

use Ospp\Protocol\Enums\SessionStatus;

final class SessionTransitions
{
    /**
     * The nine `(from, to)` pairs of `05-state-machines.md` §3.3.
     *
     * `active -> completed` is the AUTONOMOUS stop, and it was missing here until 0.32.0
     * of this SDK. §3.3 asserts it in four places for three reasons the station reports
     * without ever being asked to stop -- `Local` (the user pressed the physical Stop
     * button on the bay), `LocalOutOfCredit`, and `OperatorStopped` -- and the §3.1 diagram
     * draws the edge. Without it, three of the seven `SessionEndReason` values were
     * unrepresentable in this machine.
     *
     * `stopping` is NOT a legal substitute for it, and that is why this is a fix rather
     * than a preference. `stopping` means the server sent StopService and is awaiting
     * confirmation; the reference server reached the right end state by writing it anyway,
     * in a separate un-wrapped write, and its own timeout sweep reads a persisted
     * `stopping` as "the station never confirmed the stop" and settles it as
     * `StopAckLost` on a different billing arm. A crash between the two writes therefore
     * settled a session the station HAD reported, with a real `actualDurationSeconds`, as
     * one it never answered.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending' => ['authorized', 'failed'],
        'authorized' => ['active', 'failed'],
        'active' => ['stopping', 'completed', 'failed'],
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
