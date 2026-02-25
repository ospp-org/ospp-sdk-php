<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum ReservationStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::ACTIVE, self::EXPIRED, self::CANCELLED], true);
    }

    public function isCancellable(): bool
    {
        return $this === self::PENDING || $this === self::CONFIRMED;
    }

    public function isConvertible(): bool
    {
        return $this === self::CONFIRMED;
    }

    public function holdsBay(): bool
    {
        return $this === self::CONFIRMED;
    }

    public function triggersRefund(): bool
    {
        return $this === self::EXPIRED || $this === self::CANCELLED;
    }
}
