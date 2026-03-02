<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum Severity: string
{
    case CRITICAL = 'CRITICAL';
    case ERROR = 'ERROR';
    case WARNING = 'WARNING';
    case INFO = 'INFO';

    public function isActionRequired(): bool
    {
        return $this === self::CRITICAL || $this === self::ERROR;
    }

    /**
     * Create from PascalCase wire value (SecurityEvent payload).
     */
    public static function fromOspp(string $osppValue): self
    {
        return self::from(strtoupper($osppValue));
    }

    /**
     * Convert to PascalCase wire value for SecurityEvent payload.
     */
    public function toOspp(): string
    {
        return ucfirst(strtolower($this->value));
    }
}
