<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum SessionStatus: string
{
    case PENDING = 'pending';
    case AUTHORIZED = 'authorized';
    case ACTIVE = 'active';
    case STOPPING = 'stopping';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::COMPLETED || $this === self::FAILED;
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function hasTimeout(): bool
    {
        return in_array($this, [self::PENDING, self::AUTHORIZED, self::ACTIVE, self::STOPPING], true);
    }

    public function isBillable(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isStoppable(): bool
    {
        return $this === self::ACTIVE;
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
