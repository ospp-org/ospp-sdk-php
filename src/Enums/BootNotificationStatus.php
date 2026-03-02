<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum BootNotificationStatus: string
{
    case ACCEPTED = 'Accepted';
    case REJECTED = 'Rejected';
    case PENDING = 'Pending';
}
