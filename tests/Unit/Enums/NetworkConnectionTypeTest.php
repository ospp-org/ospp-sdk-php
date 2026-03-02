<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\NetworkConnectionType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NetworkConnectionTypeTest extends TestCase
{
    #[Test]
    public function it_has_exactly_three_cases(): void
    {
        self::assertCount(3, NetworkConnectionType::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('Ethernet', NetworkConnectionType::ETHERNET->value);
        self::assertSame('Wifi', NetworkConnectionType::WIFI->value);
        self::assertSame('Cellular', NetworkConnectionType::CELLULAR->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(NetworkConnectionType::ETHERNET, NetworkConnectionType::from('Ethernet'));
        self::assertSame(NetworkConnectionType::WIFI, NetworkConnectionType::from('Wifi'));
        self::assertSame(NetworkConnectionType::CELLULAR, NetworkConnectionType::from('Cellular'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(NetworkConnectionType::tryFrom('ETHERNET'));
        self::assertNull(NetworkConnectionType::tryFrom('wifi'));
        self::assertNull(NetworkConnectionType::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        NetworkConnectionType::from('invalid');
    }
}
