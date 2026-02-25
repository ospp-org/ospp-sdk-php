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
}
