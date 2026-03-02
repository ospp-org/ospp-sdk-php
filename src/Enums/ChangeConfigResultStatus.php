<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum ChangeConfigResultStatus: string
{
    case ACCEPTED = 'Accepted';
    case REBOOT_REQUIRED = 'RebootRequired';
    case REJECTED = 'Rejected';
    case NOT_SUPPORTED = 'NotSupported';
}
