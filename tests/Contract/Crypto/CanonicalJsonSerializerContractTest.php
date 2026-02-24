<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Contract\Crypto;

use OneStopPay\OsppProtocol\Crypto\CanonicalJsonSerializer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CanonicalJsonSerializerContractTest extends TestCase
{
    private CanonicalJsonSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new CanonicalJsonSerializer();
    }

    #[Test]
    public function unicode_characters_are_not_escaped(): void
    {
        // "cafe" with e-acute (U+00E9) as UTF-8 bytes
        $cafe = "caf\u{00e9}";
        $result = $this->serializer->serialize(['name' => $cafe]);

        self::assertStringContainsString($cafe, $result);
        self::assertStringNotContainsString('\u00e9', $result);
    }

    #[Test]
    public function slashes_are_not_escaped(): void
    {
        $result = $this->serializer->serialize(['url' => 'https://example.com/path']);

        self::assertStringContainsString('/', $result);
        self::assertStringNotContainsString('\\/', $result);
    }

    #[Test]
    public function integer_zero_is_not_quoted(): void
    {
        $result = $this->serializer->serialize(['count' => 0]);

        self::assertSame('{"count":0}', $result);
    }

    #[Test]
    public function boolean_values_are_lowercase(): void
    {
        $result = $this->serializer->serialize(['flag' => true, 'other' => false]);

        // Keys should be sorted: flag < other
        self::assertSame('{"flag":true,"other":false}', $result);
    }

    #[Test]
    public function null_values_are_literal_null(): void
    {
        $result = $this->serializer->serialize(['empty' => null]);

        self::assertSame('{"empty":null}', $result);
    }

    #[Test]
    public function deeply_nested_keys_sorted_at_every_level(): void
    {
        $data = [
            'z' => [
                'b' => [
                    'y' => 3,
                    'x' => 2,
                ],
                'a' => 1,
            ],
            'a' => 'top',
        ];

        $result = $this->serializer->serialize($data);

        self::assertSame('{"a":"top","z":{"a":1,"b":{"x":2,"y":3}}}', $result);
    }

    #[Test]
    public function array_of_objects_preserves_array_order_sorts_object_keys(): void
    {
        $data = [
            'items' => [
                ['z' => 1, 'a' => 2],
                ['y' => 3, 'b' => 4],
            ],
        ];

        $result = $this->serializer->serialize($data);

        self::assertSame('{"items":[{"a":2,"z":1},{"b":4,"y":3}]}', $result);
    }

    #[Test]
    public function empty_string_key_is_valid(): void
    {
        $data = ['' => 'value'];

        $result = $this->serializer->serialize($data);

        self::assertSame('{"":"value"}', $result);
    }

    #[Test]
    public function no_trailing_newline(): void
    {
        $result = $this->serializer->serialize(['key' => 'value']);

        self::assertMatchesRegularExpression('/[}\]]$/', $result);
        self::assertStringEndsNotWith("\n", $result);
    }

    #[Test]
    public function serialization_is_idempotent(): void
    {
        $data = ['z' => ['b' => 2, 'a' => 1], 'a' => 'first'];

        $first = $this->serializer->serialize($data);
        $decoded = json_decode($first, true);
        $second = $this->serializer->serialize($decoded);

        self::assertSame($first, $second);
    }

    #[Test]
    public function empty_array_produces_empty_brackets(): void
    {
        $result = $this->serializer->serialize([]);

        self::assertSame('[]', $result);
    }

    #[Test]
    public function float_values_preserved(): void
    {
        $result = $this->serializer->serialize(['pi' => 3.14]);

        self::assertSame('{"pi":3.14}', $result);
    }
}
