<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\StateMachines;

use Ospp\Protocol\Enums\BayStatus;
use Ospp\Protocol\Enums\EffectedBy;
use Ospp\Protocol\StateMachines\BayTransitions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The canonical bay transition table — spec/05-state-machines.md §2.3.
 *
 * "This is the canonical table. Nothing else in this specification restates it."
 *
 * The table has two parties in it and the `Effected by` column says which:
 * "Twenty `Station` rows by distinct `(from, to)` pair, and six `Server` rows —
 * twenty-six in all. [...] A station implements the `Station` rows. A server
 * implements all of them."
 *
 * The pair lists below are transcribed from §2.3 and are the SAME vectors the
 * sdk-ts mirror of this file asserts. Both SDKs are reference implementations
 * and a disagreement between them becomes a defect in every consumer — it has
 * once already, which is why this table now has one home.
 */
final class BayCanonicalTableContractTest extends TestCase
{
    private BayTransitions $transitions;

    protected function setUp(): void
    {
        $this->transitions = new BayTransitions();
    }

    /**
     * The twenty `Station` rows of §2.3, by distinct (from, to) pair.
     *
     * @return list<array{BayStatus, BayStatus}>
     */
    public static function stationPairs(): array
    {
        return [
            // Unknown has FIVE exits, not three. §2.3: "A station that reboots
            // mid-session MUST resume that session [...] `Occupied` and
            // `Finishing` are the two states a resumed session can leave a bay
            // in, and they are the two added."
            [BayStatus::UNKNOWN, BayStatus::AVAILABLE],
            [BayStatus::UNKNOWN, BayStatus::FAULTED],
            [BayStatus::UNKNOWN, BayStatus::UNAVAILABLE],
            [BayStatus::UNKNOWN, BayStatus::OCCUPIED],
            [BayStatus::UNKNOWN, BayStatus::FINISHING],

            [BayStatus::AVAILABLE, BayStatus::RESERVED],
            [BayStatus::AVAILABLE, BayStatus::OCCUPIED],
            [BayStatus::AVAILABLE, BayStatus::FAULTED],
            [BayStatus::AVAILABLE, BayStatus::UNAVAILABLE],

            [BayStatus::RESERVED, BayStatus::OCCUPIED],
            [BayStatus::RESERVED, BayStatus::AVAILABLE],
            [BayStatus::RESERVED, BayStatus::FAULTED],

            [BayStatus::OCCUPIED, BayStatus::FINISHING],
            [BayStatus::OCCUPIED, BayStatus::FAULTED],

            [BayStatus::FINISHING, BayStatus::AVAILABLE],
            [BayStatus::FINISHING, BayStatus::FAULTED],

            [BayStatus::FAULTED, BayStatus::AVAILABLE],
            [BayStatus::FAULTED, BayStatus::UNAVAILABLE],

            [BayStatus::UNAVAILABLE, BayStatus::AVAILABLE],
            // §2.3, Hardware error detected: "`Unavailable` is a source like any
            // other: a bay taken out of service can still develop a fault".
            [BayStatus::UNAVAILABLE, BayStatus::FAULTED],
        ];
    }

    /**
     * The six `Server` rows of §2.3 — every state to `Unknown`, on connection loss.
     *
     * @return list<array{BayStatus, BayStatus}>
     */
    public static function serverPairs(): array
    {
        return [
            [BayStatus::AVAILABLE, BayStatus::UNKNOWN],
            [BayStatus::RESERVED, BayStatus::UNKNOWN],
            [BayStatus::OCCUPIED, BayStatus::UNKNOWN],
            [BayStatus::FINISHING, BayStatus::UNKNOWN],
            [BayStatus::FAULTED, BayStatus::UNKNOWN],
            [BayStatus::UNAVAILABLE, BayStatus::UNKNOWN],
        ];
    }

    public function testStationRowCountIsTwenty(): void
    {
        self::assertCount(20, self::stationPairs(), 'the vector list itself');
        self::assertSame(20, $this->transitions->transitionCount(EffectedBy::STATION));
    }

    public function testServerRowCountIsSix(): void
    {
        self::assertCount(6, self::serverPairs(), 'the vector list itself');
        self::assertSame(
            6,
            $this->transitions->transitionCount(EffectedBy::SERVER)
                - $this->transitions->transitionCount(EffectedBy::STATION),
        );
    }

    #[DataProvider('stationPairs')]
    public function testStationMayEffectItsOwnRows(BayStatus $from, BayStatus $to): void
    {
        self::assertTrue(
            $this->transitions->canTransition($from, $to, EffectedBy::STATION),
            "station must be able to effect {$from->value} -> {$to->value}",
        );
    }

    /**
     * "A server implements all twenty-six" — the Station rows included.
     */
    #[DataProvider('stationPairs')]
    public function testServerAlsoImplementsTheStationRows(BayStatus $from, BayStatus $to): void
    {
        self::assertTrue(
            $this->transitions->canTransition($from, $to, EffectedBy::SERVER),
            "server must accept {$from->value} -> {$to->value}",
        );
    }

    /**
     * "a station needs no others and MUST NOT implement the `Server` six."
     */
    #[DataProvider('serverPairs')]
    public function testStationMustNotEffectAServerRow(BayStatus $from, BayStatus $to): void
    {
        self::assertFalse(
            $this->transitions->canTransition($from, $to, EffectedBy::STATION),
            "station MUST NOT effect {$from->value} -> {$to->value}",
        );
    }

    #[DataProvider('serverPairs')]
    public function testServerMayEffectTheInferredRows(BayStatus $from, BayStatus $to): void
    {
        self::assertTrue(
            $this->transitions->canTransition($from, $to, EffectedBy::SERVER),
            "server must be able to effect {$from->value} -> {$to->value}",
        );
    }

    public function testServerTableIsExactlyTwentySix(): void
    {
        self::assertSame(26, $this->transitions->transitionCount(EffectedBy::SERVER));
    }

    /**
     * Everything outside the union of the two lists is invalid for BOTH parties.
     *
     * §1.5 (chapter preamble): "A transition not listed for a machine is invalid,
     * and implementations MUST NOT perform one."
     */
    public function testEveryPairOutsideTheTableIsRefusedForBothParties(): void
    {
        $station = [];
        foreach (self::stationPairs() as [$from, $to]) {
            $station[$from->value.'>'.$to->value] = true;
        }
        $server = $station;
        foreach (self::serverPairs() as [$from, $to]) {
            $server[$from->value.'>'.$to->value] = true;
        }

        $checked = 0;
        foreach (BayStatus::cases() as $from) {
            foreach (BayStatus::cases() as $to) {
                $key = $from->value.'>'.$to->value;
                ++$checked;

                self::assertSame(
                    isset($station[$key]),
                    $this->transitions->canTransition($from, $to, EffectedBy::STATION),
                    "station {$key}",
                );
                self::assertSame(
                    isset($server[$key]),
                    $this->transitions->canTransition($from, $to, EffectedBy::SERVER),
                    "server {$key}",
                );
            }
        }

        self::assertSame(49, $checked, 'the full 7x7 matrix');
    }

    /**
     * No self-transition on either side. §2.5 is explicit that an `X -> X` is not
     * in the table, and status-notification.md §5 rule 2 turns on it.
     */
    public function testNoSelfTransitionForEitherParty(): void
    {
        foreach (BayStatus::cases() as $state) {
            self::assertFalse($this->transitions->canTransition($state, $state, EffectedBy::STATION));
            self::assertFalse($this->transitions->canTransition($state, $state, EffectedBy::SERVER));
        }
    }
}
