<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\ValueObjects;

use Ospp\Protocol\ValueObjects\ProtocolVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Version negotiation is exact match — VERSIONING.md.
 *
 * Mirrored by sdk-ts tests/types/ExactMatchNegotiation.test.ts.
 */
final class ExactMatchNegotiationContractTest extends TestCase
{
    /**
     * VERSIONING.md: "**Negotiation is exact match.** At boot the station
     * declares one version in `BootNotification`'s envelope. The server holds a
     * **set** of versions it supports. If the declared version is a member of
     * that set, the server responds `Accepted`."
     */
    #[Test]
    public function membershipOfTheSupportedSetDecides(): void
    {
        $supported = [
            ProtocolVersion::fromString('0.3.0'),
            ProtocolVersion::fromString('0.4.0'),
        ];

        self::assertTrue(ProtocolVersion::fromString('0.3.0')->isSupportedBy($supported));
        self::assertTrue(ProtocolVersion::fromString('0.4.0')->isSupportedBy($supported));
        self::assertFalse(ProtocolVersion::fromString('0.5.0')->isSupportedBy($supported));
    }

    /**
     * VERSIONING.md: "There is no compatibility relation. `0.3.0` and `0.4.0`
     * are different versions and neither implies the other; a server that
     * supports both says so by listing both."
     *
     * The deleted rule classified these as compatible because MAJOR is 0 for
     * every version OSPP has shipped. The contradiction cost money: a `0.4.0`
     * station accepted by a `0.3.0` server delivers a full session and emits
     * `SessionEnded` with a `reason` the older schema rejects — and SessionEnded
     * is the sole billing source when no StopService was issued.
     */
    #[Test]
    public function aSharedMajorImpliesNothing(): void
    {
        $server = [ProtocolVersion::fromString('0.3.0')];

        self::assertFalse(ProtocolVersion::fromString('0.4.0')->isSupportedBy($server));
        self::assertFalse(ProtocolVersion::fromString('0.1.0')->isSupportedBy($server));
        self::assertFalse(ProtocolVersion::fromString('0.10.0')->isSupportedBy($server));
        self::assertFalse(ProtocolVersion::fromString('0.3.1')->isSupportedBy($server));
    }

    /**
     * The MAJOR-compatibility method is DELETED, not left returning something
     * narrower. A consumer still calling it would read a wrong answer as a
     * right one.
     */
    #[Test]
    public function theMajorCompatibilityMethodNoLongerExists(): void
    {
        self::assertFalse(
            method_exists(ProtocolVersion::class, 'isCompatibleWith'),
            'isCompatibleWith() encoded the deleted "same MAJOR is compatible" rule',
        );
    }

    /**
     * An empty supported set accepts nothing. A server that has configured no
     * versions supports no station — it does not silently accept every one.
     */
    #[Test]
    public function anEmptySupportedSetAcceptsNothing(): void
    {
        self::assertFalse(ProtocolVersion::fromString('0.3.0')->isSupportedBy([]));
    }

    /**
     * Equality is exact and unchanged; negotiation is defined in terms of it.
     */
    #[Test]
    public function equalityIsExact(): void
    {
        $a = ProtocolVersion::fromString('0.3.0');

        self::assertTrue($a->equals(ProtocolVersion::fromString('0.3.0')));
        self::assertFalse($a->equals(ProtocolVersion::fromString('0.3.1')));
    }
}
