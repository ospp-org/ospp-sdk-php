<?php

declare(strict_types=1);

namespace Ospp\Protocol\Crypto\Ble;

use Ospp\Protocol\Enums\OsppErrorCode;
use RuntimeException;

/**
 * A BLE StationIdentity certificate verification failure (§6.5.2). Carries the
 * normative error code 2013 (BLE_AUTH_FAILED) as the exception code; the caller
 * aborts the handshake. Mirrors the TS SDK's StationIdentityError.
 */
final class StationIdentityException extends RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            "StationIdentity verification failed: {$reason}",
            OsppErrorCode::BLE_AUTH_FAILED->value,
        );
    }
}
