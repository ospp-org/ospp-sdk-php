<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SeverityTest extends TestCase
{
    #[Test]
    public function it_has_exactly_four_cases(): void
    {
        self::assertCount(4, Severity::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('CRITICAL', Severity::CRITICAL->value);
        self::assertSame('ERROR', Severity::ERROR->value);
        self::assertSame('WARNING', Severity::WARNING->value);
        self::assertSame('INFO', Severity::INFO->value);
    }

    // --- isActionRequired ---

    #[Test]
    public function is_action_required_returns_true_for_critical_and_error(): void
    {
        self::assertTrue(Severity::CRITICAL->isActionRequired());
        self::assertTrue(Severity::ERROR->isActionRequired());
    }

    #[Test]
    public function is_action_required_returns_false_for_warning_and_info(): void
    {
        self::assertFalse(Severity::WARNING->isActionRequired());
        self::assertFalse(Severity::INFO->isActionRequired());
    }

    // --- from / tryFrom ---

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(Severity::CRITICAL, Severity::from('CRITICAL'));
        self::assertSame(Severity::ERROR, Severity::from('ERROR'));
        self::assertSame(Severity::WARNING, Severity::from('WARNING'));
        self::assertSame(Severity::INFO, Severity::from('INFO'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(Severity::tryFrom('critical'));
        self::assertNull(Severity::tryFrom(''));
        self::assertNull(Severity::tryFrom('DEBUG'));
        self::assertNull(Severity::tryFrom('NOTICE'));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        Severity::from('invalid');
    }

    // --- fromOspp / toOspp (wire format conversion) ---

    #[Test]
    public function from_ospp_converts_pascal_case_wire_values(): void
    {
        self::assertSame(Severity::CRITICAL, Severity::fromOspp('Critical'));
        self::assertSame(Severity::ERROR, Severity::fromOspp('Error'));
        self::assertSame(Severity::WARNING, Severity::fromOspp('Warning'));
        self::assertSame(Severity::INFO, Severity::fromOspp('Info'));
    }

    #[Test]
    public function to_ospp_returns_pascal_case_wire_values(): void
    {
        self::assertSame('Critical', Severity::CRITICAL->toOspp());
        self::assertSame('Error', Severity::ERROR->toOspp());
        self::assertSame('Warning', Severity::WARNING->toOspp());
        self::assertSame('Info', Severity::INFO->toOspp());
    }

    #[Test]
    public function from_ospp_roundtrips_with_to_ospp(): void
    {
        foreach (Severity::cases() as $severity) {
            self::assertSame($severity, Severity::fromOspp($severity->toOspp()));
        }
    }
}
