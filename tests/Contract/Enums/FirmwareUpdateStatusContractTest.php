<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Enums;

use Ospp\Protocol\Enums\FirmwareUpdateStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for FirmwareUpdateStatus enum.
 *
 * Pins the fromNotificationStatus explicit PascalCase match,
 * rejection of internal states, and terminal/active counts.
 */
final class FirmwareUpdateStatusContractTest extends TestCase
{
    #[Test]
    public function fromNotificationStatus_accepts_exactly_5_PascalCase_values(): void
    {
        $expected = [
            'Downloading' => FirmwareUpdateStatus::DOWNLOADING,
            'Downloaded' => FirmwareUpdateStatus::DOWNLOADED,
            'Installing' => FirmwareUpdateStatus::INSTALLING,
            'Installed' => FirmwareUpdateStatus::INSTALLED,
            'Failed' => FirmwareUpdateStatus::FAILED,
        ];

        foreach ($expected as $input => $expectedStatus) {
            self::assertSame(
                $expectedStatus,
                FirmwareUpdateStatus::fromNotificationStatus($input),
                "fromNotificationStatus('{$input}') should return FirmwareUpdateStatus::{$expectedStatus->name}",
            );
        }
    }

    #[Test]
    public function fromNotificationStatus_rejects_5_lowercase_variants(): void
    {
        $lowercaseVariants = ['downloading', 'downloaded', 'installing', 'installed', 'failed'];
        $rejected = 0;

        foreach ($lowercaseVariants as $variant) {
            try {
                FirmwareUpdateStatus::fromNotificationStatus($variant);
            } catch (\InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(5, $rejected, 'All 5 lowercase variants must be rejected');
    }

    #[Test]
    public function fromNotificationStatus_rejects_5_internal_states(): void
    {
        $internalStates = ['Idle', 'Verifying', 'Verified', 'Rebooting', 'Activated'];
        $rejected = 0;

        foreach ($internalStates as $state) {
            try {
                FirmwareUpdateStatus::fromNotificationStatus($state);
            } catch (\InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(5, $rejected, 'All 5 internal states must be rejected');
    }

    #[Test]
    public function fromNotificationStatus_rejects_UPPERCASE_variants(): void
    {
        $uppercaseVariants = ['DOWNLOADING', 'DOWNLOADED', 'INSTALLING', 'INSTALLED', 'FAILED'];
        $rejected = 0;

        foreach ($uppercaseVariants as $variant) {
            try {
                FirmwareUpdateStatus::fromNotificationStatus($variant);
            } catch (\InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(5, $rejected, 'All 5 UPPERCASE variants must be rejected');
    }

    #[Test]
    public function fromNotificationStatus_rejects_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FirmwareUpdateStatus::fromNotificationStatus('');
    }

    #[Test]
    public function isTerminal_count_is_exactly_2(): void
    {
        $terminal = array_filter(
            FirmwareUpdateStatus::cases(),
            fn (FirmwareUpdateStatus $s) => $s->isTerminal(),
        );
        self::assertCount(2, $terminal);

        $terminalSet = array_values($terminal);
        self::assertContains(FirmwareUpdateStatus::ACTIVATED, $terminalSet);
        self::assertContains(FirmwareUpdateStatus::FAILED, $terminalSet);
    }

    #[Test]
    public function isActive_count_is_exactly_7(): void
    {
        $active = array_filter(
            FirmwareUpdateStatus::cases(),
            fn (FirmwareUpdateStatus $s) => $s->isActive(),
        );
        self::assertCount(7, $active);

        // All non-terminal except IDLE: DOWNLOADING, DOWNLOADED, VERIFYING, VERIFIED, INSTALLING, INSTALLED, REBOOTING
        $activeSet = array_values($active);
        self::assertContains(FirmwareUpdateStatus::DOWNLOADING, $activeSet);
        self::assertContains(FirmwareUpdateStatus::DOWNLOADED, $activeSet);
        self::assertContains(FirmwareUpdateStatus::VERIFYING, $activeSet);
        self::assertContains(FirmwareUpdateStatus::VERIFIED, $activeSet);
        self::assertContains(FirmwareUpdateStatus::INSTALLING, $activeSet);
        self::assertContains(FirmwareUpdateStatus::INSTALLED, $activeSet);
        self::assertContains(FirmwareUpdateStatus::REBOOTING, $activeSet);
        self::assertFalse(FirmwareUpdateStatus::IDLE->isActive(), 'IDLE must not be active');
    }

    #[Test]
    public function idle_is_neither_terminal_nor_active(): void
    {
        self::assertFalse(FirmwareUpdateStatus::IDLE->isTerminal());
        self::assertFalse(FirmwareUpdateStatus::IDLE->isActive());
    }
}
