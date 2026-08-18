<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

/**
 * The STATION's diagnostics upload state — spec/05-state-machines.md §8.2.
 *
 * Five states. `idle` is the entry and where both outcomes return; nothing here
 * is terminal, because §8.3: "a station that treats it as terminal can run one
 * diagnostics upload and never a second."
 *
 * This is NOT {@see DiagnosticsStatus}. §8.5 fixes the division: that enum is a
 * SERVER's record of one requested upload and legitimately carries `pending`, a
 * state the station does not have — it has not been asked yet, or it has and has
 * already answered. Until 0.23.0 this SDK had only the record enum and used it as
 * the machine, which is how it came to disagree with sdk-ts on three edges with
 * both suites green and neither able to cite a table.
 *
 * Only four of the five states have a wire representation (§8.4): `idle` is
 * reported by nothing, in either direction.
 */
enum DiagnosticsState: string
{
    case IDLE = 'idle';
    case COLLECTING = 'collecting';
    case UPLOADING = 'uploading';
    case UPLOADED = 'uploaded';
    case FAILED = 'failed';

    /**
     * No state of this machine is terminal — §8.3.
     *
     * Kept as a method rather than omitted so the canonical-table contract can
     * assert `isTerminal() <=> has no outgoing edge` the same way the firmware
     * mirror does, and so a future edit that makes one terminal fails there
     * instead of passing silently.
     */
    public function isTerminal(): bool
    {
        return false;
    }

    public function isActive(): bool
    {
        return $this !== self::IDLE;
    }

    /**
     * Whether a DiagnosticsNotification can report this state — §8.4.
     */
    public function isReportable(): bool
    {
        return $this !== self::IDLE;
    }

    /**
     * The wire -> machine bridge. §8.4's mapping, read in the direction a server
     * consumes it.
     *
     * The four values are exactly the enum of
     * `schemas/mqtt/diagnostics-notification.schema.json`. There is deliberately
     * no inverse that produces `idle` from a wire value: §8.4 states that
     * `Uploaded -> Idle` and `Failed -> Idle` "have no wire trigger, and a server
     * must not wait for one."
     *
     * @throws \InvalidArgumentException on any value outside the notification enum
     */
    public static function fromNotificationStatus(string $status): self
    {
        return match ($status) {
            'Collecting' => self::COLLECTING,
            'Uploading' => self::UPLOADING,
            'Uploaded' => self::UPLOADED,
            'Failed' => self::FAILED,
            default => throw new \InvalidArgumentException(
                "Unknown diagnostics notification status: {$status}",
            ),
        };
    }

    /**
     * The machine -> wire direction. Null for `idle`, which no notification
     * carries (§8.4).
     */
    public function toNotificationStatus(): ?string
    {
        return match ($this) {
            self::IDLE => null,
            self::COLLECTING => 'Collecting',
            self::UPLOADING => 'Uploading',
            self::UPLOADED => 'Uploaded',
            self::FAILED => 'Failed',
        };
    }
}
