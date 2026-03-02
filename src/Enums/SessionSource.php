<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum SessionSource: string
{
    case MOBILE_APP = 'MobileApp';
    case WEB_PAYMENT = 'WebPayment';
}
