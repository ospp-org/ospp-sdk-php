<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\BootReason;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BootReasonTest extends TestCase
{
    #[Test]
    public function it_has_exactly_eight_cases(): void
    {
        // v0.11.0: +RemoteReset (a commanded return, reset.md §5 rule 6) and
        // +Reconnect (no boot occurred, boot-notification.md §5.2).
        self::assertCount(8, BootReason::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('PowerOn', BootReason::POWER_ON->value);
        self::assertSame('Watchdog', BootReason::WATCHDOG->value);
        self::assertSame('FirmwareUpdate', BootReason::FIRMWARE_UPDATE->value);
        self::assertSame('RemoteReset', BootReason::REMOTE_RESET->value);
        self::assertSame('ManualReset', BootReason::MANUAL_RESET->value);
        self::assertSame('ScheduledReset', BootReason::SCHEDULED_RESET->value);
        self::assertSame('ErrorRecovery', BootReason::ERROR_RECOVERY->value);
        self::assertSame('Reconnect', BootReason::RECONNECT->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(BootReason::POWER_ON, BootReason::from('PowerOn'));
        self::assertSame(BootReason::WATCHDOG, BootReason::from('Watchdog'));
        self::assertSame(BootReason::ERROR_RECOVERY, BootReason::from('ErrorRecovery'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(BootReason::tryFrom('POWER_ON'));
        self::assertNull(BootReason::tryFrom('invalid'));
        self::assertNull(BootReason::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        BootReason::from('invalid');
    }
}
