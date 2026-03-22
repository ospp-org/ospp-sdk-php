<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Crypto;

use Ospp\Protocol\Crypto\CriticalMessageRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CriticalMessageRegistryTest extends TestCase
{
    #[Test]
    public function countReturnsNineteen(): void
    {
        self::assertSame(20, CriticalMessageRegistry::count());
    }

    #[Test]
    public function allCriticalActionsReturnsArrayOfNineteenStrings(): void
    {
        $actions = CriticalMessageRegistry::allCriticalActions();

        self::assertCount(20, $actions);

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
            'SessionEnded',
            'AuthorizeOfflinePass',
            'SignCertificate',
            'CertificateInstall',
            'TriggerCertificateRenewal',
            'ChangeConfiguration',
            'Reset',
            'UpdateFirmware',
            'SetMaintenanceMode',
            'UpdateServiceCatalog',
            'BootNotification',
            'TriggerMessage',
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
        self::assertFalse(CriticalMessageRegistry::isCritical('SecurityEvent'));
        self::assertFalse(CriticalMessageRegistry::isCritical('DataTransfer'));
        self::assertFalse(CriticalMessageRegistry::isCritical(''));
        self::assertFalse(CriticalMessageRegistry::isCritical('NonExistentAction'));
    }
}
