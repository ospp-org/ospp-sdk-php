<?php

declare(strict_types=1);

namespace Ospp\Protocol\Actions;

/**
 * All 30 OSPP action constants (27 MQTT + 3 API-only).
 */
final class OsppAction
{
    // Core Profile — MQTT (6)
    public const BOOT_NOTIFICATION = 'BootNotification';
    public const HEARTBEAT = 'Heartbeat';
    public const STATUS_NOTIFICATION = 'StatusNotification';
    public const CONNECTION_LOST = 'ConnectionLost';
    public const DATA_TRANSFER = 'DataTransfer';
    public const TRIGGER_MESSAGE = 'TriggerMessage';

    // Transaction Profile — MQTT (7)
    public const START_SERVICE = 'StartService';
    public const STOP_SERVICE = 'StopService';
    public const RESERVE_BAY = 'ReserveBay';
    public const CANCEL_RESERVATION = 'CancelReservation';
    public const METER_VALUES = 'MeterValues';
    public const TRANSACTION_EVENT = 'TransactionEvent';
    public const SESSION_ENDED = 'SessionEnded';

    // Device Management Profile — Station Events — MQTT (2)
    public const FIRMWARE_STATUS_NOTIFICATION = 'FirmwareStatusNotification';
    public const DIAGNOSTICS_NOTIFICATION = 'DiagnosticsNotification';

    // Device Management Profile — Server Commands — MQTT (7)
    public const GET_CONFIGURATION = 'GetConfiguration';
    public const CHANGE_CONFIGURATION = 'ChangeConfiguration';
    public const UPDATE_FIRMWARE = 'UpdateFirmware';
    public const GET_DIAGNOSTICS = 'GetDiagnostics';
    public const RESET = 'Reset';
    public const SET_MAINTENANCE_MODE = 'SetMaintenanceMode';
    public const UPDATE_SERVICE_CATALOG = 'UpdateServiceCatalog';

    // Security Profile — MQTT (5)
    public const AUTHORIZE_OFFLINE_PASS = 'AuthorizeOfflinePass';
    public const SECURITY_EVENT = 'SecurityEvent';
    public const SIGN_CERTIFICATE = 'SignCertificate';
    public const CERTIFICATE_INSTALL = 'CertificateInstall';
    public const TRIGGER_CERTIFICATE_RENEWAL = 'TriggerCertificateRenewal';

