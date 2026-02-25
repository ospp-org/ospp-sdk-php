<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\StateMachines;

use Ospp\Protocol\Enums\SessionStatus;
use Ospp\Protocol\StateMachines\SessionTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionTransitionsTest extends TestCase
{
    private SessionTransitions $machine;

    protected function setUp(): void
    {
        $this->machine = new SessionTransitions();
    }

    #[Test]
    public function transitionCountReturnsEight(): void
    {
        self::assertSame(8, $this->machine->transitionCount());
    }

    #[Test]
    public function canTransitionForAllValidTransitions(): void
    {
        $validTransitions = [
            [SessionStatus::PENDING, SessionStatus::AUTHORIZED],
            [SessionStatus::PENDING, SessionStatus::FAILED],
            [SessionStatus::AUTHORIZED, SessionStatus::ACTIVE],
            [SessionStatus::AUTHORIZED, SessionStatus::FAILED],
            [SessionStatus::ACTIVE, SessionStatus::STOPPING],
            [SessionStatus::ACTIVE, SessionStatus::FAILED],
            [SessionStatus::STOPPING, SessionStatus::COMPLETED],
            [SessionStatus::STOPPING, SessionStatus::FAILED],
        ];

        foreach ($validTransitions as [$from, $to]) {
            self::assertTrue(
                $this->machine->canTransition($from, $to),
                "Expected transition {$from->value} -> {$to->value} to be valid",
            );
        }
    }

    #[Test]
    public function terminalStatesHaveNoTransitions(): void
    {
        $terminalStates = [SessionStatus::COMPLETED, SessionStatus::FAILED];

        foreach ($terminalStates as $terminal) {
            $allowed = $this->machine->allowedTransitions($terminal);
            self::assertSame(
                [],
                $allowed,
                "Terminal state {$terminal->value} should have no allowed transitions",
            );
        }

        // Also verify canTransition returns false for all possible targets
        foreach ($terminalStates as $terminal) {
            foreach (SessionStatus::cases() as $target) {
                self::assertFalse(
                    $this->machine->canTransition($terminal, $target),
                    "Terminal state {$terminal->value} should not transition to {$target->value}",
                );
            }
        }
    }

    #[Test]
    public function canTransitionReturnsFalseForInvalidTransitions(): void
    {
        $invalidTransitions = [
            [SessionStatus::PENDING, SessionStatus::ACTIVE],
            [SessionStatus::PENDING, SessionStatus::STOPPING],
            [SessionStatus::PENDING, SessionStatus::COMPLETED],
            [SessionStatus::AUTHORIZED, SessionStatus::STOPPING],
            [SessionStatus::AUTHORIZED, SessionStatus::COMPLETED],
            [SessionStatus::ACTIVE, SessionStatus::AUTHORIZED],
            [SessionStatus::ACTIVE, SessionStatus::COMPLETED],
            [SessionStatus::STOPPING, SessionStatus::ACTIVE],
            [SessionStatus::STOPPING, SessionStatus::AUTHORIZED],
        ];

        foreach ($invalidTransitions as [$from, $to]) {
            self::assertFalse(
                $this->machine->canTransition($from, $to),
                "Expected transition {$from->value} -> {$to->value} to be invalid",
            );
        }
    }

    #[Test]
    public function getTimeoutReturnsCorrectValues(): void
    {
        self::assertSame(30, $this->machine->getTimeout(SessionStatus::PENDING));
        self::assertSame(30, $this->machine->getTimeout(SessionStatus::AUTHORIZED));
        self::assertSame(3600, $this->machine->getTimeout(SessionStatus::ACTIVE));
        self::assertSame(30, $this->machine->getTimeout(SessionStatus::STOPPING));
    }

    #[Test]
    public function getTimeoutReturnsNullForTerminalStates(): void
    {
        self::assertNull($this->machine->getTimeout(SessionStatus::COMPLETED));
        self::assertNull($this->machine->getTimeout(SessionStatus::FAILED));
    }

    #[Test]
    public function getTimeoutTableReturnsAllTimeouts(): void
    {
        $table = $this->machine->getTimeoutTable();

        self::assertSame([
            'pending' => 30,
            'authorized' => 30,
            'active' => 3600,
            'stopping' => 30,
        ], $table);

        // Terminal states must NOT be in the timeout table
        self::assertArrayNotHasKey('completed', $table);
        self::assertArrayNotHasKey('failed', $table);
    }
}
