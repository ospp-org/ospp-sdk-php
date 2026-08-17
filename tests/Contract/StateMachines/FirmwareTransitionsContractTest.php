<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\StateMachines;

use Ospp\Protocol\Enums\FirmwareUpdateStatus;
use Ospp\Protocol\StateMachines\FirmwareTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FirmwareTransitionsContractTest extends TestCase
{
    private FirmwareTransitions $transitions;

    protected function setUp(): void
    {
        $this->transitions = new FirmwareTransitions();
    }

    // The edge set — and the size of it — is pinned by
    // FirmwareCanonicalTableContractTest against spec §6.3. It is deliberately
    // not restated as a cardinal here: this file asserted `14`, which is the
    // ROW count of §6.3's table rather than its edge count, and the two phantom
    // edges that reached that total were what the assertion protected.

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

    // The 10x10 sweep also lived here, as a 14-valid / 86-invalid tally. A tally
    // over the matrix cannot say WHICH pair moved — swapping a real edge for a
    // phantom leaves both numbers unchanged. FirmwareCanonicalTableContractTest
    // sweeps the same matrix and compares each cell against the named edge set.

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
