<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\DataTransferStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DataTransferStatusTest extends TestCase
{
    #[Test]
    public function it_has_exactly_four_cases(): void
    {
        self::assertCount(4, DataTransferStatus::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('Accepted', DataTransferStatus::ACCEPTED->value);
        self::assertSame('Rejected', DataTransferStatus::REJECTED->value);
        self::assertSame('UnknownVendor', DataTransferStatus::UNKNOWN_VENDOR->value);
        self::assertSame('UnknownData', DataTransferStatus::UNKNOWN_DATA->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(DataTransferStatus::ACCEPTED, DataTransferStatus::from('Accepted'));
        self::assertSame(DataTransferStatus::REJECTED, DataTransferStatus::from('Rejected'));
        self::assertSame(DataTransferStatus::UNKNOWN_VENDOR, DataTransferStatus::from('UnknownVendor'));
        self::assertSame(DataTransferStatus::UNKNOWN_DATA, DataTransferStatus::from('UnknownData'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(DataTransferStatus::tryFrom('ACCEPTED'));
        self::assertNull(DataTransferStatus::tryFrom('unknownvendor'));
        self::assertNull(DataTransferStatus::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        DataTransferStatus::from('invalid');
    }
}
