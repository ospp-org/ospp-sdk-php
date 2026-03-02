<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\ChangeConfigResultStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChangeConfigResultStatusTest extends TestCase
{
    #[Test]
    public function it_has_exactly_four_cases(): void
    {
        self::assertCount(4, ChangeConfigResultStatus::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('Accepted', ChangeConfigResultStatus::ACCEPTED->value);
        self::assertSame('RebootRequired', ChangeConfigResultStatus::REBOOT_REQUIRED->value);
        self::assertSame('Rejected', ChangeConfigResultStatus::REJECTED->value);
        self::assertSame('NotSupported', ChangeConfigResultStatus::NOT_SUPPORTED->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(ChangeConfigResultStatus::ACCEPTED, ChangeConfigResultStatus::from('Accepted'));
        self::assertSame(ChangeConfigResultStatus::REBOOT_REQUIRED, ChangeConfigResultStatus::from('RebootRequired'));
        self::assertSame(ChangeConfigResultStatus::REJECTED, ChangeConfigResultStatus::from('Rejected'));
        self::assertSame(ChangeConfigResultStatus::NOT_SUPPORTED, ChangeConfigResultStatus::from('NotSupported'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(ChangeConfigResultStatus::tryFrom('ACCEPTED'));
        self::assertNull(ChangeConfigResultStatus::tryFrom('accepted'));
        self::assertNull(ChangeConfigResultStatus::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        ChangeConfigResultStatus::from('invalid');
    }
}
