<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum StationConnectivity: string
{
    case ONLINE = 'Online';
    case OFFLINE = 'Offline';
}
