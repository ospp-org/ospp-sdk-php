<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum BayStatus: string
{
    case UNKNOWN = 'unknown';
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case OCCUPIED = 'occupied';
    case FINISHING = 'finishing';
    case FAULTED = 'faulted';
    case UNAVAILABLE = 'unavailable';

    public function isInitial(): bool
    {
        return $this === self::UNKNOWN;
    }

    public function canStartSession(): bool
    {
        return $this === self::AVAILABLE || $this === self::RESERVED;
    }

    public function canReserve(): bool
    {
        return $this === self::AVAILABLE;
    }

    public function isFaulted(): bool
    {
        return $this === self::FAULTED;
    }

    public function acceptsSessions(): bool
    {
        return $this === self::AVAILABLE || $this === self::RESERVED;
    }

    public function acceptsReservations(): bool
    {
        return $this === self::AVAILABLE;
    }

    public static function fromOspp(string $osppValue): self
    {
        return self::from(strtolower($osppValue));
    }

    public function toOspp(): string
    {
        return ucfirst($this->value);
    }
}
