<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Crypto;

use Ospp\Protocol\Crypto\CriticalMessageRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CriticalMessageRegistryTest extends TestCase
{
    #[Test]
    public function countReturnsFourteen(): void
    {
        self::assertSame(14, CriticalMessageRegistry::count());
    }

    #[Test]
    public function allCriticalActionsReturnsArrayOfFourteenStrings(): void
    {
        $actions = CriticalMessageRegistry::allCriticalActions();

        self::assertCount(14, $actions);

        foreach ($actions as $action) {
            self::assertIsString($action);
            self::assertNotEmpty($action);
        }
    }

    #[Test]
    public function isCriticalReturnsTrueForEachCriticalAction(): void
    {
        $expected = [
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

        foreach ($expected as $action) {
            self::assertTrue(
                CriticalMessageRegistry::isCritical($action),
                "Expected '{$action}' to be critical",
            );
        }
    }

    #[Test]
    public function isCriticalReturnsFalseForNonCriticalActions(): void
    {
        self::assertFalse(CriticalMessageRegistry::isCritical('Heartbeat'));
        self::assertFalse(CriticalMessageRegistry::isCritical('MeterValues'));
        self::assertFalse(CriticalMessageRegistry::isCritical('StatusNotification'));
        self::assertFalse(CriticalMessageRegistry::isCritical(''));
        self::assertFalse(CriticalMessageRegistry::isCritical('NonExistentAction'));
    }
}
