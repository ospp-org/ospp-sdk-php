<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\FirmwareUpdateStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FirmwareUpdateStatusTest extends TestCase
{
    #[Test]
    public function it_has_exactly_ten_cases(): void
    {
        self::assertCount(10, FirmwareUpdateStatus::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('idle', FirmwareUpdateStatus::IDLE->value);
        self::assertSame('downloading', FirmwareUpdateStatus::DOWNLOADING->value);
        self::assertSame('downloaded', FirmwareUpdateStatus::DOWNLOADED->value);
        self::assertSame('verifying', FirmwareUpdateStatus::VERIFYING->value);
        self::assertSame('verified', FirmwareUpdateStatus::VERIFIED->value);
        self::assertSame('installing', FirmwareUpdateStatus::INSTALLING->value);
        self::assertSame('installed', FirmwareUpdateStatus::INSTALLED->value);
        self::assertSame('rebooting', FirmwareUpdateStatus::REBOOTING->value);
        self::assertSame('activated', FirmwareUpdateStatus::ACTIVATED->value);
        self::assertSame('failed', FirmwareUpdateStatus::FAILED->value);
    }

    // --- isTerminal ---

    #[Test]
    public function is_terminal_returns_true_for_activated_and_failed(): void
    {
        self::assertTrue(FirmwareUpdateStatus::ACTIVATED->isTerminal());
        self::assertTrue(FirmwareUpdateStatus::FAILED->isTerminal());
    }

    #[Test]
    public function is_terminal_returns_false_for_non_terminal_states(): void
    {
        $nonTerminal = [
            FirmwareUpdateStatus::IDLE,
            FirmwareUpdateStatus::DOWNLOADING,
            FirmwareUpdateStatus::DOWNLOADED,
            FirmwareUpdateStatus::VERIFYING,
            FirmwareUpdateStatus::VERIFIED,
            FirmwareUpdateStatus::INSTALLING,
            FirmwareUpdateStatus::INSTALLED,
            FirmwareUpdateStatus::REBOOTING,
        ];

        foreach ($nonTerminal as $status) {
            self::assertFalse($status->isTerminal(), "{$status->value} should not be terminal");
        }
    }

    // --- isActive ---

    #[Test]
    public function is_active_returns_true_for_in_progress_states(): void
    {
        $activeStates = [
            FirmwareUpdateStatus::DOWNLOADING,
            FirmwareUpdateStatus::DOWNLOADED,
            FirmwareUpdateStatus::VERIFYING,
            FirmwareUpdateStatus::VERIFIED,
            FirmwareUpdateStatus::INSTALLING,
            FirmwareUpdateStatus::INSTALLED,
            FirmwareUpdateStatus::REBOOTING,
        ];

        foreach ($activeStates as $status) {
            self::assertTrue($status->isActive(), "{$status->value} should be active");
        }
    }

    #[Test]
    public function is_active_returns_false_for_idle_and_terminal(): void
    {
        self::assertFalse(FirmwareUpdateStatus::IDLE->isActive());
        self::assertFalse(FirmwareUpdateStatus::ACTIVATED->isActive());
        self::assertFalse(FirmwareUpdateStatus::FAILED->isActive());
    }

    // --- fromNotificationStatus (explicit match — only accepts PascalCase notification values) ---

    #[Test]
    public function from_notification_status_maps_valid_notification_values(): void
    {
        self::assertSame(FirmwareUpdateStatus::DOWNLOADING, FirmwareUpdateStatus::fromNotificationStatus('Downloading'));
        self::assertSame(FirmwareUpdateStatus::DOWNLOADED, FirmwareUpdateStatus::fromNotificationStatus('Downloaded'));
        self::assertSame(FirmwareUpdateStatus::INSTALLING, FirmwareUpdateStatus::fromNotificationStatus('Installing'));
        self::assertSame(FirmwareUpdateStatus::INSTALLED, FirmwareUpdateStatus::fromNotificationStatus('Installed'));
        self::assertSame(FirmwareUpdateStatus::FAILED, FirmwareUpdateStatus::fromNotificationStatus('Failed'));
    }

    #[Test]
    public function from_notification_status_throws_for_unknown_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown firmware notification status: NONEXISTENT');
        FirmwareUpdateStatus::fromNotificationStatus('NONEXISTENT');
    }

    #[Test]
    public function from_notification_status_throws_for_lowercase_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FirmwareUpdateStatus::fromNotificationStatus('downloading');
    }

    #[Test]
    public function from_notification_status_throws_for_statuses_not_sent_by_station(): void
    {
        // Stations only send 5 notification statuses. Others (idle, verifying, etc.) are internal.
        $this->expectException(\InvalidArgumentException::class);
        FirmwareUpdateStatus::fromNotificationStatus('Idle');
    }
}
