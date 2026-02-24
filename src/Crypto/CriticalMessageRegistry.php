<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Crypto;

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

        // Security Profile
        'SecurityEvent',
        'AuthorizeOfflinePass',

        // Device Management Profile
        'ChangeConfiguration',
        'Reset',
        'UpdateFirmware',

        // Core Profile — critical response
        'BootNotification',

        // Offline Profile
        'IssueOfflinePass',
        'RevokeOfflinePass',

        // Payment Profile
        'WebPaymentAuthorization',
    ];

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
