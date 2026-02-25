<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum SessionSource: string
{
    case MOBILE_APP = 'mobile_app';
    case WEB_PAYMENT = 'web_payment';
}
