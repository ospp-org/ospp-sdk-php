<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\BayStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BayStatusTest extends TestCase
{
    /**
     * The six states that may cross the wire. Derived from `isReportable()`
     * rather than listed, so adding a case cannot silently skip it here.
     *
     * @return list<BayStatus>
     */
    private static function reportable(): array
    {
        return array_values(array_filter(
            BayStatus::cases(),
            static fn (BayStatus $s): bool => $s->isReportable(),
        ));
    }

    #[Test]
    public function it_has_exactly_seven_cases(): void
    {
        // Seven states, six reportable. The wire narrowed in spec v0.10.0; the
        // FSM did not, and `UNKNOWN` remains a state this enum must express.
        self::assertCount(7, BayStatus::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('unknown', BayStatus::UNKNOWN->value);
        self::assertSame('available', BayStatus::AVAILABLE->value);
        self::assertSame('reserved', BayStatus::RESERVED->value);
        self::assertSame('occupied', BayStatus::OCCUPIED->value);
        self::assertSame('finishing', BayStatus::FINISHING->value);
        self::assertSame('faulted', BayStatus::FAULTED->value);
        self::assertSame('unavailable', BayStatus::UNAVAILABLE->value);
    }

    // --- isInitial ---

    #[Test]
    public function is_initial_returns_true_only_for_unknown(): void
    {
        self::assertTrue(BayStatus::UNKNOWN->isInitial());

        self::assertFalse(BayStatus::AVAILABLE->isInitial());
        self::assertFalse(BayStatus::RESERVED->isInitial());
        self::assertFalse(BayStatus::OCCUPIED->isInitial());
        self::assertFalse(BayStatus::FINISHING->isInitial());
        self::assertFalse(BayStatus::FAULTED->isInitial());
        self::assertFalse(BayStatus::UNAVAILABLE->isInitial());
    }

    // --- canStartSession ---

    #[Test]
    public function can_start_session_returns_true_for_available_and_reserved(): void
    {
        self::assertTrue(BayStatus::AVAILABLE->canStartSession());
        self::assertTrue(BayStatus::RESERVED->canStartSession());

        self::assertFalse(BayStatus::UNKNOWN->canStartSession());
        self::assertFalse(BayStatus::OCCUPIED->canStartSession());
        self::assertFalse(BayStatus::FINISHING->canStartSession());
        self::assertFalse(BayStatus::FAULTED->canStartSession());
        self::assertFalse(BayStatus::UNAVAILABLE->canStartSession());
    }

    // --- canReserve ---

    #[Test]
    public function can_reserve_returns_true_only_for_available(): void
    {
        self::assertTrue(BayStatus::AVAILABLE->canReserve());

        self::assertFalse(BayStatus::UNKNOWN->canReserve());
        self::assertFalse(BayStatus::RESERVED->canReserve());
        self::assertFalse(BayStatus::OCCUPIED->canReserve());
        self::assertFalse(BayStatus::FINISHING->canReserve());
        self::assertFalse(BayStatus::FAULTED->canReserve());
        self::assertFalse(BayStatus::UNAVAILABLE->canReserve());
    }

    // --- isFaulted ---

    #[Test]
    public function is_faulted_returns_true_only_for_faulted(): void
    {
        self::assertTrue(BayStatus::FAULTED->isFaulted());

        self::assertFalse(BayStatus::UNKNOWN->isFaulted());
        self::assertFalse(BayStatus::AVAILABLE->isFaulted());
        self::assertFalse(BayStatus::RESERVED->isFaulted());
        self::assertFalse(BayStatus::OCCUPIED->isFaulted());
        self::assertFalse(BayStatus::FINISHING->isFaulted());
        self::assertFalse(BayStatus::UNAVAILABLE->isFaulted());
    }

    // --- acceptsSessions ---

    #[Test]
    public function accepts_sessions_returns_true_for_available_and_reserved(): void
    {
        self::assertTrue(BayStatus::AVAILABLE->acceptsSessions());
        self::assertTrue(BayStatus::RESERVED->acceptsSessions());

        self::assertFalse(BayStatus::UNKNOWN->acceptsSessions());
        self::assertFalse(BayStatus::OCCUPIED->acceptsSessions());
        self::assertFalse(BayStatus::FINISHING->acceptsSessions());
        self::assertFalse(BayStatus::FAULTED->acceptsSessions());
        self::assertFalse(BayStatus::UNAVAILABLE->acceptsSessions());
    }

    // --- acceptsReservations ---

    #[Test]
    public function accepts_reservations_returns_true_only_for_available(): void
    {
        self::assertTrue(BayStatus::AVAILABLE->acceptsReservations());

        self::assertFalse(BayStatus::UNKNOWN->acceptsReservations());
        self::assertFalse(BayStatus::RESERVED->acceptsReservations());
        self::assertFalse(BayStatus::OCCUPIED->acceptsReservations());
        self::assertFalse(BayStatus::FINISHING->acceptsReservations());
        self::assertFalse(BayStatus::FAULTED->acceptsReservations());
        self::assertFalse(BayStatus::UNAVAILABLE->acceptsReservations());
    }

    // --- fromOspp (PascalCase from wire → lowercase enum) ---

    #[Test]
    public function from_ospp_handles_pascal_case_wire_values(): void
    {
        self::assertSame(BayStatus::AVAILABLE, BayStatus::fromOspp('Available'));
        self::assertSame(BayStatus::RESERVED, BayStatus::fromOspp('Reserved'));
        self::assertSame(BayStatus::OCCUPIED, BayStatus::fromOspp('Occupied'));
        self::assertSame(BayStatus::FINISHING, BayStatus::fromOspp('Finishing'));
        self::assertSame(BayStatus::FAULTED, BayStatus::fromOspp('Faulted'));
        self::assertSame(BayStatus::UNAVAILABLE, BayStatus::fromOspp('Unavailable'));
    }

    #[Test]
    public function from_ospp_also_handles_lowercase_input(): void
    {
        foreach (self::reportable() as $status) {
            self::assertSame($status, BayStatus::fromOspp($status->value));
        }
    }

    #[Test]
    public function from_ospp_throws_for_invalid_value(): void
    {
        $this->expectException(\ValueError::class);
        BayStatus::fromOspp('nonexistent');
    }

    /**
     * The inverse of the assertion this test file used to make. `Unknown` was a
     * wire value until spec v0.10.0; it is now refused at the boundary in both
     * casings. Inverted rather than deleted, so nothing silently readmits it.
     */
    #[Test]
    public function from_ospp_rejects_unknown_pascal_case(): void
    {
        $this->expectException(\ValueError::class);
        BayStatus::fromOspp('Unknown');
    }

    #[Test]
    public function from_ospp_rejects_unknown_lowercase(): void
    {
        $this->expectException(\ValueError::class);
        BayStatus::fromOspp('unknown');
    }

    #[Test]
    public function unknown_is_the_only_unreportable_case(): void
    {
        self::assertFalse(BayStatus::UNKNOWN->isReportable());

        foreach (BayStatus::cases() as $status) {
            if ($status === BayStatus::UNKNOWN) {
                continue;
            }
            self::assertTrue($status->isReportable(), "BayStatus::{$status->name} should be reportable");
        }

        self::assertCount(6, self::reportable());
    }

    // --- toOspp (lowercase enum → PascalCase for wire) ---

    #[Test]
    public function to_ospp_returns_pascal_case_value(): void
    {
        self::assertSame('Unknown', BayStatus::UNKNOWN->toOspp());
        self::assertSame('Available', BayStatus::AVAILABLE->toOspp());
        self::assertSame('Reserved', BayStatus::RESERVED->toOspp());
        self::assertSame('Occupied', BayStatus::OCCUPIED->toOspp());
        self::assertSame('Finishing', BayStatus::FINISHING->toOspp());
        self::assertSame('Faulted', BayStatus::FAULTED->toOspp());
        self::assertSame('Unavailable', BayStatus::UNAVAILABLE->toOspp());
    }

    #[Test]
    public function from_ospp_roundtrips_with_to_ospp(): void
    {
        foreach (self::reportable() as $status) {
            self::assertSame($status, BayStatus::fromOspp($status->toOspp()));
        }
    }

    /**
     * `toOspp()` is total and `fromOspp()` is not, so the round trip is too —
     * and that asymmetry is the point, not an oversight. `Unknown` renders (for
     * logs and display) but does not parse back off the wire, because nothing
     * may put it there.
     */
    #[Test]
    public function unknown_renders_but_does_not_round_trip(): void
    {
        self::assertSame('Unknown', BayStatus::UNKNOWN->toOspp());

        $this->expectException(\ValueError::class);
        BayStatus::fromOspp(BayStatus::UNKNOWN->toOspp());
    }

    // --- from / tryFrom ---

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(BayStatus::tryFrom('AVAILABLE'));
        self::assertNull(BayStatus::tryFrom(''));
        self::assertNull(BayStatus::tryFrom('invalid'));
    }
}
