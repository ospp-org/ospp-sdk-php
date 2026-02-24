<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Integration;

use OneStopPay\OsppProtocol\Enums\BayStatus;
use OneStopPay\OsppProtocol\Enums\DiagnosticsStatus;
use OneStopPay\OsppProtocol\Enums\FirmwareUpdateStatus;
use OneStopPay\OsppProtocol\Enums\ReservationStatus;
use OneStopPay\OsppProtocol\Enums\SessionStatus;
use OneStopPay\OsppProtocol\StateMachines\BayTransitions;
use OneStopPay\OsppProtocol\StateMachines\DiagnosticsTransitions;
use OneStopPay\OsppProtocol\StateMachines\FirmwareTransitions;
use OneStopPay\OsppProtocol\StateMachines\ReservationTransitions;
use OneStopPay\OsppProtocol\StateMachines\SessionTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StateMachineEnumConsistencyTest extends TestCase
{
    #[Test]
    public function BayTransitions_keys_match_BayStatus_values(): void
    {
        $transitions = new BayTransitions();
        $table = $transitions->getTransitionTable();
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
        $table = $transitions->getTransitionTable();

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

    #[Test]
    public function DiagnosticsTransitions_keys_match_DiagnosticsStatus_values(): void
    {
        $transitions = new DiagnosticsTransitions();
        $table = $transitions->getTransitionTable();
        $tableKeys = array_keys($table);

        $enumValues = array_map(
            fn (DiagnosticsStatus $status) => $status->value,
            DiagnosticsStatus::cases(),
        );

        sort($tableKeys);
        sort($enumValues);

        self::assertSame($enumValues, $tableKeys);
    }

    #[Test]
    public function DiagnosticsTransitions_matches_DiagnosticsStatus_allowedTransitions(): void
    {
        $transitions = new DiagnosticsTransitions();

        foreach (DiagnosticsStatus::cases() as $status) {
            $transitionClassAllowed = $transitions->allowedTransitions($status);
            $enumMethodAllowed = $status->allowedTransitions();

            $transitionValues = array_map(
                fn (DiagnosticsStatus $s) => $s->value,
                $transitionClassAllowed,
            );

            $enumValues = array_map(
                fn (DiagnosticsStatus $s) => $s->value,
                $enumMethodAllowed,
            );

            sort($transitionValues);
            sort($enumValues);

            self::assertSame(
                $enumValues,
                $transitionValues,
                "DiagnosticsTransitions and DiagnosticsStatus::allowedTransitions() disagree for status '{$status->value}'",
            );
        }
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
