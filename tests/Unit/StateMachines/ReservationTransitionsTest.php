<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Unit\StateMachines;

use OneStopPay\OsppProtocol\Enums\BayStatus;
use OneStopPay\OsppProtocol\Enums\ReservationStatus;
use OneStopPay\OsppProtocol\StateMachines\ReservationTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReservationTransitionsTest extends TestCase
{
    private ReservationTransitions $machine;

    protected function setUp(): void
    {
        $this->machine = new ReservationTransitions();
    }

    #[Test]
    public function transitionCountReturnsFive(): void
    {
        self::assertSame(5, $this->machine->transitionCount());
    }

    #[Test]
    public function canTransitionForAllValidTransitions(): void
    {
        $validTransitions = [
            [ReservationStatus::PENDING, ReservationStatus::CONFIRMED],
            [ReservationStatus::PENDING, ReservationStatus::CANCELLED],
            [ReservationStatus::CONFIRMED, ReservationStatus::ACTIVE],
            [ReservationStatus::CONFIRMED, ReservationStatus::EXPIRED],
            [ReservationStatus::CONFIRMED, ReservationStatus::CANCELLED],
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
        $terminalStates = [
            ReservationStatus::ACTIVE,
            ReservationStatus::EXPIRED,
            ReservationStatus::CANCELLED,
        ];

        foreach ($terminalStates as $terminal) {
            $allowed = $this->machine->allowedTransitions($terminal);
            self::assertSame(
                [],
                $allowed,
                "Terminal state {$terminal->value} should have no allowed transitions",
            );
        }

        foreach ($terminalStates as $terminal) {
            foreach (ReservationStatus::cases() as $target) {
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
            [ReservationStatus::PENDING, ReservationStatus::ACTIVE],
            [ReservationStatus::PENDING, ReservationStatus::EXPIRED],
            [ReservationStatus::CONFIRMED, ReservationStatus::PENDING],
        ];

        foreach ($invalidTransitions as [$from, $to]) {
            self::assertFalse(
                $this->machine->canTransition($from, $to),
                "Expected transition {$from->value} -> {$to->value} to be invalid",
            );
        }
    }

    #[Test]
    public function isValidTtlAcceptsValidRange(): void
    {
        // Valid: 1 through 15
        for ($ttl = 1; $ttl <= 15; $ttl++) {
            self::assertTrue(
                $this->machine->isValidTtl($ttl),
                "TTL {$ttl} should be valid",
            );
        }
    }

    #[Test]
    public function isValidTtlRejectsInvalidValues(): void
    {
        self::assertFalse($this->machine->isValidTtl(0), 'TTL 0 should be invalid');
        self::assertFalse($this->machine->isValidTtl(-1), 'TTL -1 should be invalid');
        self::assertFalse($this->machine->isValidTtl(16), 'TTL 16 should be invalid');
        self::assertFalse($this->machine->isValidTtl(100), 'TTL 100 should be invalid');
    }

    #[Test]
    public function getTtlConstraintsReturnsMinAndMax(): void
    {
        $constraints = $this->machine->getTtlConstraints();

        self::assertSame(['min' => 1, 'max' => 15], $constraints);
    }

    #[Test]
    public function validateBayForReservationAllowsAvailableBay(): void
    {
        $result = $this->machine->validateBayForReservation(BayStatus::AVAILABLE);

        self::assertTrue($result['allowed']);
        self::assertNull($result['errorCode']);
        self::assertNull($result['errorText']);
    }

    #[Test]
    public function validateBayForReservationRejectsReservedBay(): void
    {
        $result = $this->machine->validateBayForReservation(BayStatus::RESERVED);

        self::assertFalse($result['allowed']);
        self::assertSame(3014, $result['errorCode']);
        self::assertSame('BAY_RESERVED', $result['errorText']);
    }

    #[Test]
    public function validateBayForReservationRejectsOccupiedBay(): void
    {
        $result = $this->machine->validateBayForReservation(BayStatus::OCCUPIED);

        self::assertFalse($result['allowed']);
        self::assertSame(3001, $result['errorCode']);
        self::assertSame('BAY_BUSY', $result['errorText']);
    }

    #[Test]
    public function validateBayForReservationRejectsFinishingBay(): void
    {
        $result = $this->machine->validateBayForReservation(BayStatus::FINISHING);

        self::assertFalse($result['allowed']);
        self::assertSame(3002, $result['errorCode']);
        self::assertSame('BAY_NOT_READY', $result['errorText']);
    }

    #[Test]
    public function validateBayForReservationRejectsFaultedBay(): void
    {
        $result = $this->machine->validateBayForReservation(BayStatus::FAULTED);

        self::assertFalse($result['allowed']);
        self::assertSame(3002, $result['errorCode']);
        self::assertSame('BAY_NOT_READY', $result['errorText']);
    }

    #[Test]
    public function validateBayForReservationRejectsUnavailableBay(): void
    {
        $result = $this->machine->validateBayForReservation(BayStatus::UNAVAILABLE);

        self::assertFalse($result['allowed']);
        self::assertSame(3011, $result['errorCode']);
        self::assertSame('BAY_MAINTENANCE', $result['errorText']);
    }

    #[Test]
    public function validateBayForReservationRejectsUnknownBay(): void
    {
        $result = $this->machine->validateBayForReservation(BayStatus::UNKNOWN);

        self::assertFalse($result['allowed']);
        self::assertSame(3002, $result['errorCode']);
        self::assertSame('BAY_NOT_READY', $result['errorText']);
    }
}
