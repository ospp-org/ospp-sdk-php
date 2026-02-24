<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Enums;

use OneStopPay\OsppProtocol\Crypto\CriticalMessageRegistry;

enum SigningMode: string
{
    case ALL = 'all';
    case CRITICAL = 'critical';
    case NONE = 'none';

    public function shouldSign(string $action): bool
    {
        return match ($this) {
            self::ALL => true,
            self::CRITICAL => CriticalMessageRegistry::isCritical($action),
            self::NONE => false,
        };
    }

    public function shouldVerify(string $action): bool
    {
        return $this->shouldSign($action);
    }
}
