<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

/**
 * The seven states of the bay FSM.
 *
 * Six of them are REPORTABLE — they may appear in `status` or `previousStatus`
 * on the wire. `UNKNOWN` is the seventh and is not one of them: a station enters
 * it at power-on and leaves it by self-test, and a server enters it on
 * connection loss and leaves it on the next accepted StatusNotification. Both
 * parties hold it; neither transmits it (spec 05-state-machines.md §1.2).
 *
 * The case stays. `UNKNOWN` is a real state this enum must be able to express —
 * it is what a server holds a bay at before its first report, and it is a
 * persisted domain value. What narrowed in spec v0.10.0 is the WIRE, and so
 * what narrowed here is {@see self::fromOspp()}, the wire boundary — not the
 * vocabulary.
 */
enum BayStatus: string
{
    case UNKNOWN = 'unknown';
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case OCCUPIED = 'occupied';
    case FINISHING = 'finishing';
    case FAULTED = 'faulted';
    case UNAVAILABLE = 'unavailable';

    public function isInitial(): bool
    {
        return $this === self::UNKNOWN;
    }

    public function canStartSession(): bool
    {
        return $this === self::AVAILABLE || $this === self::RESERVED;
    }

    public function canReserve(): bool
    {
        return $this === self::AVAILABLE;
    }

    public function isFaulted(): bool
    {
        return $this === self::FAULTED;
    }

    public function acceptsSessions(): bool
    {
        return $this === self::AVAILABLE || $this === self::RESERVED;
    }

    public function acceptsReservations(): bool
    {
        return $this === self::AVAILABLE;
    }

    /**
     * Whether this state may be carried in a message.
     *
     * True for the six reportable states, false for {@see self::UNKNOWN}. Use
     * this to gate a value before it reaches the wire; {@see self::fromOspp()}
     * enforces the same rule on the way in.
     */
    public function isReportable(): bool
    {
        return $this !== self::UNKNOWN;
    }

    /**
     * Parse a bay status off the wire.
     *
     * Rejects `Unknown`. It is a valid state of this enum but not a valid wire
     * value, so a message carrying it is non-conforming and is refused at the
     * boundary rather than admitted as a domain value it happens to match.
     *
     * @throws \ValueError if the value is not one of the six reportable states
     */
    public static function fromOspp(string $osppValue): self
    {
        $case = self::from(strtolower($osppValue));

        if (! $case->isReportable()) {
            throw new \ValueError(sprintf(
                '"%s" is not a reportable bay status. `Unknown` is held by both parties and '
                .'transmitted by neither (spec 05-state-machines.md §1.2); a message carrying '
                .'it is non-conforming. Reportable: %s.',
                $osppValue,
                implode(', ', array_map(
                    static fn (self $s): string => $s->toOspp(),
                    array_filter(self::cases(), static fn (self $s): bool => $s->isReportable()),
                )),
            ));
        }

        return $case;
    }

    /**
     * Render this status in wire form (PascalCase).
     *
     * Total on purpose, including for {@see self::UNKNOWN} — it is used for
     * logging and display as well as for serialisation, and a partial function
     * here would throw in a log line. It does NOT follow that the result may be
     * transmitted: gate on {@see self::isReportable()} first. The consequence is
     * that `fromOspp(toOspp())` round-trips for the six reportable states and
     * not for `UNKNOWN`, which is precisely the asymmetry the spec describes.
     */
    public function toOspp(): string
    {
        return ucfirst($this->value);
    }
}
