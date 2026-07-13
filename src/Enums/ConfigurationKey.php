<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

/**
 * Complete OSPP Configuration Key registry.
 * 29 keys across 5 profiles.
 */
enum ConfigurationKey: string
{
    // Core Profile (9 keys)
    case HEARTBEAT_INTERVAL_SECONDS = 'HeartbeatIntervalSeconds';
    case CONNECTION_TIMEOUT = 'ConnectionTimeout';
    case RECONNECT_BACKOFF_MAX = 'ReconnectBackoffMax';
    case STATION_NAME = 'StationName';
    case TIME_ZONE = 'TimeZone';
    case PROTOCOL_VERSION = 'ProtocolVersion';
    case FIRMWARE_VERSION = 'FirmwareVersion';
    case BOOT_RETRY_INTERVAL = 'BootRetryInterval';
    case CONNECTION_LOST_GRACE_PERIOD = 'ConnectionLostGracePeriod';

    // Transaction Profile (6 keys)
    case METER_VALUES_INTERVAL = 'MeterValuesInterval';
    case METER_VALUES_SAMPLE_INTERVAL = 'MeterValuesSampleInterval';
    case MAX_SESSION_DURATION_SECONDS = 'MaxSessionDurationSeconds';
    case SESSION_TIMEOUT = 'SessionTimeout';
    case RESERVATION_DEFAULT_TTL = 'ReservationDefaultTTL';
    case DEFAULT_CREDITS_PER_SESSION = 'DefaultCreditsPerSession';

    // Security Profile (6 keys)
    case CERTIFICATE_SERIAL_NUMBER = 'CertificateSerialNumber';
    case AUTHORIZATION_CACHE_ENABLED = 'AuthorizationCacheEnabled';
    case MESSAGE_SIGNING_MODE = 'MessageSigningMode';
    case OFFLINE_PASS_PUBLIC_KEY = 'OfflinePassPublicKey';
    case CERTIFICATE_RENEWAL_THRESHOLD_DAYS = 'CertificateRenewalThresholdDays';
    case CERTIFICATE_RENEWAL_ENABLED = 'CertificateRenewalEnabled';

    // Offline Profile (4 keys)
    case OFFLINE_MODE_ENABLED = 'OfflineModeEnabled';
    case MAX_OFFLINE_TRANSACTIONS = 'MaxOfflineTransactions';
    case OFFLINE_PASS_MAX_AGE = 'OfflinePassMaxAge';
    case REVOCATION_EPOCH = 'RevocationEpoch';

    // Device Management Profile (4 keys)
    case FIRMWARE_UPDATE_ENABLED = 'FirmwareUpdateEnabled';
    case DIAGNOSTICS_UPLOAD_URL = 'DiagnosticsUploadUrl';
    case LOG_LEVEL = 'LogLevel';
    case AUTO_REBOOT_ENABLED = 'AutoRebootEnabled';

    public function type(): string
    {
        return match ($this) {
            self::STATION_NAME,
            self::TIME_ZONE,
            self::PROTOCOL_VERSION,
            self::FIRMWARE_VERSION,
            self::CERTIFICATE_SERIAL_NUMBER,
            self::MESSAGE_SIGNING_MODE,
            self::OFFLINE_PASS_PUBLIC_KEY,
            self::DIAGNOSTICS_UPLOAD_URL,
            self::LOG_LEVEL => 'string',

            self::AUTHORIZATION_CACHE_ENABLED,
            self::CERTIFICATE_RENEWAL_ENABLED,
            self::OFFLINE_MODE_ENABLED,
            self::FIRMWARE_UPDATE_ENABLED,
            self::AUTO_REBOOT_ENABLED => 'boolean',

            default => 'integer',
        };
    }

