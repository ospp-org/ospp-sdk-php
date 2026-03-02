<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum ResetType: string
{
    case SOFT = 'Soft';
    case HARD = 'Hard';
}
