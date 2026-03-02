<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum BleServiceStatus: string
{
    case STARTING = 'Starting';
    case RUNNING = 'Running';
    case COMPLETE = 'Complete';
    case RECEIPT_READY = 'ReceiptReady';
    case ERROR = 'Error';
}
