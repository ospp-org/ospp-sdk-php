<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\LogLevel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LogLevelTest extends TestCase
{
    #[Test]
    public function it_has_exactly_four_cases(): void
    {
        self::assertCount(4, LogLevel::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('Debug', LogLevel::DEBUG->value);
        self::assertSame('Info', LogLevel::INFO->value);
        self::assertSame('Warn', LogLevel::WARN->value);
        self::assertSame('Error', LogLevel::ERROR->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(LogLevel::DEBUG, LogLevel::from('Debug'));
        self::assertSame(LogLevel::INFO, LogLevel::from('Info'));
        self::assertSame(LogLevel::WARN, LogLevel::from('Warn'));
        self::assertSame(LogLevel::ERROR, LogLevel::from('Error'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(LogLevel::tryFrom('DEBUG'));
        self::assertNull(LogLevel::tryFrom('debug'));
        self::assertNull(LogLevel::tryFrom('Warning'));
        self::assertNull(LogLevel::tryFrom('Critical'));
        self::assertNull(LogLevel::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        LogLevel::from('invalid');
    }
}
