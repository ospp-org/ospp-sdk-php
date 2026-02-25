<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\SessionSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionSourceTest extends TestCase
{
    #[Test]
    public function it_has_exactly_two_cases(): void
    {
        self::assertCount(2, SessionSource::cases());
    }

    #[Test]
    public function mobile_app_has_correct_value(): void
    {
        self::assertSame('mobile_app', SessionSource::MOBILE_APP->value);
    }

    #[Test]
    public function web_payment_has_correct_value(): void
    {
        self::assertSame('web_payment', SessionSource::WEB_PAYMENT->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(SessionSource::MOBILE_APP, SessionSource::from('mobile_app'));
        self::assertSame(SessionSource::WEB_PAYMENT, SessionSource::from('web_payment'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(SessionSource::tryFrom('MOBILE_APP'));
        self::assertNull(SessionSource::tryFrom('app'));
        self::assertNull(SessionSource::tryFrom('qr'));
        self::assertNull(SessionSource::tryFrom('ble'));
        self::assertNull(SessionSource::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        SessionSource::from('invalid');
    }
}
