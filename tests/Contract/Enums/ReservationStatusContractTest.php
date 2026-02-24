<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Contract\Enums;

use OneStopPay\OsppProtocol\Enums\ReservationStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for ReservationStatus enum.
 *
 * Pins behavioral method counts and identity checks for
 * holdsBay, triggersRefund, isCancellable, isConvertible, isTerminal.
 */
final class ReservationStatusContractTest extends TestCase
{
    #[Test]
    public function holdsBay_count_is_exactly_1(): void
    {
        $holding = array_filter(
            ReservationStatus::cases(),
            fn (ReservationStatus $s) => $s->holdsBay(),
        );
        self::assertCount(1, $holding);
        self::assertSame(ReservationStatus::CONFIRMED, array_values($holding)[0]);
    }

    #[Test]
    public function triggersRefund_count_is_exactly_2(): void
    {
        $refunding = array_filter(
            ReservationStatus::cases(),
            fn (ReservationStatus $s) => $s->triggersRefund(),
        );
        self::assertCount(2, $refunding);

        $refundingSet = array_values($refunding);
        self::assertContains(ReservationStatus::EXPIRED, $refundingSet);
        self::assertContains(ReservationStatus::CANCELLED, $refundingSet);
    }

    #[Test]
    public function isCancellable_count_is_exactly_2(): void
    {
        $cancellable = array_filter(
            ReservationStatus::cases(),
            fn (ReservationStatus $s) => $s->isCancellable(),
        );
        self::assertCount(2, $cancellable);

        $cancellableSet = array_values($cancellable);
        self::assertContains(ReservationStatus::PENDING, $cancellableSet);
        self::assertContains(ReservationStatus::CONFIRMED, $cancellableSet);
    }

    #[Test]
    public function isConvertible_count_is_exactly_1(): void
    {
        $convertible = array_filter(
            ReservationStatus::cases(),
            fn (ReservationStatus $s) => $s->isConvertible(),
        );
        self::assertCount(1, $convertible);
        self::assertSame(ReservationStatus::CONFIRMED, array_values($convertible)[0]);
    }

    #[Test]
    public function isTerminal_count_is_exactly_3(): void
    {
        $terminal = array_filter(
            ReservationStatus::cases(),
            fn (ReservationStatus $s) => $s->isTerminal(),
        );
        self::assertCount(3, $terminal);

        $terminalSet = array_values($terminal);
        self::assertContains(ReservationStatus::ACTIVE, $terminalSet);
        self::assertContains(ReservationStatus::EXPIRED, $terminalSet);
        self::assertContains(ReservationStatus::CANCELLED, $terminalSet);
    }
}
