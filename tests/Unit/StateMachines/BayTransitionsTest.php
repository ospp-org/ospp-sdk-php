<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Unit\StateMachines;

use OneStopPay\OsppProtocol\Enums\BayStatus;
use OneStopPay\OsppProtocol\StateMachines\BayTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BayTransitionsTest extends TestCase
{
    private BayTransitions $machine;

    protected function setUp(): void
    {
        $this->machine = new BayTransitions();
    }

    #[Test]
    public function transitionCountReturnsEighteen(): void
    {
        self::assertSame(18, $this->machine->transitionCount());
    }

    #[Test]
    public function canTransitionForAllValidTransitions(): void
    {
        $validTransitions = [
            // unknown ->
            [BayStatus::UNKNOWN, BayStatus::AVAILABLE],
            [BayStatus::UNKNOWN, BayStatus::FAULTED],
            [BayStatus::UNKNOWN, BayStatus::UNAVAILABLE],
            // available ->
            [BayStatus::AVAILABLE, BayStatus::RESERVED],
            [BayStatus::AVAILABLE, BayStatus::OCCUPIED],
            [BayStatus::AVAILABLE, BayStatus::FAULTED],
            [BayStatus::AVAILABLE, BayStatus::UNAVAILABLE],
            // reserved ->
            [BayStatus::RESERVED, BayStatus::AVAILABLE],
            [BayStatus::RESERVED, BayStatus::OCCUPIED],
            [BayStatus::RESERVED, BayStatus::FAULTED],
            // occupied ->
            [BayStatus::OCCUPIED, BayStatus::FINISHING],
            [BayStatus::OCCUPIED, BayStatus::FAULTED],
            // finishing ->
            [BayStatus::FINISHING, BayStatus::AVAILABLE],
            [BayStatus::FINISHING, BayStatus::FAULTED],
            // faulted ->
            [BayStatus::FAULTED, BayStatus::AVAILABLE],
            [BayStatus::FAULTED, BayStatus::UNAVAILABLE],
            // unavailable ->
            [BayStatus::UNAVAILABLE, BayStatus::AVAILABLE],
            [BayStatus::UNAVAILABLE, BayStatus::FAULTED],
        ];

        foreach ($validTransitions as [$from, $to]) {
            self::assertTrue(
                $this->machine->canTransition($from, $to),
                "Expected transition {$from->value} -> {$to->value} to be valid",
            );
        }
    }

    #[Test]
    public function canTransitionReturnsFalseForInvalidTransitions(): void
    {
        $invalidTransitions = [
            [BayStatus::UNKNOWN, BayStatus::OCCUPIED],
            [BayStatus::UNKNOWN, BayStatus::RESERVED],
            [BayStatus::AVAILABLE, BayStatus::FINISHING],
            [BayStatus::RESERVED, BayStatus::UNAVAILABLE],
            [BayStatus::OCCUPIED, BayStatus::AVAILABLE],
            [BayStatus::OCCUPIED, BayStatus::RESERVED],
            [BayStatus::FINISHING, BayStatus::OCCUPIED],
            [BayStatus::FAULTED, BayStatus::RESERVED],
            [BayStatus::UNAVAILABLE, BayStatus::OCCUPIED],
        ];

        foreach ($invalidTransitions as [$from, $to]) {
            self::assertFalse(
                $this->machine->canTransition($from, $to),
                "Expected transition {$from->value} -> {$to->value} to be invalid",
            );
        }
    }

    #[Test]
    public function allowedTransitionsForEachState(): void
    {
        $expectations = [
            [BayStatus::UNKNOWN, [BayStatus::AVAILABLE, BayStatus::FAULTED, BayStatus::UNAVAILABLE]],
            [BayStatus::AVAILABLE, [BayStatus::RESERVED, BayStatus::OCCUPIED, BayStatus::FAULTED, BayStatus::UNAVAILABLE]],
            [BayStatus::RESERVED, [BayStatus::AVAILABLE, BayStatus::OCCUPIED, BayStatus::FAULTED]],
            [BayStatus::OCCUPIED, [BayStatus::FINISHING, BayStatus::FAULTED]],
            [BayStatus::FINISHING, [BayStatus::AVAILABLE, BayStatus::FAULTED]],
            [BayStatus::FAULTED, [BayStatus::AVAILABLE, BayStatus::UNAVAILABLE]],
            [BayStatus::UNAVAILABLE, [BayStatus::AVAILABLE, BayStatus::FAULTED]],
        ];

        foreach ($expectations as [$from, $expectedTargets]) {
            $allowed = $this->machine->allowedTransitions($from);
            self::assertSame(
                $expectedTargets,
                $allowed,
                "Allowed transitions for {$from->value} do not match",
            );
        }
    }

    #[Test]
    public function getTransitionTableReturnsFullTable(): void
    {
        $table = $this->machine->getTransitionTable();

        self::assertCount(7, $table);
        self::assertArrayHasKey('unknown', $table);
        self::assertArrayHasKey('available', $table);
        self::assertArrayHasKey('reserved', $table);
        self::assertArrayHasKey('occupied', $table);
        self::assertArrayHasKey('finishing', $table);
        self::assertArrayHasKey('faulted', $table);
        self::assertArrayHasKey('unavailable', $table);

        // Verify specific entries
        self::assertSame(['available', 'faulted', 'unavailable'], $table['unknown']);
        self::assertSame(['reserved', 'occupied', 'faulted', 'unavailable'], $table['available']);
        self::assertSame(['available', 'occupied', 'faulted'], $table['reserved']);
        self::assertSame(['finishing', 'faulted'], $table['occupied']);
        self::assertSame(['available', 'faulted'], $table['finishing']);
        self::assertSame(['available', 'unavailable'], $table['faulted']);
        self::assertSame(['available', 'faulted'], $table['unavailable']);
    }
}
