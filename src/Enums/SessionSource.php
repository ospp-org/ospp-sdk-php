<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Enums;

enum SessionSource: string
{
    case MOBILE_APP = 'mobile_app';
    case WEB_PAYMENT = 'web_payment';
}
