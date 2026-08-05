<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\StateMachines;

use Ospp\Protocol\Enums\BayStatus;
use Ospp\Protocol\Enums\EffectedBy;
use Ospp\Protocol\StateMachines\BayTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit cover for {@see BayTransitions}. The exhaustive 7x7 proof of the canonical
 * table lives in {@see \Ospp\Protocol\Tests\Contract\StateMachines\BayCanonicalTableContractTest};
 * this file covers the accessors and the shape of what they return.
 */
final class BayTransitionsTest extends TestCase
{
    private BayTransitions $machine;

    protected function setUp(): void
    {
        $this->machine = new BayTransitions();
    }

    #[Test]
    public function transitionCountIsTwentyForAStationAndTwentySixForAServer(): void
    {
        self::assertSame(20, $this->machine->transitionCount(EffectedBy::STATION));
        self::assertSame(26, $this->machine->transitionCount(EffectedBy::SERVER));
    }

    #[Test]
    public function canTransitionForAllValidStationTransitions(): void
    {
        $validTransitions = [
            // unknown -> five exits. `occupied` and `finishing` are the two the
            // bay-FSM arc added, for a station that rebooted mid-session and owes
            // a truthful post-boot report (spec 05-state-machines.md §2.3).
            [BayStatus::UNKNOWN, BayStatus::AVAILABLE],
            [BayStatus::UNKNOWN, BayStatus::FAULTED],
            [BayStatus::UNKNOWN, BayStatus::UNAVAILABLE],
            [BayStatus::UNKNOWN, BayStatus::OCCUPIED],
            [BayStatus::UNKNOWN, BayStatus::FINISHING],
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
                $this->machine->canTransition($from, $to, EffectedBy::STATION),
                "Expected transition {$from->value} -> {$to->value} to be valid",
            );
        }
    }

    #[Test]
    public function canTransitionReturnsFalseForInvalidTransitions(): void
    {
        $invalidTransitions = [
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
            foreach (EffectedBy::cases() as $party) {
                self::assertFalse(
                    $this->machine->canTransition($from, $to, $party),
                    "Expected transition {$from->value} -> {$to->value} to be invalid for {$party->value}",
                );
            }
        }
    }

    /**
     * A station MUST NOT implement the `Server` rows; a server implements them.
     */
    #[Test]
    public function transitionsIntoUnknownAreServerOnly(): void
    {
        foreach (BayStatus::cases() as $from) {
            if ($from === BayStatus::UNKNOWN) {
                continue;
            }

            self::assertFalse(
                $this->machine->canTransition($from, BayStatus::UNKNOWN, EffectedBy::STATION),
                "station MUST NOT effect {$from->value} -> unknown",
            );
            self::assertTrue(
                $this->machine->canTransition($from, BayStatus::UNKNOWN, EffectedBy::SERVER),
                "server infers {$from->value} -> unknown on connection loss",
            );
        }
    }

    #[Test]
    public function allowedTransitionsForEachState(): void
    {
        $expectations = [
            [BayStatus::UNKNOWN, [BayStatus::AVAILABLE, BayStatus::FAULTED, BayStatus::UNAVAILABLE, BayStatus::OCCUPIED, BayStatus::FINISHING]],
            [BayStatus::AVAILABLE, [BayStatus::RESERVED, BayStatus::OCCUPIED, BayStatus::FAULTED, BayStatus::UNAVAILABLE]],
            [BayStatus::RESERVED, [BayStatus::OCCUPIED, BayStatus::AVAILABLE, BayStatus::FAULTED]],
            [BayStatus::OCCUPIED, [BayStatus::FINISHING, BayStatus::FAULTED]],
            [BayStatus::FINISHING, [BayStatus::AVAILABLE, BayStatus::FAULTED]],
            [BayStatus::FAULTED, [BayStatus::AVAILABLE, BayStatus::UNAVAILABLE]],
            [BayStatus::UNAVAILABLE, [BayStatus::AVAILABLE, BayStatus::FAULTED]],
        ];

        foreach ($expectations as [$from, $expectedTargets]) {
            $allowed = $this->machine->allowedTransitions($from, EffectedBy::STATION);
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
        $table = $this->machine->getTransitionTable(EffectedBy::STATION);

        self::assertCount(7, $table);
        foreach (BayStatus::cases() as $state) {
            self::assertArrayHasKey($state->value, $table);
        }

        self::assertSame(['available', 'faulted', 'unavailable', 'occupied', 'finishing'], $table['unknown']);
        self::assertSame(['reserved', 'occupied', 'faulted', 'unavailable'], $table['available']);
        self::assertSame(['occupied', 'available', 'faulted'], $table['reserved']);
        self::assertSame(['finishing', 'faulted'], $table['occupied']);
        self::assertSame(['available', 'faulted'], $table['finishing']);
        self::assertSame(['available', 'unavailable'], $table['faulted']);
        self::assertSame(['available', 'faulted'], $table['unavailable']);
    }

    #[Test]
    public function serverTableAddsUnknownAsATargetAndNothingElse(): void
    {
        $station = $this->machine->getTransitionTable(EffectedBy::STATION);
        $server = $this->machine->getTransitionTable(EffectedBy::SERVER);

        foreach ($server as $from => $targets) {
            $added = array_values(array_diff($targets, $station[$from]));

            self::assertSame(
                $from === 'unknown' ? [] : ['unknown'],
                $added,
                "server table adds only 'unknown' as a target, from {$from}",
            );
        }
    }
}
