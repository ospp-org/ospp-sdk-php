<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Integration;

use Ospp\Protocol\Enums\BayStatus;
use Ospp\Protocol\Enums\EffectedBy;
use Ospp\Protocol\Enums\DiagnosticsState;
use Ospp\Protocol\Enums\DiagnosticsStatus;
use Ospp\Protocol\Enums\FirmwareUpdateStatus;
use Ospp\Protocol\Enums\ReservationStatus;
use Ospp\Protocol\Enums\SessionStatus;
use Ospp\Protocol\StateMachines\BayTransitions;
use Ospp\Protocol\StateMachines\DiagnosticsTransitions;
use Ospp\Protocol\StateMachines\FirmwareTransitions;
use Ospp\Protocol\StateMachines\ReservationTransitions;
use Ospp\Protocol\StateMachines\SessionTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StateMachineEnumConsistencyTest extends TestCase
{
    #[Test]
    public function BayTransitions_keys_match_BayStatus_values(): void
    {
        $transitions = new BayTransitions();
        $table = $transitions->getTransitionTable(EffectedBy::STATION);
        $tableKeys = array_keys($table);

        $enumValues = array_map(
            fn (BayStatus $status) => $status->value,
            BayStatus::cases(),
        );

        sort($tableKeys);
        sort($enumValues);

        self::assertSame($enumValues, $tableKeys);
    }

    #[Test]
    public function BayTransitions_targets_are_valid_BayStatus_values(): void
    {
        $transitions = new BayTransitions();
        // SERVER is the superset -- all twenty-six rows, so this validates the
        // `Server` targets too (spec/05-state-machines.md §2.3).
        $table = $transitions->getTransitionTable(EffectedBy::SERVER);

        foreach ($table as $source => $targets) {
            foreach ($targets as $target) {
                $status = BayStatus::tryFrom($target);

                self::assertNotNull(
                    $status,
                    "BayTransitions target '{$target}' from source '{$source}' is not a valid BayStatus value",
                );
            }
        }
    }

    #[Test]
    public function SessionTransitions_keys_match_SessionStatus_values(): void
    {
        $transitions = new SessionTransitions();
        $table = $transitions->getTransitionTable();
        $tableKeys = array_keys($table);

        $enumValues = array_map(
            fn (SessionStatus $status) => $status->value,
            SessionStatus::cases(),
        );

        sort($tableKeys);
        sort($enumValues);

        self::assertSame($enumValues, $tableKeys);
    }

    #[Test]
    public function SessionTransitions_timeout_keys_subset_of_SessionStatus(): void
    {
        $transitions = new SessionTransitions();
        $timeoutTable = $transitions->getTimeoutTable();

        foreach (array_keys($timeoutTable) as $key) {
            $status = SessionStatus::tryFrom($key);

            self::assertNotNull(
                $status,
                "SessionTransitions timeout key '{$key}' is not a valid SessionStatus value",
            );
        }
    }

    #[Test]
    public function FirmwareTransitions_keys_match_FirmwareUpdateStatus_values(): void
    {
        $transitions = new FirmwareTransitions();
        $table = $transitions->getTransitionTable();
        $tableKeys = array_keys($table);

        $enumValues = array_map(
            fn (FirmwareUpdateStatus $status) => $status->value,
            FirmwareUpdateStatus::cases(),
        );

        sort($tableKeys);
        sort($enumValues);

        self::assertSame($enumValues, $tableKeys);
    }

    // The two diagnostics tests that lived here asserted that
    // DiagnosticsTransitions and DiagnosticsStatus::allowedTransitions() were the
    // same table, keyed by the same enum. Both assertions were true and both were
    // holding the wrong thing in place.
    //
    // spec/05-state-machines.md §8.5 divides them: DiagnosticsTransitions is the
    // STATION's machine over DiagnosticsState (idle/collecting/uploading/uploaded/
    // failed, seven edges, nothing terminal); DiagnosticsStatus is a SERVER's record
    // of one requested upload, which legitimately carries `pending` and whose
    // outcomes ARE terminal because a row is closed by its outcome. Requiring them
    // to be identical is what kept `pending` inside the machine and kept the
    // outcomes terminal there, and §8.5 forbids the comparison outright: "a
    // conformance test MUST NOT assert a transition of the server's record against
    // §8.3."
    //
    // The station machine is pinned by DiagnosticsCanonicalTableContractTest. What
    // survives here is the property that still holds — the machine's table is keyed
    // by exactly the states of its own enum.

    #[Test]
    public function DiagnosticsTransitions_keys_match_DiagnosticsState_values(): void
    {
        $transitions = new DiagnosticsTransitions();
        $tableKeys = array_keys($transitions->getTransitionTable());

        $enumValues = array_map(
            fn (DiagnosticsState $state) => $state->value,
            DiagnosticsState::cases(),
        );

        sort($tableKeys);
        sort($enumValues);

        self::assertSame($enumValues, $tableKeys);
    }

    #[Test]
    public function DiagnosticsState_and_DiagnosticsStatus_are_deliberately_different(): void
    {
        // The guard against re-merging them. If a later change makes the record
        // enum and the station enum agree on membership, one of the two has lost
        // the distinction §8.5 draws, and this fails before a table can follow.
        $stationStates = array_map(
            fn (DiagnosticsState $s) => $s->value,
            DiagnosticsState::cases(),
        );
        $recordStates = array_map(
            fn (DiagnosticsStatus $s) => $s->value,
            DiagnosticsStatus::cases(),
        );

        self::assertNotSame(
            $recordStates,
            $stationStates,
            '§8.5: the station machine and the server record are different objects',
        );
        self::assertContains('pending', $recordStates, 'the record has the pre-response interval');
        self::assertNotContains('pending', $stationStates, 'the station does not');
        self::assertContains('idle', $stationStates, 'the station has an idle state');
        self::assertNotContains('idle', $recordStates, 'a record row is never idle');
    }

    #[Test]
    public function ReservationTransitions_keys_match_ReservationStatus_values(): void
    {
        $transitions = new ReservationTransitions();
        $table = $transitions->getTransitionTable();
        $tableKeys = array_keys($table);

        $enumValues = array_map(
            fn (ReservationStatus $status) => $status->value,
            ReservationStatus::cases(),
        );

        sort($tableKeys);
        sort($enumValues);

        self::assertSame($enumValues, $tableKeys);
    }
}
