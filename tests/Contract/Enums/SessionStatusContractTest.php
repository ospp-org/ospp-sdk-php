<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Enums;

use Ospp\Protocol\Enums\SessionSource;
use Ospp\Protocol\Enums\SessionStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for SessionStatus enum.
 *
 * Pins behavioral counts, wire format, roundtrip safety,
 * and guards against phantom enum cases.
 */
final class SessionStatusContractTest extends TestCase
{
    #[Test]
    public function isBillable_count_is_exactly_1_and_is_ACTIVE(): void
    {
        $billable = array_filter(SessionStatus::cases(), fn (SessionStatus $s) => $s->isBillable());
        self::assertCount(1, $billable);
        self::assertSame(SessionStatus::ACTIVE, array_values($billable)[0]);
    }

    #[Test]
    public function isStoppable_count_is_exactly_1(): void
    {
        $stoppable = array_filter(SessionStatus::cases(), fn (SessionStatus $s) => $s->isStoppable());
        self::assertCount(1, $stoppable);
        self::assertSame(SessionStatus::ACTIVE, array_values($stoppable)[0]);
    }

    #[Test]
    public function isTerminal_count_is_exactly_2(): void
    {
        $terminal = array_filter(SessionStatus::cases(), fn (SessionStatus $s) => $s->isTerminal());
        self::assertCount(2, $terminal);

        $terminalSet = array_values($terminal);
        self::assertContains(SessionStatus::COMPLETED, $terminalSet);
        self::assertContains(SessionStatus::FAILED, $terminalSet);
    }

    #[Test]
    public function hasTimeout_count_is_exactly_4(): void
    {
        $withTimeout = array_filter(SessionStatus::cases(), fn (SessionStatus $s) => $s->hasTimeout());
        self::assertCount(4, $withTimeout);

        $withTimeoutSet = array_values($withTimeout);
        self::assertContains(SessionStatus::PENDING, $withTimeoutSet);
        self::assertContains(SessionStatus::AUTHORIZED, $withTimeoutSet);
        self::assertContains(SessionStatus::ACTIVE, $withTimeoutSet);
        self::assertContains(SessionStatus::STOPPING, $withTimeoutSet);
    }

    #[Test]
    public function fromOspp_converts_PascalCase_for_all_6_cases(): void
    {
        foreach (SessionStatus::cases() as $case) {
            self::assertSame(
                $case,
                SessionStatus::fromOspp(ucfirst($case->value)),
                "fromOspp(ucfirst('{$case->value}')) should return SessionStatus::{$case->name}",
            );
        }
    }

    #[Test]
    public function toOspp_returns_PascalCase_for_all_6_cases(): void
    {
        foreach (SessionStatus::cases() as $case) {
            self::assertSame(
                ucfirst($case->value),
                $case->toOspp(),
                "SessionStatus::{$case->name}->toOspp() should return '" . ucfirst($case->value) . "'",
            );
        }
    }

    #[Test]
    public function fromOspp_toOspp_roundtrip_for_all_cases(): void
    {
        foreach (SessionStatus::cases() as $case) {
            self::assertSame(
                $case,
                SessionStatus::fromOspp($case->toOspp()),
                "SessionStatus::{$case->name} roundtrip failed",
            );
        }
    }

    #[Test]
    public function no_STARTING_case_exists(): void
    {
        self::assertNull(SessionStatus::tryFrom('starting'));
    }

    #[Test]
    public function only_two_session_sources_exist(): void
    {
        self::assertCount(2, SessionSource::cases());
        self::assertSame('MobileApp', SessionSource::MOBILE_APP->value);
        self::assertSame('WebPayment', SessionSource::WEB_PAYMENT->value);
    }
}
