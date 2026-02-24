<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Unit\Enums;

use OneStopPay\OsppProtocol\Enums\SessionStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionStatusTest extends TestCase
{
    #[Test]
    public function it_has_exactly_six_cases(): void
    {
        self::assertCount(6, SessionStatus::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('pending', SessionStatus::PENDING->value);
        self::assertSame('authorized', SessionStatus::AUTHORIZED->value);
        self::assertSame('active', SessionStatus::ACTIVE->value);
        self::assertSame('stopping', SessionStatus::STOPPING->value);
        self::assertSame('completed', SessionStatus::COMPLETED->value);
        self::assertSame('failed', SessionStatus::FAILED->value);
    }

    // --- isTerminal ---

    #[Test]
    public function is_terminal_returns_true_for_completed_and_failed(): void
    {
        self::assertTrue(SessionStatus::COMPLETED->isTerminal());
        self::assertTrue(SessionStatus::FAILED->isTerminal());

        self::assertFalse(SessionStatus::PENDING->isTerminal());
        self::assertFalse(SessionStatus::AUTHORIZED->isTerminal());
        self::assertFalse(SessionStatus::ACTIVE->isTerminal());
        self::assertFalse(SessionStatus::STOPPING->isTerminal());
    }

    // --- isActive ---

    #[Test]
    public function is_active_returns_true_only_for_active(): void
    {
        self::assertTrue(SessionStatus::ACTIVE->isActive());

        self::assertFalse(SessionStatus::PENDING->isActive());
        self::assertFalse(SessionStatus::AUTHORIZED->isActive());
        self::assertFalse(SessionStatus::STOPPING->isActive());
        self::assertFalse(SessionStatus::COMPLETED->isActive());
        self::assertFalse(SessionStatus::FAILED->isActive());
    }

    // --- hasTimeout ---

    #[Test]
    public function has_timeout_returns_true_for_non_terminal_states(): void
    {
        self::assertTrue(SessionStatus::PENDING->hasTimeout());
        self::assertTrue(SessionStatus::AUTHORIZED->hasTimeout());
        self::assertTrue(SessionStatus::ACTIVE->hasTimeout());
        self::assertTrue(SessionStatus::STOPPING->hasTimeout());

        self::assertFalse(SessionStatus::COMPLETED->hasTimeout());
        self::assertFalse(SessionStatus::FAILED->hasTimeout());
    }

    // --- isBillable ---

    #[Test]
    public function is_billable_returns_true_only_for_active(): void
    {
        self::assertTrue(SessionStatus::ACTIVE->isBillable());

        self::assertFalse(SessionStatus::PENDING->isBillable());
        self::assertFalse(SessionStatus::AUTHORIZED->isBillable());
        self::assertFalse(SessionStatus::STOPPING->isBillable());
        self::assertFalse(SessionStatus::COMPLETED->isBillable());
        self::assertFalse(SessionStatus::FAILED->isBillable());
    }

    // --- isStoppable ---

    #[Test]
    public function is_stoppable_returns_true_only_for_active(): void
    {
        self::assertTrue(SessionStatus::ACTIVE->isStoppable());

        self::assertFalse(SessionStatus::PENDING->isStoppable());
        self::assertFalse(SessionStatus::AUTHORIZED->isStoppable());
        self::assertFalse(SessionStatus::STOPPING->isStoppable());
        self::assertFalse(SessionStatus::COMPLETED->isStoppable());
        self::assertFalse(SessionStatus::FAILED->isStoppable());
    }

    // --- fromOspp ---

    #[Test]
    public function from_ospp_creates_correct_status_from_lowercase(): void
    {
        foreach (SessionStatus::cases() as $status) {
            self::assertSame($status, SessionStatus::fromOspp($status->value));
        }
    }

    #[Test]
    public function from_ospp_creates_correct_status_from_pascal_case(): void
    {
        self::assertSame(SessionStatus::PENDING, SessionStatus::fromOspp('Pending'));
        self::assertSame(SessionStatus::AUTHORIZED, SessionStatus::fromOspp('Authorized'));
        self::assertSame(SessionStatus::ACTIVE, SessionStatus::fromOspp('Active'));
        self::assertSame(SessionStatus::STOPPING, SessionStatus::fromOspp('Stopping'));
        self::assertSame(SessionStatus::COMPLETED, SessionStatus::fromOspp('Completed'));
        self::assertSame(SessionStatus::FAILED, SessionStatus::fromOspp('Failed'));
    }

    #[Test]
    public function from_ospp_throws_for_invalid_value(): void
    {
        $this->expectException(\ValueError::class);
        SessionStatus::fromOspp('nonexistent');
    }

    // --- toOspp ---

    #[Test]
    public function to_ospp_returns_pascal_case_string(): void
    {
        self::assertSame('Pending', SessionStatus::PENDING->toOspp());
        self::assertSame('Authorized', SessionStatus::AUTHORIZED->toOspp());
        self::assertSame('Active', SessionStatus::ACTIVE->toOspp());
        self::assertSame('Stopping', SessionStatus::STOPPING->toOspp());
        self::assertSame('Completed', SessionStatus::COMPLETED->toOspp());
        self::assertSame('Failed', SessionStatus::FAILED->toOspp());
    }

    #[Test]
    public function from_ospp_roundtrips_with_to_ospp(): void
    {
        foreach (SessionStatus::cases() as $status) {
            self::assertSame($status, SessionStatus::fromOspp($status->toOspp()));
        }
    }

    // --- from / tryFrom ---

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(SessionStatus::tryFrom('PENDING'));
        self::assertNull(SessionStatus::tryFrom(''));
        self::assertNull(SessionStatus::tryFrom('starting'));
    }
}
