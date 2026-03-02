<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\BleServiceStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BleServiceStatusTest extends TestCase
{
    #[Test]
    public function it_has_exactly_five_cases(): void
    {
        self::assertCount(5, BleServiceStatus::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('Starting', BleServiceStatus::STARTING->value);
        self::assertSame('Running', BleServiceStatus::RUNNING->value);
        self::assertSame('Complete', BleServiceStatus::COMPLETE->value);
        self::assertSame('ReceiptReady', BleServiceStatus::RECEIPT_READY->value);
        self::assertSame('Error', BleServiceStatus::ERROR->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(BleServiceStatus::STARTING, BleServiceStatus::from('Starting'));
        self::assertSame(BleServiceStatus::RUNNING, BleServiceStatus::from('Running'));
        self::assertSame(BleServiceStatus::COMPLETE, BleServiceStatus::from('Complete'));
        self::assertSame(BleServiceStatus::RECEIPT_READY, BleServiceStatus::from('ReceiptReady'));
        self::assertSame(BleServiceStatus::ERROR, BleServiceStatus::from('Error'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(BleServiceStatus::tryFrom('STARTING'));
        self::assertNull(BleServiceStatus::tryFrom('starting'));
        self::assertNull(BleServiceStatus::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        BleServiceStatus::from('invalid');
    }
}
