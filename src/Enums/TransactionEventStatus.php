<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum TransactionEventStatus: string
{
    case ACCEPTED = 'Accepted';
    case DUPLICATE = 'Duplicate';
    case REJECTED = 'Rejected';
    case RETRY_LATER = 'RetryLater';
}
