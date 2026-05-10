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
    // Constructor validation — spec compliance
    //
    // Spec defines messageId as {type: string, minLength: 1, maxLength: 64}
    // with no pattern. Prefixes (msg_/cmd_/err_/boot_/...) are a SHOULD-only
    // convention per spec/spec/03-messages.md and MUST NOT be relied on for
    // routing. The constructor accepts any non-empty string up to 64 chars.
    // ---------------------------------------------------------------

    #[Test]
    public function constructorAcceptsRawUuid(): void
    {
        $value = '550e8400-e29b-41d4-a716-446655440000';
        $id = new MessageId($value);

        self::assertSame($value, $id->value);
    }

    #[Test]
    public function constructorAcceptsArbitraryNonEmptyString(): void
    {
        $id = new MessageId('hello-world');

        self::assertSame('hello-world', $id->value);
    }

    #[Test]
    public function constructorAcceptsSpecPrefixes(): void
    {
        // Prefixes from spec/spec/03-messages.md table; constructor must
        // accept all of them (and any other non-empty ≤64-char string).
        foreach (['boot_x', 'hb_x', 'evt_x', 'sec_x', 'tx_x', 'auth_x', 'cmd_x', 'lwt-x'] as $value) {
            self::assertSame($value, (new MessageId($value))->value);
        }
    }

    #[Test]
    public function constructorAcceptsPrefixWithoutUnderscore(): void
    {
        // Previously rejected; spec has no such constraint.
        $id = new MessageId('msg550e8400');

        self::assertSame('msg550e8400', $id->value);
    }

    // ---------------------------------------------------------------
    // Constructor validation — length boundaries (spec maxLength: 64)
    // ---------------------------------------------------------------

    #[Test]
    public function constructorAcceptsExactly64Chars(): void
    {
        $value = str_repeat('a', 64);
        $id = new MessageId($value);

        self::assertSame($value, $id->value);
    }

    #[Test]
    public function constructorAcceptsSingleChar(): void
    {
        $id = new MessageId('x');

        self::assertSame('x', $id->value);
    }

    #[Test]
    public function constructorRejectsExactly65Chars(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MessageId must be at most 64 characters');

        new MessageId(str_repeat('a', 65));
    }

    #[Test]
    public function constructorRejectsMultibyteOverflow(): void
    {
        // 33 emoji × 1 codepoint each = 33 chars (mb_strlen) but ≥132 bytes.
        // mb_strlen path enforces 64 *characters*, not bytes.
        // Use a value that is mb_strlen > 64 but still printable.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MessageId must be at most 64 characters');

        new MessageId(str_repeat('é', 65));
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
