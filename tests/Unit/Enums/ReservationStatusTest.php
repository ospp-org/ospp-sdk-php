<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\ReservationStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReservationStatusTest extends TestCase
{
    #[Test]
    public function it_has_exactly_five_cases(): void
    {
        self::assertCount(5, ReservationStatus::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('pending', ReservationStatus::PENDING->value);
        self::assertSame('confirmed', ReservationStatus::CONFIRMED->value);
        self::assertSame('active', ReservationStatus::ACTIVE->value);
        self::assertSame('expired', ReservationStatus::EXPIRED->value);
        self::assertSame('cancelled', ReservationStatus::CANCELLED->value);
    }

    // --- isTerminal ---

    #[Test]
    public function is_terminal_returns_true_for_active_expired_and_cancelled(): void
    {
        self::assertTrue(ReservationStatus::ACTIVE->isTerminal());
        self::assertTrue(ReservationStatus::EXPIRED->isTerminal());
        self::assertTrue(ReservationStatus::CANCELLED->isTerminal());
    }

    #[Test]
    public function is_terminal_returns_false_for_pending_and_confirmed(): void
    {
        self::assertFalse(ReservationStatus::PENDING->isTerminal());
        self::assertFalse(ReservationStatus::CONFIRMED->isTerminal());
    }

    // --- isCancellable ---

    #[Test]
    public function is_cancellable_returns_true_for_pending_and_confirmed(): void
    {
        self::assertTrue(ReservationStatus::PENDING->isCancellable());
        self::assertTrue(ReservationStatus::CONFIRMED->isCancellable());
    }

    #[Test]
    public function is_cancellable_returns_false_for_terminal_states(): void
    {
        self::assertFalse(ReservationStatus::ACTIVE->isCancellable());
        self::assertFalse(ReservationStatus::EXPIRED->isCancellable());
        self::assertFalse(ReservationStatus::CANCELLED->isCancellable());
    }

    // --- isConvertible ---

    #[Test]
    public function is_convertible_returns_true_only_for_confirmed(): void
    {
        self::assertTrue(ReservationStatus::CONFIRMED->isConvertible());

        self::assertFalse(ReservationStatus::PENDING->isConvertible());
        self::assertFalse(ReservationStatus::ACTIVE->isConvertible());
        self::assertFalse(ReservationStatus::EXPIRED->isConvertible());
        self::assertFalse(ReservationStatus::CANCELLED->isConvertible());
    }

    // --- holdsBay ---

    #[Test]
    public function holds_bay_returns_true_only_for_confirmed(): void
    {
        self::assertTrue(ReservationStatus::CONFIRMED->holdsBay());

        self::assertFalse(ReservationStatus::PENDING->holdsBay());
        self::assertFalse(ReservationStatus::ACTIVE->holdsBay());
        self::assertFalse(ReservationStatus::EXPIRED->holdsBay());
        self::assertFalse(ReservationStatus::CANCELLED->holdsBay());
    }

    // --- triggersRefund ---

    #[Test]
    public function triggers_refund_returns_true_for_expired_and_cancelled(): void
    {
        self::assertTrue(ReservationStatus::EXPIRED->triggersRefund());
        self::assertTrue(ReservationStatus::CANCELLED->triggersRefund());
    }

    #[Test]
    public function triggers_refund_returns_false_for_non_refundable_states(): void
    {
        self::assertFalse(ReservationStatus::PENDING->triggersRefund());
        self::assertFalse(ReservationStatus::CONFIRMED->triggersRefund());
        self::assertFalse(ReservationStatus::ACTIVE->triggersRefund());
    }

    // --- from / tryFrom ---

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        foreach (ReservationStatus::cases() as $status) {
            self::assertSame($status, ReservationStatus::from($status->value));
        }
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(ReservationStatus::tryFrom('PENDING'));
        self::assertNull(ReservationStatus::tryFrom(''));
        self::assertNull(ReservationStatus::tryFrom('invalid'));
    }
}
