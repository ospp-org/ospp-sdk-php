<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

/**
 * A SERVER's record of one requested diagnostics upload — spec/05-state-machines.md §8.5.
 *
 * This is NOT the station's machine; that is {@see DiagnosticsState} and it lives in
 * `StateMachines\DiagnosticsTransitions`. The two model different subjects, which is
 * what §8.5 exists to fix:
 *
 *   "A server holds one row per requested upload, and that row needs a state this
 *   machine does not have: the interval between dispatching GetDiagnostics and
 *   receiving its RESPONSE. Two implementations reached for the same name for it —
 *   `pending` — and then disagreed about everything downstream, because a record of a
 *   REQUEST and a machine of a STATION are not the same object."
 *
 * So the six edges below are the RECORD's and they are correct as they stand:
 *
 *   * `pending -> failed` is the station answering `Rejected`. On the station's side
 *     nothing happened at all — the machine never left `idle` and no notification was
 *     sent — but the row is closed, and this is the edge that closes it.
 *   * `uploaded` and `failed` ARE terminal here. A row is closed by its outcome and is
 *     not reopened; the next GetDiagnostics opens a new row. That is the opposite of
 *     the station machine, where treating them as terminal makes it single-use.
 *
 * §8.5: "A server MAY name its record's states as it likes, but it MUST NOT publish
 * them as this machine's, and a conformance test MUST NOT assert a transition of the
 * server's record against §8.3."
 */
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

    /**
     * The wire values a notification can carry, mapped onto the record.
     *
     * `pending` is deliberately not producible here: it is not a wire value, and
     * `schemas/mqtt/diagnostics-notification.schema.json` does not contain it.
     *
     * For the STATION machine's bridge, use
     * {@see DiagnosticsState::fromNotificationStatus()} — same four values, different
     * destination.
     */
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
