<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum BootReason: string
{
    case POWER_ON = 'PowerOn';
    case WATCHDOG = 'Watchdog';
    case FIRMWARE_UPDATE = 'FirmwareUpdate';
    case MANUAL_RESET = 'ManualReset';
    case SCHEDULED_RESET = 'ScheduledReset';
    case ERROR_RECOVERY = 'ErrorRecovery';
}
