<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\PricingType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PricingTypeTest extends TestCase
{
    #[Test]
    public function it_has_exactly_two_cases(): void
    {
        self::assertCount(2, PricingType::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('PerMinute', PricingType::PER_MINUTE->value);
        self::assertSame('Fixed', PricingType::FIXED->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(PricingType::PER_MINUTE, PricingType::from('PerMinute'));
        self::assertSame(PricingType::FIXED, PricingType::from('Fixed'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(PricingType::tryFrom('PER_MINUTE'));
        self::assertNull(PricingType::tryFrom('perminute'));
        self::assertNull(PricingType::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        PricingType::from('invalid');
    }
}
