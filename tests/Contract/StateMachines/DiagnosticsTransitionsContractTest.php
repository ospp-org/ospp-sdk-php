<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\StateMachines;

use Ospp\Protocol\Enums\DiagnosticsStatus;
use Ospp\Protocol\StateMachines\DiagnosticsTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DiagnosticsTransitionsContractTest extends TestCase
{
    private DiagnosticsTransitions $transitions;

    protected function setUp(): void
    {
        $this->transitions = new DiagnosticsTransitions();
    }

    #[Test]
    public function transition_count_is_exactly_6(): void
    {
        self::assertSame(6, $this->transitions->transitionCount());
    }

    #[Test]
    public function self_transitions_are_never_allowed(): void
    {
        foreach (DiagnosticsStatus::cases() as $status) {
            self::assertFalse(
                $this->transitions->canTransition($status, $status),
                "Self-transition should not be allowed for {$status->value}",
            );
        }
    }

    #[Test]
    public function complete_5x5_transition_matrix(): void
    {
        $valid = 0;
        $invalid = 0;

        foreach (DiagnosticsStatus::cases() as $from) {
            foreach (DiagnosticsStatus::cases() as $to) {
                if ($this->transitions->canTransition($from, $to)) {
                    $valid++;
                } else {
                    $invalid++;
                }
            }
        }

        self::assertSame(6, $valid, 'Expected exactly 6 valid transitions');
        self::assertSame(19, $invalid, 'Expected exactly 19 invalid transitions');
        self::assertSame(25, $valid + $invalid, 'Total pairs should be 25 (5x5)');
    }

    #[Test]
    public function happy_path_pending_collecting_uploading_uploaded(): void
    {
        $sequence = [
            [DiagnosticsStatus::PENDING, DiagnosticsStatus::COLLECTING],
            [DiagnosticsStatus::COLLECTING, DiagnosticsStatus::UPLOADING],
            [DiagnosticsStatus::UPLOADING, DiagnosticsStatus::UPLOADED],
        ];

        foreach ($sequence as [$from, $to]) {
            self::assertTrue(
                $this->transitions->canTransition($from, $to),
                "{$from->value} -> {$to->value} should be valid in the happy path",
            );
        }
    }

    #[Test]
    public function every_non_terminal_can_fail(): void
    {
        // DiagnosticsStatus has 3 non-terminal states (out of 5 total)
        $nonTerminal = [
            DiagnosticsStatus::PENDING,
            DiagnosticsStatus::COLLECTING,
            DiagnosticsStatus::UPLOADING,
        ];

        self::assertCount(3, $nonTerminal, 'Diagnostics has exactly 3 non-terminal states');

        foreach ($nonTerminal as $status) {
            self::assertTrue(
                $this->transitions->canTransition($status, DiagnosticsStatus::FAILED),
                "{$status->value} should be able to transition to FAILED",
            );
        }
    }
}
