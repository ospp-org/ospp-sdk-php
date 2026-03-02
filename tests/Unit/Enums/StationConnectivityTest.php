<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\StationConnectivity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StationConnectivityTest extends TestCase
{
    #[Test]
    public function it_has_exactly_two_cases(): void
    {
        self::assertCount(2, StationConnectivity::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('Online', StationConnectivity::ONLINE->value);
        self::assertSame('Offline', StationConnectivity::OFFLINE->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(StationConnectivity::ONLINE, StationConnectivity::from('Online'));
        self::assertSame(StationConnectivity::OFFLINE, StationConnectivity::from('Offline'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(StationConnectivity::tryFrom('ONLINE'));
        self::assertNull(StationConnectivity::tryFrom('online'));
        self::assertNull(StationConnectivity::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        StationConnectivity::from('invalid');
    }
}
