<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Actions;

use Ospp\Protocol\Actions\OsppAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OsppActionTest extends TestCase
{
    // ---------------------------------------------------------------
    // all()
    // ---------------------------------------------------------------

    #[Test]
    public function allReturns30Actions(): void
    {
        $all = OsppAction::all();

        self::assertCount(30, $all);
    }

    #[Test]
    public function allContainsEveryConstant(): void
    {
        $all = OsppAction::all();

        // Spot-check key actions from each profile
        self::assertContains('BootNotification', $all);
        self::assertContains('Heartbeat', $all);
        self::assertContains('StartService', $all);
        self::assertContains('MeterValues', $all);
        self::assertContains('GetConfiguration', $all);
        self::assertContains('UpdateFirmware', $all);
        self::assertContains('AuthorizeOfflinePass', $all);
        self::assertContains('SecurityEvent', $all);
        self::assertContains('SignCertificate', $all);
        self::assertContains('CertificateInstall', $all);
        self::assertContains('TriggerCertificateRenewal', $all);
        self::assertContains('DataTransfer', $all);
        self::assertContains('TriggerMessage', $all);
        self::assertContains('IssueOfflinePass', $all);
        self::assertContains('WebPaymentAuthorization', $all);
    }

    #[Test]
    public function allHasNoDuplicates(): void
    {
        $all = OsppAction::all();

        self::assertSame($all, array_values(array_unique($all)));
    }

    // ---------------------------------------------------------------
    // mqttActions()
    // ---------------------------------------------------------------

    #[Test]
    public function mqttActionsReturns27(): void
    {
        $mqtt = OsppAction::mqttActions();

        self::assertCount(27, $mqtt);
    }

    #[Test]
    public function mqttActionsDoNotContainApiOnly(): void
    {
        $mqtt = OsppAction::mqttActions();

        self::assertNotContains('IssueOfflinePass', $mqtt);
        self::assertNotContains('RevokeOfflinePass', $mqtt);
        self::assertNotContains('WebPaymentAuthorization', $mqtt);
    }

    // ---------------------------------------------------------------
    // apiOnlyActions()
    // ---------------------------------------------------------------

    #[Test]
    public function apiOnlyActionsReturns3(): void
    {
        $apiOnly = OsppAction::apiOnlyActions();

        self::assertCount(3, $apiOnly);
    }

    #[Test]
    public function apiOnlyActionsContainsExactActions(): void
    {
        $apiOnly = OsppAction::apiOnlyActions();

        self::assertSame([
            'IssueOfflinePass',
            'RevokeOfflinePass',
            'WebPaymentAuthorization',
        ], $apiOnly);
    }

    // ---------------------------------------------------------------
    // mqttActions() + apiOnlyActions() = all()
    // ---------------------------------------------------------------

    #[Test]
    public function mqttPlusApiOnlyEqualsAll(): void
    {
        $all = OsppAction::all();
        $combined = [...OsppAction::mqttActions(), ...OsppAction::apiOnlyActions()];

        self::assertSame($all, $combined);
    }

    // ---------------------------------------------------------------
    // stationToServer() and serverToStation()
    // ---------------------------------------------------------------

    #[Test]
    public function stationToServerReturns11Actions(): void
    {
        $s2s = OsppAction::stationToServer();

        self::assertCount(11, $s2s);
    }

    #[Test]
    public function serverToStationReturns14Actions(): void
    {
        $s2st = OsppAction::serverToStation();

        self::assertCount(14, $s2st);
    }

    #[Test]
    public function brokerToServerHoldsConnectionLostAlone(): void
    {
        self::assertSame([OsppAction::CONNECTION_LOST], OsppAction::brokerToServer());
    }

    #[Test]
    public function bidirectionalHoldsDataTransferAlone(): void
    {
        self::assertSame([OsppAction::DATA_TRANSFER], OsppAction::bidirectional());
    }

    /**
     * The four direction lists PARTITION the MQTT set: every action placed
     * exactly once, nothing left over on either side.
     *
     * Until 0.40.0 there were two lists, they overlapped on `DataTransfer`, and
     * the pair was a cover rather than a partition. The assertion that stood
     * here named that overlap as the expected answer, so it would have stayed
     * green through a second one.
     *
     * Both sides are derived. `assertSame(11 + 14 + 1 + 1, 27)` would fold to
     * `27 === 27` before it reached the runner and would pass over an empty
     * class — the shape `scripts/check-inert-assertions.php` exists to refuse.
     */
    #[Test]
    public function theFourDirectionListsPartitionTheMqttSet(): void
    {
        $buckets = [
            'stationToServer' => OsppAction::stationToServer(),
            'serverToStation' => OsppAction::serverToStation(),
            'brokerToServer' => OsppAction::brokerToServer(),
            'bidirectional' => OsppAction::bidirectional(),
        ];

        $placed = array_merge(...array_values($buckets));
        $twice = array_values(array_unique(array_diff_assoc($placed, array_unique($placed))));

        self::assertSame([], $twice, 'an action placed in more than one direction list');

        sort($placed);
        $mqtt = OsppAction::mqttActions();
        sort($mqtt);

        self::assertSame($mqtt, $placed, 'the four direction lists must place every MQTT action exactly once');
    }

    #[Test]
    public function stationToServerContainsExpectedActions(): void
    {
        $s2s = OsppAction::stationToServer();

        self::assertContains('BootNotification', $s2s);
        self::assertContains('Heartbeat', $s2s);
        self::assertContains('StatusNotification', $s2s);
        self::assertContains('MeterValues', $s2s);
        self::assertContains('TransactionEvent', $s2s);
        self::assertContains('SessionEnded', $s2s);
        self::assertContains('FirmwareStatusNotification', $s2s);
        self::assertContains('DiagnosticsNotification', $s2s);
        self::assertContains('AuthorizeOfflinePass', $s2s);
        self::assertContains('SecurityEvent', $s2s);
        self::assertContains('SignCertificate', $s2s);
        self::assertNotContains('ConnectionLost', $s2s, 'the broker row belongs to brokerToServer()');
        self::assertNotContains('DataTransfer', $s2s, 'the bidirectional row belongs to bidirectional()');
    }

    #[Test]
    public function serverToStationContainsExpectedActions(): void
    {
        $s2st = OsppAction::serverToStation();

        self::assertContains('StartService', $s2st);
        self::assertContains('StopService', $s2st);
        self::assertContains('ReserveBay', $s2st);
        self::assertContains('CancelReservation', $s2st);
        self::assertContains('GetConfiguration', $s2st);
        self::assertContains('ChangeConfiguration', $s2st);
        self::assertContains('UpdateFirmware', $s2st);
        self::assertContains('GetDiagnostics', $s2st);
        self::assertContains('Reset', $s2st);
        self::assertContains('SetMaintenanceMode', $s2st);
        self::assertContains('UpdateServiceCatalog', $s2st);
        self::assertContains('CertificateInstall', $s2st);
        self::assertContains('TriggerCertificateRenewal', $s2st);
        self::assertContains('TriggerMessage', $s2st);
        self::assertNotContains('DataTransfer', $s2st, 'the bidirectional row belongs to bidirectional()');
    }

    // ---------------------------------------------------------------
    // events() and requests()
    // ---------------------------------------------------------------

    #[Test]
    public function eventsReturns7Actions(): void
    {
        $events = OsppAction::events();

        self::assertCount(7, $events);
    }

    #[Test]
    public function eventsContainsExpectedActions(): void
    {
        $events = OsppAction::events();

        self::assertContains('StatusNotification', $events);
        self::assertContains('ConnectionLost', $events);
        self::assertContains('MeterValues', $events);
        self::assertContains('SessionEnded', $events);
        self::assertContains('FirmwareStatusNotification', $events);
        self::assertContains('DiagnosticsNotification', $events);
        self::assertContains('SecurityEvent', $events);
    }

    #[Test]
    public function requestsReturns20Actions(): void
    {
        $requests = OsppAction::requests();

        self::assertCount(20, $requests);
    }

    #[Test]
    public function requestsContainsExpectedActions(): void
    {
        $requests = OsppAction::requests();

        self::assertContains('BootNotification', $requests);
        self::assertContains('Heartbeat', $requests);
        self::assertContains('StartService', $requests);
        self::assertContains('StopService', $requests);
        self::assertContains('ReserveBay', $requests);
        self::assertContains('CancelReservation', $requests);
        self::assertContains('GetConfiguration', $requests);
        self::assertContains('ChangeConfiguration', $requests);
        self::assertContains('UpdateFirmware', $requests);
        self::assertContains('GetDiagnostics', $requests);
        self::assertContains('Reset', $requests);
        self::assertContains('SetMaintenanceMode', $requests);
        self::assertContains('UpdateServiceCatalog', $requests);
        self::assertContains('AuthorizeOfflinePass', $requests);
        self::assertContains('TransactionEvent', $requests);
        self::assertContains('SignCertificate', $requests);
        self::assertContains('CertificateInstall', $requests);
        self::assertContains('TriggerCertificateRenewal', $requests);
        self::assertContains('DataTransfer', $requests);
        self::assertContains('TriggerMessage', $requests);
    }

    #[Test]
    public function eventsAndRequestsAreDisjoint(): void
    {
        $events = OsppAction::events();
        $requests = OsppAction::requests();

        $intersection = array_intersect($events, $requests);

        self::assertEmpty($intersection, 'events and requests must not overlap');
    }

    #[Test]
    public function eventsAndRequestsCoverAllMqtt(): void
    {
        $events = OsppAction::events();
        $requests = OsppAction::requests();
        $combined = array_values(array_unique([...$events, ...$requests]));

        sort($combined);
        $mqtt = OsppAction::mqttActions();
        sort($mqtt);

        self::assertSame($mqtt, $combined);
    }

    // ---------------------------------------------------------------
    // isValid()
    // ---------------------------------------------------------------

    #[Test]
    public function isValidReturnsTrueForMqttAction(): void
    {
        self::assertTrue(OsppAction::isValid('BootNotification'));
    }

    #[Test]
    public function isValidReturnsTrueForApiOnlyAction(): void
    {
        self::assertTrue(OsppAction::isValid('IssueOfflinePass'));
    }

    #[Test]
    public function isValidReturnsTrueForAllKnownActions(): void
    {
        foreach (OsppAction::all() as $action) {
            self::assertTrue(OsppAction::isValid($action), "Expected '{$action}' to be valid");
        }
    }

    #[Test]
    public function isValidReturnsFalseForUnknownAction(): void
    {
        self::assertFalse(OsppAction::isValid('InvalidAction'));
    }

    #[Test]
    public function isValidReturnsFalseForEmptyString(): void
    {
        self::assertFalse(OsppAction::isValid(''));
    }

    #[Test]
    public function isValidIsCaseSensitive(): void
    {
        self::assertFalse(OsppAction::isValid('bootnotification'));
        self::assertFalse(OsppAction::isValid('BOOTNOTIFICATION'));
    }

    // ---------------------------------------------------------------
    // isMqtt()
    // ---------------------------------------------------------------

    #[Test]
    public function isMqttReturnsTrueForMqttAction(): void
    {
        self::assertTrue(OsppAction::isMqtt('BootNotification'));
        self::assertTrue(OsppAction::isMqtt('StartService'));
        self::assertTrue(OsppAction::isMqtt('SecurityEvent'));
    }

    #[Test]
    public function isMqttReturnsFalseForApiOnlyAction(): void
    {
        self::assertFalse(OsppAction::isMqtt('IssueOfflinePass'));
        self::assertFalse(OsppAction::isMqtt('RevokeOfflinePass'));
        self::assertFalse(OsppAction::isMqtt('WebPaymentAuthorization'));
    }

    #[Test]
    public function isMqttReturnsFalseForUnknownAction(): void
    {
        self::assertFalse(OsppAction::isMqtt('InvalidAction'));
    }

    #[Test]
    public function isMqttReturnsTrueForAllMqttActions(): void
    {
        foreach (OsppAction::mqttActions() as $action) {
            self::assertTrue(OsppAction::isMqtt($action), "Expected '{$action}' to be MQTT");
        }
    }

    #[Test]
    public function isMqttReturnsFalseForAllApiOnlyActions(): void
    {
        foreach (OsppAction::apiOnlyActions() as $action) {
            self::assertFalse(OsppAction::isMqtt($action), "Expected '{$action}' to NOT be MQTT");
        }
    }

    // ---------------------------------------------------------------
    // Constants match expected values
    // ---------------------------------------------------------------

    #[Test]
    public function constantsHaveExpectedValues(): void
    {
        // Core Profile
        self::assertSame('BootNotification', OsppAction::BOOT_NOTIFICATION);
        self::assertSame('Heartbeat', OsppAction::HEARTBEAT);
        self::assertSame('StatusNotification', OsppAction::STATUS_NOTIFICATION);
        self::assertSame('ConnectionLost', OsppAction::CONNECTION_LOST);
        self::assertSame('DataTransfer', OsppAction::DATA_TRANSFER);
        self::assertSame('TriggerMessage', OsppAction::TRIGGER_MESSAGE);

        // Transaction Profile
        self::assertSame('StartService', OsppAction::START_SERVICE);
        self::assertSame('StopService', OsppAction::STOP_SERVICE);
        self::assertSame('ReserveBay', OsppAction::RESERVE_BAY);
        self::assertSame('CancelReservation', OsppAction::CANCEL_RESERVATION);
        self::assertSame('MeterValues', OsppAction::METER_VALUES);
        self::assertSame('TransactionEvent', OsppAction::TRANSACTION_EVENT);
        self::assertSame('SessionEnded', OsppAction::SESSION_ENDED);

        // Device Management Profile — Station Events
        self::assertSame('FirmwareStatusNotification', OsppAction::FIRMWARE_STATUS_NOTIFICATION);
        self::assertSame('DiagnosticsNotification', OsppAction::DIAGNOSTICS_NOTIFICATION);

        // Device Management Profile — Server Commands
        self::assertSame('GetConfiguration', OsppAction::GET_CONFIGURATION);
        self::assertSame('ChangeConfiguration', OsppAction::CHANGE_CONFIGURATION);
        self::assertSame('UpdateFirmware', OsppAction::UPDATE_FIRMWARE);
        self::assertSame('GetDiagnostics', OsppAction::GET_DIAGNOSTICS);
        self::assertSame('Reset', OsppAction::RESET);
        self::assertSame('SetMaintenanceMode', OsppAction::SET_MAINTENANCE_MODE);
        self::assertSame('UpdateServiceCatalog', OsppAction::UPDATE_SERVICE_CATALOG);

        // Security Profile
        self::assertSame('AuthorizeOfflinePass', OsppAction::AUTHORIZE_OFFLINE_PASS);
        self::assertSame('SecurityEvent', OsppAction::SECURITY_EVENT);
        self::assertSame('SignCertificate', OsppAction::SIGN_CERTIFICATE);
        self::assertSame('CertificateInstall', OsppAction::CERTIFICATE_INSTALL);
        self::assertSame('TriggerCertificateRenewal', OsppAction::TRIGGER_CERTIFICATE_RENEWAL);

        // API-Only
        self::assertSame('IssueOfflinePass', OsppAction::ISSUE_OFFLINE_PASS);
        self::assertSame('RevokeOfflinePass', OsppAction::REVOKE_OFFLINE_PASS);
        self::assertSame('WebPaymentAuthorization', OsppAction::WEB_PAYMENT_AUTHORIZATION);
    }
}
