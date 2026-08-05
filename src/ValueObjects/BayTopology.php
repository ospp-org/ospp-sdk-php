<?php

declare(strict_types=1);

namespace Ospp\Protocol\ValueObjects;

/**
 * One bay of a station's physical topology: its ordinal, and the program
 * ordinals that bay can run.
 *
 * This is the unit the station re-declares in every BootNotification (`bays[]`)
 * and the unit the server echoes in a `3018 TOPOLOGY_MISMATCH` `details`
 * object — one definition, not two copies
 * (spec schemas/common/bay-topology.schema.json).
 *
 * **Labels are not part of it.** They are declared once, at provisioning, so an
 * operator can tell which physical program is which when building the service
 * bindings. They are descriptive and are never compared: a corrected typo in a
 * firmware constant MUST NOT put a station into `Pending`.
 *
 * **Programs are physical and station-owned; services are commercial and
 * server-minted.** A program is a complete station-defined operation ("simple
 * wash"), identified within its bay by `programNumber`. It is not a composable
 * element — "brush" and "water" are not programs. The binding between a service
 * and a program is created on the SERVER by an operator; the station never
 * originates it (01-architecture.md §4.2).
 *
 * Neither set need be dense: a station whose bay 2 was never fitted declares
 * `{1, 3}` and asserts nothing about a bay 2, and a server MUST NOT reject a
 * declaration for being non-dense.
 */
final class BayTopology implements \JsonSerializable
{
    /** 01-architecture.md §4.2: "MUST NOT exceed **64**". */
    public const MAX_BAYS_PER_STATION = 64;

    /** 01-architecture.md §4.2: "The maximum number of programs per bay MUST NOT exceed **32**." */
    public const MAX_PROGRAMS_PER_BAY = 32;

    /** @var list<int> */
    public readonly array $programNumbers;

    /**
     * `array<int, int>` and not `list<int>`: a caller decoding a payload may
     * hand us a non-list (a filtered array keeps its keys), and normalising it
     * here is what makes the readonly property's `list<int>` true.
     *
     * @param  array<int, int>  $programNumbers
     */
    public function __construct(
        public readonly int $bayNumber,
        array $programNumbers,
    ) {
        if ($bayNumber < 1 || $bayNumber > self::MAX_BAYS_PER_STATION) {
            throw new \InvalidArgumentException(
                "bayNumber must be 1..".self::MAX_BAYS_PER_STATION.", got {$bayNumber}",
            );
        }

        if ($programNumbers === []) {
            // minItems: 1. A bay that can run nothing is not a bay; a program
            // that cannot run right now is declared present-but-unavailable.
            throw new \InvalidArgumentException("bay {$bayNumber} must declare at least one program");
        }

        if (count($programNumbers) > self::MAX_PROGRAMS_PER_BAY) {
            throw new \InvalidArgumentException(
                "bay {$bayNumber} declares more than ".self::MAX_PROGRAMS_PER_BAY.' programs',
            );
        }

        if (count($programNumbers) !== count(array_unique($programNumbers))) {
            throw new \InvalidArgumentException("bay {$bayNumber} declares a duplicate programNumber");
        }

        foreach ($programNumbers as $programNumber) {
            if ($programNumber < 1 || $programNumber > self::MAX_PROGRAMS_PER_BAY) {
                throw new \InvalidArgumentException(
                    "programNumber must be 1..".self::MAX_PROGRAMS_PER_BAY.", got {$programNumber}",
                );
            }
        }

        $this->programNumbers = array_values($programNumbers);
    }

    /**
     * @param  array{bayNumber: int, programNumbers: array<int, int>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['bayNumber'], $data['programNumbers']);
    }

    /**
     * Equality compares `programNumbers` as a SET.
     *
     * "Order carries no meaning; the server compares this as a SET"
     * (bay-topology.schema.json). A station that re-orders its declaration
     * between boots has not changed its hardware, and must not be held out of
     * service for it.
     */
    public function equals(self $other): bool
    {
        if ($this->bayNumber !== $other->bayNumber) {
            return false;
        }

        $mine = $this->programNumbers;
        $theirs = $other->programNumbers;
        sort($mine);
        sort($theirs);

        return $mine === $theirs;
    }

    /**
     * Compare two program-ordinal sets the way the server does — as SETS.
     *
     * A station that re-orders its declaration between boots has not changed
     * its hardware, and must not be held out of service for it.
     *
     * @param  array<int, int>  $a
     * @param  array<int, int>  $b
     */
    public static function sameProgramSet(array $a, array $b): bool
    {
        $a = array_values($a);
        $b = array_values($b);
        sort($a);
        sort($b);

        return $a === $b;
    }

    /**
     * Does a declared topology match a provisioned one?
     *
     * 05-state-machines.md §1.5: "The server compares that declaration, as a set
     * in both directions, against the topology recorded for the station at
     * provisioning. [...] The mismatch is symmetric: a bay or a program ordinal
     * present on one side and absent on the other is a mismatch in either
     * direction."
     *
     * A mismatch is `3018 TOPOLOGY_MISMATCH` on a **`Pending`** response, never
     * `Rejected` — `Pending` keeps the command channel open so an operator can
     * repair the disagreement. There is no first-boot exemption.
     *
     * @param  iterable<self>  $expected  the topology recorded at provisioning
     * @param  iterable<self>  $declared  the topology this BootNotification declared
     */
    public static function topologyMatches(iterable $expected, iterable $declared): bool
    {
        $byNumber = [];
        $declaredCount = 0;
        foreach ($declared as $bay) {
            $byNumber[$bay->bayNumber] = $bay;
            ++$declaredCount;
        }

        $expectedCount = 0;
        foreach ($expected as $bay) {
            ++$expectedCount;

            if (! isset($byNumber[$bay->bayNumber])) {
                return false;
            }

            if (! $bay->equals($byNumber[$bay->bayNumber])) {
                return false;
            }
        }

        // Symmetric: a bay present only in the declaration is a mismatch too.
        return $expectedCount === $declaredCount;
    }

    /**
     * @return array{bayNumber: int, programNumbers: list<int>}
     */
    public function jsonSerialize(): array
    {
        return [
            'bayNumber' => $this->bayNumber,
            'programNumbers' => $this->programNumbers,
        ];
    }
}
