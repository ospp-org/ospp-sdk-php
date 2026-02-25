<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\MessageType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MessageTypeTest extends TestCase
{
    #[Test]
    public function it_has_exactly_three_cases(): void
    {
        self::assertCount(3, MessageType::cases());
    }

    #[Test]
    public function request_has_correct_value(): void
    {
        self::assertSame('REQUEST', MessageType::REQUEST->value);
    }

    #[Test]
    public function response_has_correct_value(): void
    {
        self::assertSame('RESPONSE', MessageType::RESPONSE->value);
    }

    #[Test]
    public function event_has_correct_value(): void
    {
        self::assertSame('EVENT', MessageType::EVENT->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(MessageType::REQUEST, MessageType::from('REQUEST'));
        self::assertSame(MessageType::RESPONSE, MessageType::from('RESPONSE'));
        self::assertSame(MessageType::EVENT, MessageType::from('EVENT'));
    }

    #[Test]
    public function it_returns_null_for_invalid_string_with_try_from(): void
    {
        self::assertNull(MessageType::tryFrom('INVALID'));
        self::assertNull(MessageType::tryFrom('request'));
        self::assertNull(MessageType::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        MessageType::from('INVALID');
    }

    #[Test]
    public function all_cases_are_backed_by_uppercase_strings(): void
    {
        foreach (MessageType::cases() as $case) {
            self::assertSame($case->name, $case->value);
        }
    }
}
