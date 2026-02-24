<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Enums;

enum MessageType: string
{
    case REQUEST = 'REQUEST';
    case RESPONSE = 'RESPONSE';
    case EVENT = 'EVENT';
}
