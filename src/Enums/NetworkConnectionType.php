<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum NetworkConnectionType: string
{
    case ETHERNET = 'Ethernet';
    case WIFI = 'Wifi';
    case CELLULAR = 'Cellular';
}
