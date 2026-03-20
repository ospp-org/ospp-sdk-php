<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum SessionEndReason: string
{
    case TIMER_EXPIRED = 'TimerExpired';
    case FAULT = 'Fault';
}
