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

    // Offline / BLE Profile (4 keys) -- Profile ID `OfflineBLE`
    case OFFLINE_MODE_ENABLED = 'OfflineModeEnabled';
    case MAX_OFFLINE_TRANSACTIONS = 'MaxOfflineTransactions';
    case OFFLINE_PASS_MAX_AGE = 'OfflinePassMaxAge';
    case REVOCATION_EPOCH = 'RevocationEpoch';

    // Device Management Profile (3 keys)
    //
    // DIAGNOSTICS_UPLOAD_URL was withdrawn by spec 0.23.0 and its case removed here.
    // It had no reachable consumer: `uploadUrl` is REQUIRED on every GetDiagnostics
    // so nothing fell back to it, no processing rule read it, and no error code
    // described the disabled state its documented `''` default claimed. Removing the
    // case is BREAKING for any consumer naming it. See CHANGELOG for the cost, which
    // lands on servers: an unknown key is answered `NotSupported` and the
    // ChangeConfiguration batch is atomic, so a push set still carrying it loses the
    // whole batch against a 0.23.0 station.
    case FIRMWARE_UPDATE_ENABLED = 'FirmwareUpdateEnabled';
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
            self::PROTOCOL_VERSION => '0.3.0',
            self::BOOT_RETRY_INTERVAL => 30,
            self::CONNECTION_LOST_GRACE_PERIOD => 300,
            self::METER_VALUES_INTERVAL => 60,
            self::METER_VALUES_SAMPLE_INTERVAL => 10,
            self::MAX_SESSION_DURATION_SECONDS => 900,
            self::SESSION_TIMEOUT => 120,
            self::RESERVATION_DEFAULT_TTL => 300,
            self::DEFAULT_CREDITS_PER_SESSION => 100,
            self::AUTHORIZATION_CACHE_ENABLED => true,
            // spec/08-configuration.md §3: default `"All"`, and Static rather
            // than Dynamic -- the mode is bound to the session key, which is
            // issued at boot, so a mid-session change would leave one peer
            // signing and the other not.
            self::MESSAGE_SIGNING_MODE => 'All',
            self::CERTIFICATE_RENEWAL_THRESHOLD_DAYS => 30,
            self::CERTIFICATE_RENEWAL_ENABLED => true,
            self::OFFLINE_MODE_ENABLED => true,
            self::MAX_OFFLINE_TRANSACTIONS => 1000,
            self::OFFLINE_PASS_MAX_AGE => 86400,
            self::REVOCATION_EPOCH => 0,
            self::FIRMWARE_UPDATE_ENABLED => true,
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

    /**
     * Whether a ChangeConfiguration on this key takes effect NOW.
     *
     * `false` is 08-configuration.md's `Static`, which does not mean "unchangeable" —
     * it means the change lands at the station's next boot. Six keys are Static and
     * all six are listed; the `default` arm is Dynamic. It was seven until spec 0.23.0
     * withdrew `DiagnosticsUploadUrl`.
     */
    public function isMutable(): bool
    {
        return match ($this) {
            self::STATION_NAME,
            self::TIME_ZONE,
            self::PROTOCOL_VERSION,
            self::FIRMWARE_VERSION,
            self::CERTIFICATE_SERIAL_NUMBER,
            // The sixth, and the one this arm was missing. 08-configuration.md:114
            // sets it **Static** in bold and gives the reason: the mode is bound to the
            // session key, which is issued at boot, so a mid-session change leaves one
            // peer signing and the other not — and verification fails closed while
            // signing fails closed too, so the station goes silent in BOTH directions.
            // A server trusting the old `true` here would dispatch that change mid
            // session, and the outage would present as a station that had died.
            //
            // This enum was contradicting its own package: Enums\SigningMode's docblock
            // has always said "The mode is `Static`". sdk-ts had it right throughout.
            self::MESSAGE_SIGNING_MODE => false,

            default => true,
        };
    }

    /**
     * The key's profile, as the **Profile ID** of spec/08-configuration.md §1.5 --
     * the normative machine identifier, never the display label.
     *
     * §1.5 states the two columns separately because the labels do not survive
     * being made identifiers: `Offline / BLE` carries a space and a slash, and
     * every implementation that needed a program value invented its own spelling
     * of it. This method returned `Offline` until the column existed to compare
     * against. The display labels, for reference, are: Core, Transaction,
     * Security, `Offline / BLE`, `Device Management`.
     *
     * Checked against the spec by scripts/check-config-registry.php.
     */
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
            self::REVOCATION_EPOCH => 'OfflineBLE',

            self::FIRMWARE_UPDATE_ENABLED,
            self::LOG_LEVEL,
            self::AUTO_REBOOT_ENABLED => 'DeviceManagement',
        };
    }
}
