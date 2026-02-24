<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Enums;

enum FirmwareUpdateStatus: string
{
    case IDLE = 'idle';
    case DOWNLOADING = 'downloading';
    case DOWNLOADED = 'downloaded';
    case VERIFYING = 'verifying';
    case VERIFIED = 'verified';
    case INSTALLING = 'installing';
    case INSTALLED = 'installed';
    case REBOOTING = 'rebooting';
    case ACTIVATED = 'activated';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::ACTIVATED || $this === self::FAILED;
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal() && $this !== self::IDLE;
    }

    public static function fromNotificationStatus(string $notificationStatus): self
    {
        return match ($notificationStatus) {
            'Downloading' => self::DOWNLOADING,
            'Downloaded' => self::DOWNLOADED,
            'Installing' => self::INSTALLING,
            'Installed' => self::INSTALLED,
            'Failed' => self::FAILED,
            default => throw new \InvalidArgumentException("Unknown firmware notification status: {$notificationStatus}"),
        };
    }
}
