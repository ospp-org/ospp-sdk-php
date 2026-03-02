<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\SecurityEventType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SecurityEventTypeTest extends TestCase
{
    #[Test]
    public function it_has_exactly_eleven_cases(): void
    {
        self::assertCount(11, SecurityEventType::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('MacVerificationFailure', SecurityEventType::MAC_VERIFICATION_FAILURE->value);
        self::assertSame('CertificateError', SecurityEventType::CERTIFICATE_ERROR->value);
        self::assertSame('UnauthorizedAccess', SecurityEventType::UNAUTHORIZED_ACCESS->value);
        self::assertSame('OfflinePassRejected', SecurityEventType::OFFLINE_PASS_REJECTED->value);
        self::assertSame('TamperDetected', SecurityEventType::TAMPER_DETECTED->value);
        self::assertSame('BruteForceAttempt', SecurityEventType::BRUTE_FORCE_ATTEMPT->value);
        self::assertSame('FirmwareIntegrityFailure', SecurityEventType::FIRMWARE_INTEGRITY_FAILURE->value);
        self::assertSame('FirmwareDowngradeAttempt', SecurityEventType::FIRMWARE_DOWNGRADE_ATTEMPT->value);
        self::assertSame('HardwareFault', SecurityEventType::HARDWARE_FAULT->value);
        self::assertSame('SoftwareFault', SecurityEventType::SOFTWARE_FAULT->value);
        self::assertSame('ClockSkew', SecurityEventType::CLOCK_SKEW->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(SecurityEventType::MAC_VERIFICATION_FAILURE, SecurityEventType::from('MacVerificationFailure'));
        self::assertSame(SecurityEventType::TAMPER_DETECTED, SecurityEventType::from('TamperDetected'));
        self::assertSame(SecurityEventType::CLOCK_SKEW, SecurityEventType::from('ClockSkew'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(SecurityEventType::tryFrom('MAC_VERIFICATION_FAILURE'));
        self::assertNull(SecurityEventType::tryFrom('invalid'));
        self::assertNull(SecurityEventType::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        SecurityEventType::from('invalid');
    }
}
