<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum PricingType: string
{
    case PER_MINUTE = 'PerMinute';
    case FIXED = 'Fixed';
}
