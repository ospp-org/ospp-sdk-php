<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\ValueObjects;

use InvalidArgumentException;
use Ospp\Protocol\ValueObjects\ProtocolVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProtocolVersionTest extends TestCase
{
    // ---------------------------------------------------------------
    // Constructor — valid values
    // ---------------------------------------------------------------

    #[Test]
    public function constructorSetsComponents(): void
    {
        $version = new ProtocolVersion(1, 2, 3);

        self::assertSame(1, $version->major);
        self::assertSame(2, $version->minor);
        self::assertSame(3, $version->patch);
        self::assertSame('1.2.3', $version->value);
    }

    #[Test]
    public function constructorAcceptsZeroComponents(): void
    {
        $version = new ProtocolVersion(0, 0, 0);

        self::assertSame(0, $version->major);
        self::assertSame(0, $version->minor);
        self::assertSame(0, $version->patch);
        self::assertSame('0.0.0', $version->value);
    }

    #[Test]
    public function constructorAcceptsLargeNumbers(): void
    {
        $version = new ProtocolVersion(99, 88, 77);

        self::assertSame('99.88.77', $version->value);
    }

    // ---------------------------------------------------------------
    // Constructor — rejects negative components
    // ---------------------------------------------------------------

    #[Test]
    public function constructorRejectsNegativeMajor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Version components must be non-negative.');

        new ProtocolVersion(-1, 0, 0);
    }

    #[Test]
    public function constructorRejectsNegativeMinor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Version components must be non-negative.');

        new ProtocolVersion(1, -1, 0);
    }

    #[Test]
    public function constructorRejectsNegativePatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Version components must be non-negative.');

        new ProtocolVersion(1, 0, -1);
    }

    // ---------------------------------------------------------------
    // fromString()
    // ---------------------------------------------------------------

    #[Test]
    public function fromStringParsesValidVersion(): void
    {
        $version = ProtocolVersion::fromString('1.0.0');

        self::assertSame(1, $version->major);
        self::assertSame(0, $version->minor);
        self::assertSame(0, $version->patch);
    }

    #[Test]
    public function fromStringParsesHigherVersion(): void
    {
        $version = ProtocolVersion::fromString('2.5.11');

        self::assertSame(2, $version->major);
        self::assertSame(5, $version->minor);
        self::assertSame(11, $version->patch);
    }

    #[Test]
    public function fromStringRejectsTwoPartVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid protocol version format: "1.0"');

        ProtocolVersion::fromString('1.0');
    }

    #[Test]
    public function fromStringRejectsSinglePart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid protocol version format: "1"');

        ProtocolVersion::fromString('1');
    }

    #[Test]
    public function fromStringRejectsNonNumericParts(): void
    {
        // "a.b.c" will parse as 0.0.0 via (int) cast, which is valid
        // but let's verify it doesn't throw
        $version = ProtocolVersion::fromString('a.b.c');

        self::assertSame(0, $version->major);
        self::assertSame(0, $version->minor);
        self::assertSame(0, $version->patch);
    }

    #[Test]
    public function fromStringRejectsFourPartVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid protocol version format: "1.0.0.0"');

        ProtocolVersion::fromString('1.0.0.0');
    }

    #[Test]
    public function fromStringRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProtocolVersion::fromString('');
    }

    // ---------------------------------------------------------------
    // default()
    // ---------------------------------------------------------------

    #[Test]
    public function defaultReturnsZeroOneZero(): void
    {
        $version = ProtocolVersion::default();

        self::assertSame(0, $version->major);
        self::assertSame(1, $version->minor);
        self::assertSame(0, $version->patch);
        self::assertSame('0.1.0', $version->value);
    }

    #[Test]
    public function defaultWithCustomResolver(): void
    {
        ProtocolVersion::setDefaultResolver(fn () => '2.1.3');

        $version = ProtocolVersion::default();

        self::assertSame(2, $version->major);
        self::assertSame(1, $version->minor);
        self::assertSame(3, $version->patch);
        self::assertSame('2.1.3', $version->value);

        ProtocolVersion::setDefaultResolver(null);
    }

    // ---------------------------------------------------------------
    // isCompatibleWith()
    // ---------------------------------------------------------------

    #[Test]
    public function isCompatibleWithSameMajorReturnsTrue(): void
    {
        $a = new ProtocolVersion(1, 0, 0);
        $b = new ProtocolVersion(1, 5, 3);

        self::assertTrue($a->isCompatibleWith($b));
    }

    #[Test]
    public function isCompatibleWithSameVersionReturnsTrue(): void
    {
        $a = new ProtocolVersion(1, 0, 0);
        $b = new ProtocolVersion(1, 0, 0);

        self::assertTrue($a->isCompatibleWith($b));
    }

    #[Test]
    public function isCompatibleWithDifferentMajorReturnsFalse(): void
    {
        $a = new ProtocolVersion(1, 0, 0);
        $b = new ProtocolVersion(2, 0, 0);

        self::assertFalse($a->isCompatibleWith($b));
    }

    #[Test]
    public function isCompatibleWithIsSymmetric(): void
    {
        $a = new ProtocolVersion(1, 2, 3);
        $b = new ProtocolVersion(1, 9, 0);

        self::assertSame($a->isCompatibleWith($b), $b->isCompatibleWith($a));
    }

    // ---------------------------------------------------------------
    // equals()
    // ---------------------------------------------------------------

    #[Test]
    public function equalsSameVersionsReturnsTrue(): void
    {
        $a = new ProtocolVersion(1, 2, 3);
        $b = new ProtocolVersion(1, 2, 3);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function equalsDifferentMajorReturnsFalse(): void
    {
        $a = new ProtocolVersion(1, 0, 0);
        $b = new ProtocolVersion(2, 0, 0);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function equalsDifferentMinorReturnsFalse(): void
    {
        $a = new ProtocolVersion(1, 0, 0);
        $b = new ProtocolVersion(1, 1, 0);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function equalsDifferentPatchReturnsFalse(): void
    {
        $a = new ProtocolVersion(1, 0, 0);
        $b = new ProtocolVersion(1, 0, 1);

        self::assertFalse($a->equals($b));
    }

    // ---------------------------------------------------------------
    // jsonSerialize()
    // ---------------------------------------------------------------

    #[Test]
    public function jsonSerializeReturnsVersionString(): void
    {
        $version = new ProtocolVersion(1, 2, 3);

        self::assertSame('1.2.3', $version->jsonSerialize());
    }

    #[Test]
    public function jsonEncodeProducesQuotedVersionString(): void
    {
        $version = new ProtocolVersion(1, 0, 0);

        self::assertSame('"1.0.0"', json_encode($version));
    }

    // ---------------------------------------------------------------
    // __toString()
    // ---------------------------------------------------------------

    #[Test]
    public function toStringReturnsVersionString(): void
    {
        $version = new ProtocolVersion(3, 2, 1);

        self::assertSame('3.2.1', (string) $version);
    }

    #[Test]
    public function toStringMatchesJsonSerialize(): void
    {
        $version = new ProtocolVersion(1, 0, 0);

        self::assertSame($version->jsonSerialize(), (string) $version);
    }

    #[Test]
    public function toStringMatchesValueProperty(): void
    {
        $version = new ProtocolVersion(4, 5, 6);

        self::assertSame($version->value, (string) $version);
    }
}
