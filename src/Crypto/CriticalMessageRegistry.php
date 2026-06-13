<?php

declare(strict_types=1);

namespace Ospp\Protocol\Crypto;

/**
 * Registry of OSPP messages that MUST be signed in `critical` mode.
 *
 * These messages involve financial operations, service activation,
 * configuration changes, offline authorization, or security events.
 */
final class CriticalMessageRegistry
{
    /** @var list<string> */
    private const CRITICAL_ACTIONS = [
        // Transaction Profile
        'StartService',
        'StopService',
        'ReserveBay',
        'CancelReservation',
        'TransactionEvent',
        'SessionEnded',

        // Security Profile
        'AuthorizeOfflinePass',
        'SignCertificate',
        'CertificateInstall',
        'TriggerCertificateRenewal',

        // Device Management Profile
        'ChangeConfiguration',
        'Reset',
        'UpdateFirmware',
        'SetMaintenanceMode',
        'UpdateServiceCatalog',

        // Core Profile — critical response
        'BootNotification',

        // General Profile
        'TriggerMessage',

        // Offline Profile
        'IssueOfflinePass',
        'RevokeOfflinePass',

        // Payment Profile
        'WebPaymentAuthorization',
    ];

    /**
     * Actions exempt from HMAC in EVERY MessageSigningMode (including
     * `All`) — their MAC would be cryptographically void:
     *
     * - ConnectionLost: broker-generated Last Will; the station cannot
     *   pre-sign it.
     *
     * @var list<string>
     */
    private const ALWAYS_EXEMPT_ACTIONS = [
        'ConnectionLost',
    ];

    public static function isAlwaysExempt(string $action): bool
    {
        return in_array($action, self::ALWAYS_EXEMPT_ACTIONS, true);
    }

    /**
     * @return list<string>
     */
    public static function allAlwaysExemptActions(): array
    {
        return self::ALWAYS_EXEMPT_ACTIONS;
    }

    public static function isCritical(string $action): bool
    {
        return in_array($action, self::CRITICAL_ACTIONS, true);
    }

    /**
     * @return list<string>
     */
    public static function allCriticalActions(): array
    {
        return self::CRITICAL_ACTIONS;
    }

    public static function count(): int
    {
        return count(self::CRITICAL_ACTIONS);
    }
}
