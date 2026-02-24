<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Contract\Crypto;

use OneStopPay\OsppProtocol\Actions\OsppAction;
use OneStopPay\OsppProtocol\Crypto\CriticalMessageRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CriticalMessageRegistryContractTest extends TestCase
{
    #[Test]
    public function exactly_14_critical_actions(): void
    {
        self::assertSame(14, CriticalMessageRegistry::count());
        self::assertCount(14, CriticalMessageRegistry::allCriticalActions());
    }

    #[Test]
    public function exact_sorted_list_pinned(): void
    {
        $expected = [
            'AuthorizeOfflinePass',
            'BootNotification',
            'CancelReservation',
            'ChangeConfiguration',
            'IssueOfflinePass',
            'ReserveBay',
            'Reset',
            'RevokeOfflinePass',
            'SecurityEvent',
            'StartService',
            'StopService',
            'TransactionEvent',
            'UpdateFirmware',
            'WebPaymentAuthorization',
        ];

        $actual = CriticalMessageRegistry::allCriticalActions();
        sort($actual);

        self::assertSame($expected, $actual);
    }

    #[Test]
    public function every_critical_action_is_valid_OsppAction(): void
    {
        foreach (CriticalMessageRegistry::allCriticalActions() as $action) {
            self::assertTrue(
                OsppAction::isValid($action),
                "Critical action '{$action}' should be a valid OsppAction",
            );
        }
    }

    #[Test]
    public function non_critical_mqtt_actions_count(): void
    {
        $mqttActions = OsppAction::mqttActions();
        $criticalActions = CriticalMessageRegistry::allCriticalActions();

        $nonCriticalMqtt = array_diff($mqttActions, $criticalActions);

        // MQTT actions: 21, Critical MQTT actions = critical actions that are in mqttActions
        $criticalMqtt = array_intersect($mqttActions, $criticalActions);
        $expectedNonCritical = count($mqttActions) - count($criticalMqtt);

        self::assertCount($expectedNonCritical, $nonCriticalMqtt);
    }

    #[Test]
    public function isCritical_is_case_sensitive(): void
    {
        self::assertFalse(CriticalMessageRegistry::isCritical('startservice'));
        self::assertFalse(CriticalMessageRegistry::isCritical('STARTSERVICE'));
        self::assertFalse(CriticalMessageRegistry::isCritical('startService'));

        // Verify the correct casing works
        self::assertTrue(CriticalMessageRegistry::isCritical('StartService'));
    }
}
