<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Enums;

enum DiagnosticsStatus: string
{
    case PENDING = 'pending';
    case COLLECTING = 'collecting';
    case UPLOADING = 'uploading';
    case UPLOADED = 'uploaded';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::UPLOADED || $this === self::FAILED;
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::COLLECTING, self::FAILED],
            self::COLLECTING => [self::UPLOADING, self::FAILED],
            self::UPLOADING => [self::UPLOADED, self::FAILED],
            self::UPLOADED, self::FAILED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public static function fromNotificationStatus(string $status): self
    {
        return match ($status) {
            'Collecting' => self::COLLECTING,
            'Uploading' => self::UPLOADING,
            'Uploaded' => self::UPLOADED,
            'Failed' => self::FAILED,
            default => throw new \InvalidArgumentException("Unknown diagnostics notification status: {$status}"),
        };
    }
}
