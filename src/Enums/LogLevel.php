<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum LogLevel: string
{
    case DEBUG = 'Debug';
    case INFO = 'Info';
    case WARN = 'Warn';
    case ERROR = 'Error';
}
