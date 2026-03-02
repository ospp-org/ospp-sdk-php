<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum DataTransferStatus: string
{
    case ACCEPTED = 'Accepted';
    case REJECTED = 'Rejected';
    case UNKNOWN_VENDOR = 'UnknownVendor';
    case UNKNOWN_DATA = 'UnknownData';
}
