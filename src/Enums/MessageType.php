<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum MessageType: string
{
    case REQUEST = 'Request';
    case RESPONSE = 'Response';
    case EVENT = 'Event';
}
