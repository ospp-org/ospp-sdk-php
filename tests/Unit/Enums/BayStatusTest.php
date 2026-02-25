<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\BayStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BayStatusTest extends TestCase
{
    #[Test]
    public function it_has_exactly_seven_cases(): void
    {
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
        self::assertSame(BayStatus::UNKNOWN, BayStatus::fromOspp('Unknown'));
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
        foreach (BayStatus::cases() as $status) {
            self::assertSame($status, BayStatus::fromOspp($status->value));
        }
    }

    #[Test]
    public function from_ospp_throws_for_invalid_value(): void
    {
        $this->expectException(\ValueError::class);
        BayStatus::fromOspp('nonexistent');
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
        foreach (BayStatus::cases() as $status) {
            self::assertSame($status, BayStatus::fromOspp($status->toOspp()));
        }
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
