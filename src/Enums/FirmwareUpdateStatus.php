<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

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

    /**
     * `Activated` is the only terminal state.
     *
     * spec/05-state-machines.md §6.3: "`Failed` has exactly one outgoing edge,
     * `Failed -> Idle`; it is **not** terminal, and a machine that treats it as
     * terminal can run one firmware update and never a second."
     */
    public function isTerminal(): bool
    {
        return $this === self::ACTIVATED;
    }

    /**
     * An update in flight. Enumerated rather than derived from isTerminal():
     * `Failed` is not terminal but it is not in flight either — it is rolling
     * back — so the two predicates do not partition the same way.
     */
    public function isActive(): bool
    {
        return $this !== self::IDLE
            && $this !== self::ACTIVATED
            && $this !== self::FAILED;
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
