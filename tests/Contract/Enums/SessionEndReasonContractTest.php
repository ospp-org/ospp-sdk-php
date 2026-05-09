<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Enums;

use Ospp\Protocol\Enums\SessionEndReason;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for SessionEndReason enum.
 *
 * Pins the public API contract — cardinality, wire format, and ordering —
 * so any refactor or schema drift is caught immediately.
 */
final class SessionEndReasonContractTest extends TestCase
{
    #[Test]
    public function cardinality_is_exactly_5(): void
    {
        self::assertCount(5, SessionEndReason::cases());
    }

    #[Test]
    public function wire_format_values_match_PascalCase_pattern(): void
    {
        foreach (SessionEndReason::cases() as $case) {
            self::assertMatchesRegularExpression(
                '/^[A-Z][a-zA-Z]+$/',
                $case->value,
                "SessionEndReason::{$case->name}->value = '{$case->value}' does not match /^[A-Z][a-zA-Z]+$/",
            );
        }
    }

    #[Test]
    public function legacy_v0_3_values_remain_first_two_cases(): void
    {
        $cases = SessionEndReason::cases();

        self::assertSame(SessionEndReason::TIMER_EXPIRED, $cases[0]);
        self::assertSame(SessionEndReason::FAULT, $cases[1]);
    }

    #[Test]
    public function v0_4_added_values_are_present(): void
    {
        $values = array_map(fn (SessionEndReason $r) => $r->value, SessionEndReason::cases());

        self::assertContains('Local', $values);
        self::assertContains('LocalOutOfCredit', $values);
        self::assertContains('Deauthorized', $values);
    }

    #[Test]
    public function deferred_v0_4_values_are_NOT_present(): void
    {
        // Per spec v0.4.0 CHANGELOG "Excluded from v0.4.0 (deferred)":
        // 'Remote' and 'EnergyLimitReached' are deliberately not in the enum.
        self::assertNull(SessionEndReason::tryFrom('Remote'));
        self::assertNull(SessionEndReason::tryFrom('EnergyLimitReached'));
    }
}
