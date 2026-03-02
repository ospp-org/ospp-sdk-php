<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\BootNotificationStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BootNotificationStatusTest extends TestCase
{
    #[Test]
    public function it_has_exactly_three_cases(): void
    {
        self::assertCount(3, BootNotificationStatus::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('Accepted', BootNotificationStatus::ACCEPTED->value);
        self::assertSame('Rejected', BootNotificationStatus::REJECTED->value);
        self::assertSame('Pending', BootNotificationStatus::PENDING->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(BootNotificationStatus::ACCEPTED, BootNotificationStatus::from('Accepted'));
        self::assertSame(BootNotificationStatus::REJECTED, BootNotificationStatus::from('Rejected'));
        self::assertSame(BootNotificationStatus::PENDING, BootNotificationStatus::from('Pending'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(BootNotificationStatus::tryFrom('ACCEPTED'));
        self::assertNull(BootNotificationStatus::tryFrom('accepted'));
        self::assertNull(BootNotificationStatus::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        BootNotificationStatus::from('invalid');
    }
}
