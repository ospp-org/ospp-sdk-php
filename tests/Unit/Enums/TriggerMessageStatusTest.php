<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\TriggerMessageStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TriggerMessageStatusTest extends TestCase
{
    #[Test]
    public function it_has_exactly_three_cases(): void
    {
        self::assertCount(3, TriggerMessageStatus::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('Accepted', TriggerMessageStatus::ACCEPTED->value);
        self::assertSame('Rejected', TriggerMessageStatus::REJECTED->value);
        self::assertSame('NotImplemented', TriggerMessageStatus::NOT_IMPLEMENTED->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(TriggerMessageStatus::ACCEPTED, TriggerMessageStatus::from('Accepted'));
        self::assertSame(TriggerMessageStatus::REJECTED, TriggerMessageStatus::from('Rejected'));
        self::assertSame(TriggerMessageStatus::NOT_IMPLEMENTED, TriggerMessageStatus::from('NotImplemented'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(TriggerMessageStatus::tryFrom('ACCEPTED'));
        self::assertNull(TriggerMessageStatus::tryFrom('notimplemented'));
        self::assertNull(TriggerMessageStatus::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        TriggerMessageStatus::from('invalid');
    }
}
