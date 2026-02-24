<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Contract\StateMachines;

use OneStopPay\OsppProtocol\Enums\SessionStatus;
use OneStopPay\OsppProtocol\StateMachines\SessionTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionTransitionsContractTest extends TestCase
{
    private SessionTransitions $transitions;

    protected function setUp(): void
    {
        $this->transitions = new SessionTransitions();
    }

    #[Test]
    public function transition_count_is_exactly_8(): void
    {
        self::assertSame(8, $this->transitions->transitionCount());
    }

    #[Test]
    public function self_transitions_are_never_allowed(): void
    {
        foreach (SessionStatus::cases() as $status) {
            self::assertFalse(
                $this->transitions->canTransition($status, $status),
                "Self-transition should not be allowed for {$status->value}",
            );
        }
    }

    #[Test]
    public function complete_6x6_transition_matrix(): void
    {
        $valid = 0;
        $invalid = 0;

        foreach (SessionStatus::cases() as $from) {
            foreach (SessionStatus::cases() as $to) {
                if ($this->transitions->canTransition($from, $to)) {
                    $valid++;
                } else {
                    $invalid++;
                }
            }
        }

        self::assertSame(8, $valid, 'Expected exactly 8 valid transitions');
        self::assertSame(28, $invalid, 'Expected exactly 28 invalid transitions');
        self::assertSame(36, $valid + $invalid, 'Total pairs should be 36 (6x6)');
    }

    #[Test]
    public function every_non_terminal_can_reach_failed(): void
    {
        $nonTerminal = [
            SessionStatus::PENDING,
            SessionStatus::AUTHORIZED,
            SessionStatus::ACTIVE,
            SessionStatus::STOPPING,
        ];

        foreach ($nonTerminal as $status) {
            self::assertTrue(
                $this->transitions->canTransition($status, SessionStatus::FAILED),
                "{$status->value} should be able to transition to FAILED",
            );
        }
    }

    #[Test]
    public function timeouts_pinned(): void
    {
        self::assertSame(30, $this->transitions->getTimeout(SessionStatus::PENDING));
        self::assertSame(30, $this->transitions->getTimeout(SessionStatus::AUTHORIZED));
        self::assertSame(3600, $this->transitions->getTimeout(SessionStatus::ACTIVE));
        self::assertSame(30, $this->transitions->getTimeout(SessionStatus::STOPPING));
    }

    #[Test]
    public function terminal_states_have_null_timeout(): void
    {
        self::assertNull($this->transitions->getTimeout(SessionStatus::COMPLETED));
        self::assertNull($this->transitions->getTimeout(SessionStatus::FAILED));
    }

    #[Test]
    public function happy_path_sequence(): void
    {
        $sequence = [
            [SessionStatus::PENDING, SessionStatus::AUTHORIZED],
            [SessionStatus::AUTHORIZED, SessionStatus::ACTIVE],
            [SessionStatus::ACTIVE, SessionStatus::STOPPING],
            [SessionStatus::STOPPING, SessionStatus::COMPLETED],
        ];

        foreach ($sequence as [$from, $to]) {
            self::assertTrue(
                $this->transitions->canTransition($from, $to),
                "{$from->value} -> {$to->value} should be valid in the happy path",
            );
        }
    }
}
