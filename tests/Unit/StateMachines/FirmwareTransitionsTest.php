<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\StateMachines;

use Ospp\Protocol\Enums\FirmwareUpdateStatus;
use Ospp\Protocol\StateMachines\FirmwareTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FirmwareTransitionsTest extends TestCase
{
    private FirmwareTransitions $machine;

    protected function setUp(): void
    {
        $this->machine = new FirmwareTransitions();
    }

    // The valid-edge list that used to live here was positive-only: it asserted
    // that fourteen named pairs were permitted and nothing about what was not.
    // A machine answering `true` to every pair would have passed it. It also
    // carried two pairs §6.3 does not list, and omitted `failed -> idle`.
    // FirmwareCanonicalTableContractTest now owns the edge set and sweeps the
    // full matrix, which fails in both directions.

    #[Test]
    public function activatedIsTheOnlyTerminalState(): void
    {
        self::assertSame(
            [],
            $this->machine->allowedTransitions(FirmwareUpdateStatus::ACTIVATED),
            'activated should have no allowed transitions',
        );

        foreach (FirmwareUpdateStatus::cases() as $target) {
            self::assertFalse(
                $this->machine->canTransition(FirmwareUpdateStatus::ACTIVATED, $target),
                "activated should not transition to {$target->value}",
            );
        }
    }

    /**
     * spec/05-state-machines.md §6.3: "`Failed` has exactly one outgoing edge,
     * `Failed -> Idle`; it is **not** terminal, and a machine that treats it as
     * terminal can run one firmware update and never a second."
     */
    #[Test]
    public function failedIsNotTerminalAndRollsBackToIdle(): void
    {
        self::assertSame(
            [FirmwareUpdateStatus::IDLE],
            $this->machine->allowedTransitions(FirmwareUpdateStatus::FAILED),
        );

        foreach (FirmwareUpdateStatus::cases() as $target) {
            self::assertSame(
                $target === FirmwareUpdateStatus::IDLE,
                $this->machine->canTransition(FirmwareUpdateStatus::FAILED, $target),
                "failed -> {$target->value}",
            );
        }
    }

    #[Test]
    public function canTransitionReturnsFalseForInvalidTransitions(): void
    {
        $invalidTransitions = [
            // Can't skip stages
            [FirmwareUpdateStatus::IDLE, FirmwareUpdateStatus::DOWNLOADED],
            [FirmwareUpdateStatus::IDLE, FirmwareUpdateStatus::VERIFIED],
            [FirmwareUpdateStatus::IDLE, FirmwareUpdateStatus::ACTIVATED],
            [FirmwareUpdateStatus::DOWNLOADING, FirmwareUpdateStatus::INSTALLING],
            // Can't go backwards
            [FirmwareUpdateStatus::DOWNLOADED, FirmwareUpdateStatus::DOWNLOADING],
            [FirmwareUpdateStatus::VERIFIED, FirmwareUpdateStatus::DOWNLOADED],
            [FirmwareUpdateStatus::INSTALLED, FirmwareUpdateStatus::INSTALLING],
            // Idle has only one forward transition (no fail)
            [FirmwareUpdateStatus::IDLE, FirmwareUpdateStatus::FAILED],
            // Verified has only one forward transition (no fail)
            [FirmwareUpdateStatus::VERIFIED, FirmwareUpdateStatus::FAILED],
        ];

        foreach ($invalidTransitions as [$from, $to]) {
            self::assertFalse(
                $this->machine->canTransition($from, $to),
                "Expected transition {$from->value} -> {$to->value} to be invalid",
            );
        }
    }

    #[Test]
    public function getTransitionTableCoversAllTenStates(): void
    {
        $table = $this->machine->getTransitionTable();

        self::assertCount(10, $table);

        $expectedKeys = [
            'idle', 'downloading', 'downloaded', 'verifying', 'verified',
            'installing', 'installed', 'rebooting', 'activated', 'failed',
        ];

        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $table, "Transition table missing key: {$key}");
        }

        // `activated` is the only terminal state; `failed` rolls back to `idle`.
        self::assertSame([], $table['activated']);
        self::assertSame(['idle'], $table['failed']);
    }
}
