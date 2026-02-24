<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Regression;

use OneStopPay\OsppProtocol\Enums\BayStatus;
use OneStopPay\OsppProtocol\Enums\DiagnosticsStatus;
use OneStopPay\OsppProtocol\Enums\FirmwareUpdateStatus;
use OneStopPay\OsppProtocol\Enums\SessionStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests pinning the 5 bugs found in the CSMS audit.
 *
 * These tests ensure that once-broken behavior stays fixed:
 * - BayStatus::fromOspp uses strtolower (accepts PascalCase)
 * - BayStatus::toOspp uses ucfirst (returns PascalCase)
 * - SessionStatus::isBillable is ACTIVE, not COMPLETED
 * - FirmwareUpdateStatus::fromNotificationStatus uses explicit PascalCase match
 * - DiagnosticsStatus::fromNotificationStatus uses explicit PascalCase match
 */
final class AuditRegressionTest extends TestCase
{
    #[Test]
    public function REGRESSION_BayStatus_fromOspp_uses_strtolower(): void
    {
        self::assertSame(BayStatus::AVAILABLE, BayStatus::fromOspp('Available'));
    }

    #[Test]
    public function REGRESSION_BayStatus_toOspp_uses_ucfirst(): void
    {
        self::assertSame('Available', BayStatus::AVAILABLE->toOspp());
    }

    #[Test]
    public function REGRESSION_SessionStatus_isBillable_is_ACTIVE_not_COMPLETED(): void
    {
        self::assertTrue(SessionStatus::ACTIVE->isBillable());
        self::assertFalse(SessionStatus::COMPLETED->isBillable());
    }

    #[Test]
    public function REGRESSION_SessionStatus_fromOspp_PascalCase(): void
    {
        self::assertSame(SessionStatus::ACTIVE, SessionStatus::fromOspp('Active'));
    }

    #[Test]
    public function REGRESSION_SessionStatus_toOspp_PascalCase(): void
    {
        self::assertSame('Active', SessionStatus::ACTIVE->toOspp());
    }

    #[Test]
    public function REGRESSION_FirmwareUpdateStatus_fromNotificationStatus_explicit_match(): void
    {
        // PascalCase works
        self::assertSame(
            FirmwareUpdateStatus::DOWNLOADING,
            FirmwareUpdateStatus::fromNotificationStatus('Downloading'),
        );

        // lowercase does NOT work (explicit match, not strtolower)
        $this->expectException(\InvalidArgumentException::class);
        FirmwareUpdateStatus::fromNotificationStatus('downloading');
    }

    #[Test]
    public function REGRESSION_FirmwareUpdateStatus_rejects_internal_states(): void
    {
        $internalStates = ['Verifying', 'Verified', 'Rebooting', 'Activated', 'Idle'];
        $rejected = 0;

        foreach ($internalStates as $state) {
            try {
                FirmwareUpdateStatus::fromNotificationStatus($state);
            } catch (\InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(5, $rejected, 'All 5 internal states must be rejected by fromNotificationStatus');
    }

    #[Test]
    public function REGRESSION_DiagnosticsStatus_fromNotificationStatus_explicit_match(): void
    {
        // PascalCase works
        self::assertSame(
            DiagnosticsStatus::COLLECTING,
            DiagnosticsStatus::fromNotificationStatus('Collecting'),
        );

        // lowercase does NOT work
        $this->expectException(\InvalidArgumentException::class);
        DiagnosticsStatus::fromNotificationStatus('collecting');
    }

    #[Test]
    public function REGRESSION_DiagnosticsStatus_rejects_Pending(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DiagnosticsStatus::fromNotificationStatus('Pending');
    }

    #[Test]
    public function REGRESSION_all_fromOspp_roundtrip_with_toOspp(): void
    {
        // BayStatus roundtrip
        foreach (BayStatus::cases() as $case) {
            self::assertSame(
                $case,
                BayStatus::fromOspp($case->toOspp()),
                "BayStatus::{$case->name} failed fromOspp(toOspp()) roundtrip",
            );
        }

        // SessionStatus roundtrip
        foreach (SessionStatus::cases() as $case) {
            self::assertSame(
                $case,
                SessionStatus::fromOspp($case->toOspp()),
                "SessionStatus::{$case->name} failed fromOspp(toOspp()) roundtrip",
            );
        }
    }
}
