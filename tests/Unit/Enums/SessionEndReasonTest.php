<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\SessionEndReason;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionEndReasonTest extends TestCase
{
    #[Test]
    public function it_has_exactly_seven_cases(): void
    {
        // 7 since spec 0.31.0 added Inactivity, the SessionTimeout idle stop.
        self::assertCount(7, SessionEndReason::cases());
    }

    #[Test]
    public function all_cases_have_correct_wire_values(): void
    {
        self::assertSame('TimerExpired', SessionEndReason::TIMER_EXPIRED->value);
        self::assertSame('Fault', SessionEndReason::FAULT->value);
        self::assertSame('Local', SessionEndReason::LOCAL->value);
        self::assertSame('LocalOutOfCredit', SessionEndReason::LOCAL_OUT_OF_CREDIT->value);
        self::assertSame('Deauthorized', SessionEndReason::DEAUTHORIZED->value);
        self::assertSame('OperatorStopped', SessionEndReason::OPERATOR_STOPPED->value);
        self::assertSame('Inactivity', SessionEndReason::INACTIVITY->value);
    }

    #[Test]
    public function from_resolves_each_wire_value(): void
    {
        self::assertSame(SessionEndReason::TIMER_EXPIRED, SessionEndReason::from('TimerExpired'));
        self::assertSame(SessionEndReason::FAULT, SessionEndReason::from('Fault'));
        self::assertSame(SessionEndReason::LOCAL, SessionEndReason::from('Local'));
        self::assertSame(SessionEndReason::LOCAL_OUT_OF_CREDIT, SessionEndReason::from('LocalOutOfCredit'));
        self::assertSame(SessionEndReason::DEAUTHORIZED, SessionEndReason::from('Deauthorized'));
        self::assertSame(SessionEndReason::OPERATOR_STOPPED, SessionEndReason::from('OperatorStopped'));
        self::assertSame(SessionEndReason::INACTIVITY, SessionEndReason::from('Inactivity'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(SessionEndReason::tryFrom(''));
        self::assertNull(SessionEndReason::tryFrom('timerExpired'));
        self::assertNull(SessionEndReason::tryFrom('Remote'));
        self::assertNull(SessionEndReason::tryFrom('TIMER_EXPIRED'));
    }
}
