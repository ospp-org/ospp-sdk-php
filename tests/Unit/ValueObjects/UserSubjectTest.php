<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\ValueObjects;

use Ospp\Protocol\ValueObjects\UserSubject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * UserSubject derivation rule — single source of truth across the OSPP
 * ecosystem (csms-server PassIssuer at issuance, RevalidationGate at
 * reconcile check #5, any firmware/sim that derives independently from
 * a UUID).
 *
 * The rule is implicitly normative via the spec's
 * `^sub_[a-zA-Z0-9]+$` regex (schemas/common/offline-pass.schema.json
 * `sub` field, common.ts UserId pattern doc): a UUID-shaped string
 * CANNOT contain hyphens to satisfy the regex, so deriving the `sub`
 * from a user UUID requires stripping the hyphens. Lifted from
 * csms-server `App\Shared\ValueObjects\UserSub` v0.5.2 → SDK v0.5.3
 * to make the rule the cross-ecosystem source of truth instead of a
 * csms-server-private rule.
 *
 * Test vectors are byte-identical with the TS SDK counterpart
 * (`tests/identity/UserSubject.test.ts`) to prove cross-language
 * isomorphism — the whole point of the lift.
 */
final class UserSubjectTest extends TestCase
{
    // ---------------------------------------------------------------
    // fromUserId — canonical vectors (mirror csms-server UserSubTest)
    // ---------------------------------------------------------------

    #[Test]
    public function fromUserIdStripsHyphensAndPrefixesSubUnderscore(): void
    {
        self::assertSame('sub_user1', UserSubject::fromUserId('user-1'));
    }

    #[Test]
    public function fromUserIdOnFullUuidStripsAllFourHyphens(): void
    {
        self::assertSame(
            'sub_123e4567e89b12d3a456426614174000',
            UserSubject::fromUserId('123e4567-e89b-12d3-a456-426614174000'),
        );
    }

    #[Test]
    public function fromUserIdOnHyphenFreeInputLeavesBodyUnchanged(): void
    {
        self::assertSame('sub_plain', UserSubject::fromUserId('plain'));
    }

    #[Test]
    public function fromUserIdIsDeterministic(): void
    {
        $a = UserSubject::fromUserId('abc-def-ghi');
        $b = UserSubject::fromUserId('abc-def-ghi');

        self::assertSame($a, $b);
    }

    // ---------------------------------------------------------------
    // Cross-language byte-equality vectors
    //
    // These exact vectors MUST also pass byte-identical in sdk-ts
    // (`tests/identity/UserSubject.test.ts`). A divergence between the
    // two SDKs would reintroduce the very drift this lift was meant
    // to eliminate.
    // ---------------------------------------------------------------

    #[Test]
    public function fromUserIdEmptyInputReturnsBarePrefix(): void
    {
        self::assertSame('sub_', UserSubject::fromUserId(''));
    }

    #[Test]
    public function fromUserIdSingleHyphenInputReturnsBarePrefix(): void
    {
        self::assertSame('sub_', UserSubject::fromUserId('-'));
    }

    #[Test]
    public function fromUserIdMultipleConsecutiveHyphensAreAllStripped(): void
    {
        self::assertSame('sub_ab', UserSubject::fromUserId('a---b'));
    }

    #[Test]
    public function fromUserIdHandlesUtf8SafelyOnNonHyphenBytes(): void
    {
        // PHP str_replace operates on bytes; JS replaceAll on UTF-16 code units.
        // For ASCII '-' (0x2D), the two are byte-equivalent because UTF-8
        // continuation bytes (0x80-0xBF) never contain 0x2D, so multibyte
        // sequences are never split by the strip operation. This test pins
        // that invariant so a future locale-aware refactor doesn't break it.
        self::assertSame('sub_userémoji🎉', UserSubject::fromUserId('user-é-moji🎉'));
    }
}
