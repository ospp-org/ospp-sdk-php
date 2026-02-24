<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Actions;

/**
 * All 24 OSPP action constants (21 MQTT + 3 API-only).
 */
final class OsppAction
{
    // Core Profile — MQTT (4)
    public const BOOT_NOTIFICATION = 'BootNotification';
    public const HEARTBEAT = 'Heartbeat';
    public const STATUS_NOTIFICATION = 'StatusNotification';
    public const CONNECTION_LOST = 'ConnectionLost';

    // Transaction Profile — MQTT (5)
    public const START_SERVICE = 'StartService';
    public const STOP_SERVICE = 'StopService';
    public const RESERVE_BAY = 'ReserveBay';
    public const CANCEL_RESERVATION = 'CancelReservation';
    public const METER_VALUES = 'MeterValues';

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

    // Offline Profile — MQTT (2)
    public const AUTHORIZE_OFFLINE_PASS = 'AuthorizeOfflinePass';
    public const TRANSACTION_EVENT = 'TransactionEvent';

    // Security Profile — MQTT (1)
    public const SECURITY_EVENT = 'SecurityEvent';

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
            self::START_SERVICE,
            self::STOP_SERVICE,
            self::RESERVE_BAY,
            self::CANCEL_RESERVATION,
            self::METER_VALUES,
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
            self::TRANSACTION_EVENT,
            self::SECURITY_EVENT,
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
     * Actions sent from station to server (MQTT inbound).
     *
     * @return list<string>
     */
    public static function stationToServer(): array
    {
        return [
            self::BOOT_NOTIFICATION,
            self::HEARTBEAT,
            self::STATUS_NOTIFICATION,
            self::CONNECTION_LOST,
            self::METER_VALUES,
            self::FIRMWARE_STATUS_NOTIFICATION,
            self::DIAGNOSTICS_NOTIFICATION,
            self::AUTHORIZE_OFFLINE_PASS,
            self::TRANSACTION_EVENT,
            self::SECURITY_EVENT,
        ];
    }

    /**
     * Actions sent from server to station (MQTT outbound).
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
        ];
    }

    /**
     * Actions of type EVENT (fire-and-forget, no response expected).
     *
     * @return list<string>
     */
    public static function events(): array
    {
        return [
            self::STATUS_NOTIFICATION,
            self::CONNECTION_LOST,
            self::METER_VALUES,
            self::FIRMWARE_STATUS_NOTIFICATION,
            self::DIAGNOSTICS_NOTIFICATION,
            self::SECURITY_EVENT,
        ];
    }

    /**
     * Actions of type REQUEST (expect a response).
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
