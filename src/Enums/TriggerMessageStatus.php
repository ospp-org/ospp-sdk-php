<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum TriggerMessageStatus: string
{
    case ACCEPTED = 'Accepted';
    case REJECTED = 'Rejected';
    case NOT_IMPLEMENTED = 'NotImplemented';
}
