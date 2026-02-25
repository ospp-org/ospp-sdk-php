<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Enums;

use Ospp\Protocol\Enums\BayStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for BayStatus enum.
 *
 * These tests pin the public API contract — counts, wire format,
 * roundtrip safety, and behavioral equivalences — so that any
 * refactor that breaks the contract is caught immediately.
 */
final class BayStatusContractTest extends TestCase
{
    #[Test]
    public function canStartSession_count_is_exactly_2(): void
    {
        $startable = array_filter(BayStatus::cases(), fn (BayStatus $s) => $s->canStartSession());
        self::assertCount(2, $startable);

        $startableSet = array_values($startable);
        self::assertContains(BayStatus::AVAILABLE, $startableSet);
        self::assertContains(BayStatus::RESERVED, $startableSet);
    }

    #[Test]
    public function canReserve_count_is_exactly_1(): void
    {
        $reservable = array_filter(BayStatus::cases(), fn (BayStatus $s) => $s->canReserve());
        self::assertCount(1, $reservable);

        $reservableSet = array_values($reservable);
        self::assertSame(BayStatus::AVAILABLE, $reservableSet[0]);
    }

    #[Test]
    public function fromOspp_converts_PascalCase_for_all_7_cases(): void
    {
        foreach (BayStatus::cases() as $case) {
            self::assertSame(
                $case,
                BayStatus::fromOspp(ucfirst($case->value)),
                "fromOspp(ucfirst('{$case->value}')) should return BayStatus::{$case->name}",
            );
        }
    }

    #[Test]
    public function toOspp_returns_PascalCase_for_all_7_cases(): void
    {
        foreach (BayStatus::cases() as $case) {
            self::assertSame(
                ucfirst($case->value),
                $case->toOspp(),
                "BayStatus::{$case->name}->toOspp() should return '" . ucfirst($case->value) . "'",
            );
        }
    }

    #[Test]
    public function fromOspp_toOspp_roundtrip_for_all_cases(): void
    {
        foreach (BayStatus::cases() as $case) {
            self::assertSame(
                $case,
                BayStatus::fromOspp($case->toOspp()),
                "BayStatus::{$case->name} roundtrip failed",
            );
        }
    }

    #[Test]
    public function wire_format_values_match_PascalCase_pattern(): void
    {
        foreach (BayStatus::cases() as $case) {
            self::assertMatchesRegularExpression(
                '/^[A-Z][a-z]+$/',
                $case->toOspp(),
                "BayStatus::{$case->name}->toOspp() = '{$case->toOspp()}' does not match /^[A-Z][a-z]+$/",
            );
        }
    }

    #[Test]
    public function fromOspp_rejects_whitespace_padded_input(): void
    {
        $this->expectException(\ValueError::class);
        BayStatus::fromOspp(' Available ');
    }

    #[Test]
    public function fromOspp_handles_ALL_UPPERCASE(): void
    {
        // strtolower('AVAILABLE') = 'available' which is a valid backing value
        self::assertSame(BayStatus::AVAILABLE, BayStatus::fromOspp('AVAILABLE'));
    }

    #[Test]
    public function acceptsSessions_equals_canStartSession_for_all_cases(): void
    {
        foreach (BayStatus::cases() as $case) {
            self::assertSame(
                $case->canStartSession(),
                $case->acceptsSessions(),
                "BayStatus::{$case->name}: acceptsSessions() !== canStartSession()",
            );
        }
    }
}
