<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\ResetType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResetTypeTest extends TestCase
{
    #[Test]
    public function it_has_exactly_two_cases(): void
    {
        self::assertCount(2, ResetType::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('Soft', ResetType::SOFT->value);
        self::assertSame('Hard', ResetType::HARD->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(ResetType::SOFT, ResetType::from('Soft'));
        self::assertSame(ResetType::HARD, ResetType::from('Hard'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(ResetType::tryFrom('SOFT'));
        self::assertNull(ResetType::tryFrom('soft'));
        self::assertNull(ResetType::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        ResetType::from('invalid');
    }
}
