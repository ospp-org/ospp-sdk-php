<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Unit\StateMachines;

use OneStopPay\OsppProtocol\Enums\FirmwareUpdateStatus;
use OneStopPay\OsppProtocol\StateMachines\FirmwareTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FirmwareTransitionsTest extends TestCase
{
    private FirmwareTransitions $machine;

    protected function setUp(): void
    {
        $this->machine = new FirmwareTransitions();
    }

    #[Test]
    public function transitionCountReturnsFourteen(): void
    {
        self::assertSame(14, $this->machine->transitionCount());
    }

    #[Test]
    public function canTransitionForAllValidTransitions(): void
    {
        $validTransitions = [
            [FirmwareUpdateStatus::IDLE, FirmwareUpdateStatus::DOWNLOADING],
            [FirmwareUpdateStatus::DOWNLOADING, FirmwareUpdateStatus::DOWNLOADED],
            [FirmwareUpdateStatus::DOWNLOADING, FirmwareUpdateStatus::FAILED],
            [FirmwareUpdateStatus::DOWNLOADED, FirmwareUpdateStatus::VERIFYING],
            [FirmwareUpdateStatus::DOWNLOADED, FirmwareUpdateStatus::FAILED],
            [FirmwareUpdateStatus::VERIFYING, FirmwareUpdateStatus::VERIFIED],
            [FirmwareUpdateStatus::VERIFYING, FirmwareUpdateStatus::FAILED],
            [FirmwareUpdateStatus::VERIFIED, FirmwareUpdateStatus::INSTALLING],
            [FirmwareUpdateStatus::INSTALLING, FirmwareUpdateStatus::INSTALLED],
            [FirmwareUpdateStatus::INSTALLING, FirmwareUpdateStatus::FAILED],
            [FirmwareUpdateStatus::INSTALLED, FirmwareUpdateStatus::REBOOTING],
            [FirmwareUpdateStatus::INSTALLED, FirmwareUpdateStatus::FAILED],
            [FirmwareUpdateStatus::REBOOTING, FirmwareUpdateStatus::ACTIVATED],
            [FirmwareUpdateStatus::REBOOTING, FirmwareUpdateStatus::FAILED],
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
        $terminalStates = [FirmwareUpdateStatus::ACTIVATED, FirmwareUpdateStatus::FAILED];

        foreach ($terminalStates as $terminal) {
            $allowed = $this->machine->allowedTransitions($terminal);
            self::assertSame(
                [],
                $allowed,
                "Terminal state {$terminal->value} should have no allowed transitions",
            );
        }

        // Verify canTransition returns false for all possible targets from terminal states
        foreach ($terminalStates as $terminal) {
            foreach (FirmwareUpdateStatus::cases() as $target) {
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

        // Terminal states have empty transition lists
        self::assertSame([], $table['activated']);
        self::assertSame([], $table['failed']);
    }
}
