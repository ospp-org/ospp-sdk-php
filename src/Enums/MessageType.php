<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum MessageType: string
{
    case REQUEST = 'REQUEST';
    case RESPONSE = 'RESPONSE';
    case EVENT = 'EVENT';
}