    public function defaultValue(): string|int|bool|null
    {
        return match ($this) {
            self::HEARTBEAT_INTERVAL_SECONDS => 30,
            self::CONNECTION_TIMEOUT => 60,
            self::RECONNECT_BACKOFF_MAX => 30,
            self::STATION_NAME => '',
            self::TIME_ZONE => 'UTC',
            self::PROTOCOL_VERSION => '0.2.1',
            self::BOOT_RETRY_INTERVAL => 30,
            self::CONNECTION_LOST_GRACE_PERIOD => 300,
            self::METER_VALUES_INTERVAL => 60,
            self::METER_VALUES_SAMPLE_INTERVAL => 10,
            self::MAX_SESSION_DURATION_SECONDS => 900,
            self::SESSION_TIMEOUT => 120,
            self::RESERVATION_DEFAULT_TTL => 300,
            self::DEFAULT_CREDITS_PER_SESSION => 100,
            self::AUTHORIZATION_CACHE_ENABLED => true,
            self::MESSAGE_SIGNING_MODE => 'Critical',
            self::CERTIFICATE_RENEWAL_THRESHOLD_DAYS => 30,
            self::CERTIFICATE_RENEWAL_ENABLED => true,
            self::OFFLINE_MODE_ENABLED => true,
            self::MAX_OFFLINE_TRANSACTIONS => 50,
            self::OFFLINE_PASS_MAX_AGE => 3600,
            self::REVOCATION_EPOCH => 0,
            self::FIRMWARE_UPDATE_ENABLED => true,
            self::DIAGNOSTICS_UPLOAD_URL => '',
            self::LOG_LEVEL => 'Info',
            self::AUTO_REBOOT_ENABLED => false,
            self::FIRMWARE_VERSION,
            self::CERTIFICATE_SERIAL_NUMBER,
            self::OFFLINE_PASS_PUBLIC_KEY => null,
        };
    }

    public function access(): string
    {
        return match ($this) {
            self::PROTOCOL_VERSION,
            self::FIRMWARE_VERSION,
            self::CERTIFICATE_SERIAL_NUMBER => 'R',

            self::OFFLINE_PASS_PUBLIC_KEY => 'W',

            default => 'RW',
        };
    }

    public function isMutable(): bool
    {
        return match ($this) {
            self::STATION_NAME,
            self::TIME_ZONE,
            self::PROTOCOL_VERSION,
            self::FIRMWARE_VERSION,
            self::CERTIFICATE_SERIAL_NUMBER,
            self::DIAGNOSTICS_UPLOAD_URL => false,

            default => true,
        };
    }

    public function profile(): string
    {
        return match ($this) {
            self::HEARTBEAT_INTERVAL_SECONDS,
            self::CONNECTION_TIMEOUT,
            self::RECONNECT_BACKOFF_MAX,
            self::STATION_NAME,
            self::TIME_ZONE,
            self::PROTOCOL_VERSION,
            self::FIRMWARE_VERSION,
            self::BOOT_RETRY_INTERVAL,
            self::CONNECTION_LOST_GRACE_PERIOD => 'Core',

            self::METER_VALUES_INTERVAL,
            self::METER_VALUES_SAMPLE_INTERVAL,
            self::MAX_SESSION_DURATION_SECONDS,
            self::SESSION_TIMEOUT,
            self::RESERVATION_DEFAULT_TTL,
            self::DEFAULT_CREDITS_PER_SESSION => 'Transaction',

            self::CERTIFICATE_SERIAL_NUMBER,
            self::AUTHORIZATION_CACHE_ENABLED,
            self::MESSAGE_SIGNING_MODE,
            self::OFFLINE_PASS_PUBLIC_KEY,
            self::CERTIFICATE_RENEWAL_THRESHOLD_DAYS,
            self::CERTIFICATE_RENEWAL_ENABLED => 'Security',

            self::OFFLINE_MODE_ENABLED,
            self::MAX_OFFLINE_TRANSACTIONS,
            self::OFFLINE_PASS_MAX_AGE,
            self::REVOCATION_EPOCH => 'Offline',

            self::FIRMWARE_UPDATE_ENABLED,
            self::DIAGNOSTICS_UPLOAD_URL,
            self::LOG_LEVEL,
            self::AUTO_REBOOT_ENABLED => 'DeviceManagement',
        };
    }
}
