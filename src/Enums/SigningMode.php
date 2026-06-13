<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

use Ospp\Protocol\Crypto\CriticalMessageRegistry;

enum SigningMode: string
{
    case ALL = 'All';
    case CRITICAL = 'Critical';
    case NONE = 'None';

    public function shouldSign(string $action): bool
    {
        // Always-exempt actions are never signed in any mode — their MAC
        // would be cryptographically void.
        if (CriticalMessageRegistry::isAlwaysExempt($action)) {
            return false;
        }

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
