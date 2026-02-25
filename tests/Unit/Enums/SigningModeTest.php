<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\SigningMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SigningModeTest extends TestCase
{
    #[Test]
    public function it_has_exactly_three_cases(): void
    {
        self::assertCount(3, SigningMode::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('all', SigningMode::ALL->value);
        self::assertSame('critical', SigningMode::CRITICAL->value);
        self::assertSame('none', SigningMode::NONE->value);
    }

    // --- shouldSign in ALL mode ---

    #[Test]
    public function all_mode_signs_critical_actions(): void
    {
        self::assertTrue(SigningMode::ALL->shouldSign('StartService'));
        self::assertTrue(SigningMode::ALL->shouldSign('StopService'));
        self::assertTrue(SigningMode::ALL->shouldSign('BootNotification'));
    }

    #[Test]
    public function all_mode_signs_non_critical_actions(): void
    {
        self::assertTrue(SigningMode::ALL->shouldSign('Heartbeat'));
        self::assertTrue(SigningMode::ALL->shouldSign('StatusNotification'));
        self::assertTrue(SigningMode::ALL->shouldSign('MeterValues'));
        self::assertTrue(SigningMode::ALL->shouldSign('SomeArbitraryAction'));
    }

    // --- shouldSign in CRITICAL mode ---

    #[Test]
    public function critical_mode_signs_critical_actions(): void
    {
        $criticalActions = [
            'StartService',
            'StopService',
            'ReserveBay',
            'CancelReservation',
            'TransactionEvent',
            'SecurityEvent',
            'AuthorizeOfflinePass',
            'ChangeConfiguration',
            'Reset',
            'UpdateFirmware',
            'BootNotification',
            'IssueOfflinePass',
            'RevokeOfflinePass',
            'WebPaymentAuthorization',
        ];

        foreach ($criticalActions as $action) {
            self::assertTrue(
                SigningMode::CRITICAL->shouldSign($action),
                "CRITICAL mode should sign '{$action}'",
            );
        }
    }

    #[Test]
    public function critical_mode_does_not_sign_non_critical_actions(): void
    {
        $nonCritical = [
            'Heartbeat',
            'StatusNotification',
            'MeterValues',
            'GetConfiguration',
            'GetDiagnostics',
            'SomeArbitraryAction',
        ];

        foreach ($nonCritical as $action) {
            self::assertFalse(
                SigningMode::CRITICAL->shouldSign($action),
                "CRITICAL mode should NOT sign '{$action}'",
            );
        }
    }

    // --- shouldSign in NONE mode ---

    #[Test]
    public function none_mode_does_not_sign_critical_actions(): void
    {
        self::assertFalse(SigningMode::NONE->shouldSign('StartService'));
        self::assertFalse(SigningMode::NONE->shouldSign('BootNotification'));
        self::assertFalse(SigningMode::NONE->shouldSign('SecurityEvent'));
    }

    #[Test]
    public function none_mode_does_not_sign_non_critical_actions(): void
    {
        self::assertFalse(SigningMode::NONE->shouldSign('Heartbeat'));
        self::assertFalse(SigningMode::NONE->shouldSign('MeterValues'));
        self::assertFalse(SigningMode::NONE->shouldSign('SomeArbitraryAction'));
    }

    // --- shouldVerify delegates to shouldSign ---

    #[Test]
    public function should_verify_matches_should_sign_for_all_mode(): void
    {
        self::assertTrue(SigningMode::ALL->shouldVerify('StartService'));
        self::assertTrue(SigningMode::ALL->shouldVerify('Heartbeat'));
    }

    #[Test]
    public function should_verify_matches_should_sign_for_critical_mode(): void
    {
        self::assertTrue(SigningMode::CRITICAL->shouldVerify('StartService'));
        self::assertFalse(SigningMode::CRITICAL->shouldVerify('Heartbeat'));
    }

    #[Test]
    public function should_verify_matches_should_sign_for_none_mode(): void
    {
        self::assertFalse(SigningMode::NONE->shouldVerify('StartService'));
        self::assertFalse(SigningMode::NONE->shouldVerify('Heartbeat'));
    }

    // --- from / tryFrom ---

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(SigningMode::ALL, SigningMode::from('all'));
        self::assertSame(SigningMode::CRITICAL, SigningMode::from('critical'));
        self::assertSame(SigningMode::NONE, SigningMode::from('none'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(SigningMode::tryFrom('ALL'));
        self::assertNull(SigningMode::tryFrom(''));
        self::assertNull(SigningMode::tryFrom('CRITICAL'));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        SigningMode::from('invalid');
    }
}
