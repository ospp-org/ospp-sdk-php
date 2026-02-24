<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Contract\StateMachines;

use OneStopPay\OsppProtocol\Enums\FirmwareUpdateStatus;
use OneStopPay\OsppProtocol\StateMachines\FirmwareTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FirmwareTransitionsContractTest extends TestCase
{
    private FirmwareTransitions $transitions;

    protected function setUp(): void
    {
        $this->transitions = new FirmwareTransitions();
    }

    #[Test]
    public function transition_count_is_exactly_14(): void
    {
        self::assertSame(14, $this->transitions->transitionCount());
    }

    #[Test]
    public function self_transitions_are_never_allowed(): void
    {
        foreach (FirmwareUpdateStatus::cases() as $status) {
            self::assertFalse(
                $this->transitions->canTransition($status, $status),
                "Self-transition should not be allowed for {$status->value}",
            );
        }
    }

    #[Test]
    public function complete_10x10_transition_matrix(): void
    {
        $valid = 0;
        $invalid = 0;

        foreach (FirmwareUpdateStatus::cases() as $from) {
            foreach (FirmwareUpdateStatus::cases() as $to) {
                if ($this->transitions->canTransition($from, $to)) {
                    $valid++;
                } else {
                    $invalid++;
                }
            }
        }

        self::assertSame(14, $valid, 'Expected exactly 14 valid transitions');
        self::assertSame(86, $invalid, 'Expected exactly 86 invalid transitions');
        self::assertSame(100, $valid + $invalid, 'Total pairs should be 100 (10x10)');
    }

    #[Test]
    public function happy_path_8_consecutive_transitions(): void
    {
        $sequence = [
            [FirmwareUpdateStatus::IDLE, FirmwareUpdateStatus::DOWNLOADING],
            [FirmwareUpdateStatus::DOWNLOADING, FirmwareUpdateStatus::DOWNLOADED],
            [FirmwareUpdateStatus::DOWNLOADED, FirmwareUpdateStatus::VERIFYING],
            [FirmwareUpdateStatus::VERIFYING, FirmwareUpdateStatus::VERIFIED],
            [FirmwareUpdateStatus::VERIFIED, FirmwareUpdateStatus::INSTALLING],
            [FirmwareUpdateStatus::INSTALLING, FirmwareUpdateStatus::INSTALLED],
            [FirmwareUpdateStatus::INSTALLED, FirmwareUpdateStatus::REBOOTING],
            [FirmwareUpdateStatus::REBOOTING, FirmwareUpdateStatus::ACTIVATED],
        ];

        foreach ($sequence as $index => [$from, $to]) {
            self::assertTrue(
                $this->transitions->canTransition($from, $to),
                "Happy path step {$index}: {$from->value} -> {$to->value} should be valid",
            );
        }
    }

    #[Test]
    public function idle_cannot_fail_directly(): void
    {
        self::assertFalse(
            $this->transitions->canTransition(FirmwareUpdateStatus::IDLE, FirmwareUpdateStatus::FAILED),
        );
    }

    #[Test]
    public function verified_cannot_fail_directly(): void
    {
        self::assertFalse(
            $this->transitions->canTransition(FirmwareUpdateStatus::VERIFIED, FirmwareUpdateStatus::FAILED),
        );
    }
}