    // API-Only Actions (3) — not transmitted via MQTT
    public const ISSUE_OFFLINE_PASS = 'IssueOfflinePass';
    public const REVOKE_OFFLINE_PASS = 'RevokeOfflinePass';
    public const WEB_PAYMENT_AUTHORIZATION = 'WebPaymentAuthorization';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [...self::mqttActions(), ...self::apiOnlyActions()];
    }

    /**
     * @return list<string>
     */
    public static function mqttActions(): array
    {
        return [
            self::BOOT_NOTIFICATION,
            self::HEARTBEAT,
            self::STATUS_NOTIFICATION,
            self::CONNECTION_LOST,
            self::DATA_TRANSFER,
            self::TRIGGER_MESSAGE,
            self::START_SERVICE,
            self::STOP_SERVICE,
            self::RESERVE_BAY,
            self::CANCEL_RESERVATION,
            self::METER_VALUES,
            self::TRANSACTION_EVENT,
            self::SESSION_ENDED,
            self::FIRMWARE_STATUS_NOTIFICATION,
            self::DIAGNOSTICS_NOTIFICATION,
            self::GET_CONFIGURATION,
            self::CHANGE_CONFIGURATION,
            self::UPDATE_FIRMWARE,
            self::GET_DIAGNOSTICS,
            self::RESET,
            self::SET_MAINTENANCE_MODE,
            self::UPDATE_SERVICE_CATALOG,
            self::AUTHORIZE_OFFLINE_PASS,
            self::SECURITY_EVENT,
            self::SIGN_CERTIFICATE,
            self::CERTIFICATE_INSTALL,
            self::TRIGGER_CERTIFICATE_RENEWAL,
        ];
    }

    /**
     * @return list<string>
     */
    public static function apiOnlyActions(): array
    {
        return [
            self::ISSUE_OFFLINE_PASS,
            self::REVOKE_OFFLINE_PASS,
            self::WEB_PAYMENT_AUTHORIZATION,
        ];
    }

    /**
     * Actions the catalogue marks `Station -> Server`, and only those.
     *
     * Upstream is the Direction column of the MQTT Quick Reference in
     * `spec/03-messages.md`, compared by `scripts/check-action-registry.php` on
     * every run, in both directions.
     *
     * **This list narrowed at 0.40.0, from 13 to 11.** It used to absorb the
     * two rows the catalogue does not mark `Station -> Server` because there was
     * nowhere else to put them: `ConnectionLost`, which is
     * `Broker -> Server, or Station -> Server`, and `DataTransfer`, which is
     * `Bidirectional` and was listed here AND in `serverToStation()`. Those two
     * rows now have `brokerToServer()` and `bidirectional()`. With four lists
     * the four Direction literals map one to one and the gate compares them at
     * full resolution; with two it had to project, and a projection cannot tell
     * a bidirectional row from a row written down twice.
     *
     * A caller that wants everything that can ARRIVE from a station wants this
     * list, `brokerToServer()` and `bidirectional()` together. The sibling
     * TypeScript SDK draws the same four lists the same way, so both answer the
     * Direction question identically.
     *
     * @return list<string>
     */
    public static function stationToServer(): array
    {
        return [
            self::BOOT_NOTIFICATION,
            self::HEARTBEAT,
            self::STATUS_NOTIFICATION,
            self::METER_VALUES,
            self::TRANSACTION_EVENT,
            self::SESSION_ENDED,
            self::FIRMWARE_STATUS_NOTIFICATION,
            self::DIAGNOSTICS_NOTIFICATION,
            self::AUTHORIZE_OFFLINE_PASS,
            self::SECURITY_EVENT,
            self::SIGN_CERTIFICATE,
        ];
    }

    /**
     * Actions the catalogue marks `Server -> Station`, and only those.
     *
     * Upstream is the same Direction column, same gate, both directions.
     *
     * **This list narrowed at 0.40.0, from 15 to 14**, for the reason
     * `stationToServer()` records: `DataTransfer` is `Bidirectional` and now
     * sits in `bidirectional()` rather than in both direction lists.
     *
     * @return list<string>
     */
    public static function serverToStation(): array
    {
        return [
            self::START_SERVICE,
            self::STOP_SERVICE,
            self::RESERVE_BAY,
            self::CANCEL_RESERVATION,
            self::GET_CONFIGURATION,
            self::CHANGE_CONFIGURATION,
            self::UPDATE_FIRMWARE,
            self::GET_DIAGNOSTICS,
            self::RESET,
            self::SET_MAINTENANCE_MODE,
            self::UPDATE_SERVICE_CATALOG,
            self::CERTIFICATE_INSTALL,
            self::TRIGGER_CERTIFICATE_RENEWAL,
            self::TRIGGER_MESSAGE,
        ];
    }

    /**
     * Actions the catalogue marks `Broker → Server, or Station → Server`.
     *
     * One row: `ConnectionLost`, published as the broker's Last Will and
     * Testament when a station's session drops without a DISCONNECT.
     *
     * @return list<string>
     */
    public static function brokerToServer(): array
    {
        return [
            self::CONNECTION_LOST,
        ];
    }

    /**
     * Actions the catalogue marks `Bidirectional`.
     *
     * One row: `DataTransfer`, the action either side may originate.
     *
     * @return list<string>
     */
    public static function bidirectional(): array
    {
        return [
            self::DATA_TRANSFER,
        ];
    }

    /**
     * Actions of type EVENT (fire-and-forget, no response expected).
     *
     * Upstream is the Type column of the MQTT Quick Reference, which maps onto
     * this accessor one to one — no projection, nothing left over on either
     * side. `scripts/check-action-registry.php` compares it on every run.
     *
     * @return list<string>
     */
    public static function events(): array
    {
        return [
            self::STATUS_NOTIFICATION,
            self::CONNECTION_LOST,
            self::METER_VALUES,
            self::SESSION_ENDED,
            self::FIRMWARE_STATUS_NOTIFICATION,
            self::DIAGNOSTICS_NOTIFICATION,
            self::SECURITY_EVENT,
        ];
    }

    /**
     * Actions of type REQUEST (expect a response).
     *
     * Upstream is the `REQ/RES` value of the Type column, one to one, compared
     * by `scripts/check-action-registry.php` in both directions. Together with
     * `events()` these two lists partition the MQTT set: the catalogue gives
     * every row exactly one Type.
     *
     * @return list<string>
     */
    public static function requests(): array
    {
        return [
            self::BOOT_NOTIFICATION,
            self::HEARTBEAT,
            self::START_SERVICE,
            self::STOP_SERVICE,
            self::RESERVE_BAY,
            self::CANCEL_RESERVATION,
            self::GET_CONFIGURATION,
            self::CHANGE_CONFIGURATION,
            self::UPDATE_FIRMWARE,
            self::GET_DIAGNOSTICS,
            self::RESET,
            self::SET_MAINTENANCE_MODE,
            self::UPDATE_SERVICE_CATALOG,
            self::AUTHORIZE_OFFLINE_PASS,
            self::TRANSACTION_EVENT,
            self::SIGN_CERTIFICATE,
            self::CERTIFICATE_INSTALL,
            self::TRIGGER_CERTIFICATE_RENEWAL,
            self::DATA_TRANSFER,
            self::TRIGGER_MESSAGE,
        ];
    }

    public static function isValid(string $action): bool
    {
        return in_array($action, self::all(), true);
    }

    public static function isMqtt(string $action): bool
    {
        return in_array($action, self::mqttActions(), true);
    }
}
