<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum SessionEndReason: string
{
    case TIMER_EXPIRED = 'TimerExpired';
    case FAULT = 'Fault';
    case LOCAL = 'Local';
    case LOCAL_OUT_OF_CREDIT = 'LocalOutOfCredit';
    case DEAUTHORIZED = 'Deauthorized';
}
