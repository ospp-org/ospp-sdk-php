<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Unit\StateMachines;

use OneStopPay\OsppProtocol\Enums\DiagnosticsStatus;
use OneStopPay\OsppProtocol\StateMachines\DiagnosticsTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DiagnosticsTransitionsTest extends TestCase
{
    private DiagnosticsTransitions $machine;

    protected function setUp(): void
    {
        $this->machine = new DiagnosticsTransitions();
    }

    #[Test]
    public function transitionCountReturnsSix(): void
    {
        self::assertSame(6, $this->machine->transitionCount());
    }

    #[Test]
    public function canTransitionForAllValidTransitions(): void
    {
        $validTransitions = [
            [DiagnosticsStatus::PENDING, DiagnosticsStatus::COLLECTING],
            [DiagnosticsStatus::PENDING, DiagnosticsStatus::FAILED],
            [DiagnosticsStatus::COLLECTING, DiagnosticsStatus::UPLOADING],
            [DiagnosticsStatus::COLLECTING, DiagnosticsStatus::FAILED],
            [DiagnosticsStatus::UPLOADING, DiagnosticsStatus::UPLOADED],
            [DiagnosticsStatus::UPLOADING, DiagnosticsStatus::FAILED],
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
        $terminalStates = [DiagnosticsStatus::UPLOADED, DiagnosticsStatus::FAILED];

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
            foreach (DiagnosticsStatus::cases() as $target) {
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
            [DiagnosticsStatus::PENDING, DiagnosticsStatus::UPLOADING],
            [DiagnosticsStatus::PENDING, DiagnosticsStatus::UPLOADED],
            // Can't go backwards
            [DiagnosticsStatus::COLLECTING, DiagnosticsStatus::PENDING],
            [DiagnosticsStatus::UPLOADING, DiagnosticsStatus::COLLECTING],
            [DiagnosticsStatus::UPLOADING, DiagnosticsStatus::PENDING],
        ];

        foreach ($invalidTransitions as [$from, $to]) {
            self::assertFalse(
                $this->machine->canTransition($from, $to),
                "Expected transition {$from->value} -> {$to->value} to be invalid",
            );
        }
    }

    #[Test]
    public function getTransitionTableCoversAllFiveStates(): void
    {
        $table = $this->machine->getTransitionTable();

        self::assertCount(5, $table);

        $expectedKeys = ['pending', 'collecting', 'uploading', 'uploaded', 'failed'];

        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $table, "Transition table missing key: {$key}");
        }

        self::assertSame(['collecting', 'failed'], $table['pending']);
        self::assertSame(['uploading', 'failed'], $table['collecting']);
        self::assertSame(['uploaded', 'failed'], $table['uploading']);
        self::assertSame([], $table['uploaded']);
        self::assertSame([], $table['failed']);
    }
}
