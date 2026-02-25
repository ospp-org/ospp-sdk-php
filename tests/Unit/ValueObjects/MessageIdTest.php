<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\ValueObjects;

use InvalidArgumentException;
use Ospp\Protocol\ValueObjects\MessageId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MessageIdTest extends TestCase
{
    // ---------------------------------------------------------------
    // generate() — prefix variants
    // ---------------------------------------------------------------

    #[Test]
    public function generateWithMsgPrefix(): void
    {
        $id = MessageId::generate('msg_');

        self::assertStringStartsWith('msg_', $id->value);
    }

    #[Test]
    public function generateWithCmdPrefix(): void
    {
        $id = MessageId::generate('cmd_');

        self::assertStringStartsWith('cmd_', $id->value);
    }

    #[Test]
    public function generateWithErrPrefix(): void
    {
        $id = MessageId::generate('err_');

        self::assertStringStartsWith('err_', $id->value);
    }

    #[Test]
    public function generateDefaultPrefixIsMsg(): void
    {
        $id = MessageId::generate();

        self::assertStringStartsWith('msg_', $id->value);
    }

    // ---------------------------------------------------------------
    // generate() — UUID v4 format
    // ---------------------------------------------------------------

    #[Test]
    public function generateProducesUuidV4Format(): void
    {
        $id = MessageId::generate('msg_');
        $uuidPart = substr($id->value, 4); // strip "msg_"

        // UUID v4 pattern: 8-4-4-4-12 hex chars, version nibble = 4, variant = [89ab]
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

        self::assertMatchesRegularExpression($pattern, $uuidPart);
    }

    #[Test]
    public function generateProducesUniqueValues(): void
    {
        $a = MessageId::generate();
        $b = MessageId::generate();

        self::assertNotSame($a->value, $b->value);
    }

    // ---------------------------------------------------------------
    // fromString()
    // ---------------------------------------------------------------

    #[Test]
    public function fromStringWithValidMsgValue(): void
    {
        $value = 'msg_550e8400-e29b-41d4-a716-446655440000';
        $id = MessageId::fromString($value);

        self::assertSame($value, $id->value);
    }

    #[Test]
    public function fromStringWithValidCmdValue(): void
    {
        $value = 'cmd_550e8400-e29b-41d4-a716-446655440000';
        $id = MessageId::fromString($value);

        self::assertSame($value, $id->value);
    }

    #[Test]
    public function fromStringWithValidErrValue(): void
    {
        $value = 'err_550e8400-e29b-41d4-a716-446655440000';
        $id = MessageId::fromString($value);

        self::assertSame($value, $id->value);
    }

    // ---------------------------------------------------------------
    // Constructor validation — empty string
    // ---------------------------------------------------------------

    #[Test]
    public function constructorRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MessageId cannot be empty.');

        new MessageId('');
    }

    // ---------------------------------------------------------------
    // Constructor validation — invalid prefix
    // ---------------------------------------------------------------

    #[Test]
    public function constructorRejectsInvalidPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MessageId must start with one of [msg_, cmd_, err_]');

        new MessageId('xyz_550e8400-e29b-41d4-a716-446655440000');
    }

    #[Test]
    public function constructorRejectsPrefixWithoutUnderscore(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MessageId('msg550e8400');
    }

    #[Test]
    public function constructorRejectsRandomString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MessageId('hello-world');
    }

    // ---------------------------------------------------------------
    // equals()
    // ---------------------------------------------------------------

    #[Test]
    public function equalsSameValueReturnsTrue(): void
    {
        $value = 'msg_550e8400-e29b-41d4-a716-446655440000';
        $a = new MessageId($value);
        $b = new MessageId($value);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function equalsDifferentValueReturnsFalse(): void
    {
        $a = MessageId::generate('msg_');
        $b = MessageId::generate('msg_');

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function equalsDifferentPrefixReturnsFalse(): void
    {
        // Same UUID suffix but different prefix
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $a = new MessageId('msg_' . $uuid);
        $b = new MessageId('cmd_' . $uuid);

        self::assertFalse($a->equals($b));
    }

    // ---------------------------------------------------------------
    // jsonSerialize()
    // ---------------------------------------------------------------

    #[Test]
    public function jsonSerializeReturnsValue(): void
    {
        $value = 'msg_550e8400-e29b-41d4-a716-446655440000';
        $id = new MessageId($value);

        self::assertSame($value, $id->jsonSerialize());
    }

    #[Test]
    public function jsonEncodeProducesQuotedValue(): void
    {
        $value = 'cmd_550e8400-e29b-41d4-a716-446655440000';
        $id = new MessageId($value);

        self::assertSame('"' . $value . '"', json_encode($id));
    }

    // ---------------------------------------------------------------
    // __toString()
    // ---------------------------------------------------------------

    #[Test]
    public function toStringReturnsValue(): void
    {
        $value = 'err_550e8400-e29b-41d4-a716-446655440000';
        $id = new MessageId($value);

        self::assertSame($value, (string) $id);
    }

    #[Test]
    public function toStringMatchesJsonSerialize(): void
    {
        $id = MessageId::generate('msg_');

        self::assertSame($id->jsonSerialize(), (string) $id);
    }
}
