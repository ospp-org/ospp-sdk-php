<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\TransactionEventStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionEventStatusTest extends TestCase
{
    #[Test]
    public function it_has_exactly_four_cases(): void
    {
        self::assertCount(4, TransactionEventStatus::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('Accepted', TransactionEventStatus::ACCEPTED->value);
        self::assertSame('Duplicate', TransactionEventStatus::DUPLICATE->value);
        self::assertSame('Rejected', TransactionEventStatus::REJECTED->value);
        self::assertSame('RetryLater', TransactionEventStatus::RETRY_LATER->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(TransactionEventStatus::ACCEPTED, TransactionEventStatus::from('Accepted'));
        self::assertSame(TransactionEventStatus::DUPLICATE, TransactionEventStatus::from('Duplicate'));
        self::assertSame(TransactionEventStatus::REJECTED, TransactionEventStatus::from('Rejected'));
        self::assertSame(TransactionEventStatus::RETRY_LATER, TransactionEventStatus::from('RetryLater'));
    }

    #[Test]
    public function deferred_is_retired_and_no_longer_a_case(): void
    {
        // INVERTED from `deferred_is_distinct_from_retry_later`, which pinned the
        // v0.5.0 semantics: Deferred = held server-side, no auto-resend, awaiting
        // operator-manual unblock. Spec 0.9.0 retired the value — the gap-blocking
        // rule it existed to express is gone, and the unblock it waited for was
        // never implemented anywhere. RetryLater is now the ONLY non-terminal
        // status.
        //
        // Kept as an inversion rather than deleted because the danger is real: a
        // server that still emits 'Deferred' is emitting a value no station can
        // act on, and the wire schema no longer admits it. This fails loudly if
        // the case is ever reintroduced.
        self::assertNull(TransactionEventStatus::tryFrom('Deferred'));

        $values = array_map(
            static fn (TransactionEventStatus $c): string => $c->value,
            TransactionEventStatus::cases(),
        );
        self::assertNotContains('Deferred', $values);
        self::assertSame(['Accepted', 'Duplicate', 'Rejected', 'RetryLater'], $values);
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(TransactionEventStatus::tryFrom('ACCEPTED'));
        self::assertNull(TransactionEventStatus::tryFrom('retrylater'));
        self::assertNull(TransactionEventStatus::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        TransactionEventStatus::from('invalid');
    }
}
