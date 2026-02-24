<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Unit\Crypto;

use OneStopPay\OsppProtocol\Crypto\CanonicalJsonSerializer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CanonicalJsonSerializerTest extends TestCase
{
    private CanonicalJsonSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new CanonicalJsonSerializer();
    }

    #[Test]
    public function serializeSortsObjectKeysRecursively(): void
    {
        $data = [
            'zebra' => 'z',
            'alpha' => 'a',
            'middle' => [
                'zulu' => 1,
                'bravo' => 2,
            ],
        ];

        $result = $this->serializer->serialize($data);

        self::assertSame('{"alpha":"a","middle":{"bravo":2,"zulu":1},"zebra":"z"}', $result);
    }

    #[Test]
    public function serializePreservesArrayOrder(): void
    {
        $data = [
            'items' => ['charlie', 'alpha', 'bravo'],
        ];

        $result = $this->serializer->serialize($data);

        // Array order must be preserved, NOT sorted
        self::assertSame('{"items":["charlie","alpha","bravo"]}', $result);
    }

    #[Test]
    public function serializeUsesCompactJson(): void
    {
        $data = ['key' => 'value', 'number' => 42];

        $result = $this->serializer->serialize($data);

        // No spaces, no newlines
        self::assertStringNotContainsString(' ', $result);
        self::assertStringNotContainsString("\n", $result);
        self::assertSame('{"key":"value","number":42}', $result);
    }

    #[Test]
    public function serializeWithNestedObjects(): void
    {
        $data = [
            'z_outer' => [
                'z_inner' => [
                    'z_deep' => true,
                    'a_deep' => false,
                ],
                'a_inner' => 'first',
            ],
            'a_outer' => 'top',
        ];

        $result = $this->serializer->serialize($data);

        self::assertSame(
            '{"a_outer":"top","z_outer":{"a_inner":"first","z_inner":{"a_deep":false,"z_deep":true}}}',
            $result,
        );
    }

    #[Test]
    public function serializeWithEmptyArray(): void
    {
        $data = [
            'items' => [],
            'name' => 'test',
        ];

        $result = $this->serializer->serialize($data);

        self::assertSame('{"items":[],"name":"test"}', $result);
    }

    #[Test]
    public function serializeWithMixedNestedArraysAndObjects(): void
    {
        $data = [
            'z_key' => 'last',
            'a_key' => 'first',
            'list' => [
                ['z_field' => 3, 'a_field' => 1],
                ['m_field' => 2, 'b_field' => 0],
            ],
        ];

        $result = $this->serializer->serialize($data);

        // Top-level keys sorted: a_key, list, z_key
        // Array order preserved: first element then second
        // Object keys within each array element sorted
        self::assertSame(
            '{"a_key":"first","list":[{"a_field":1,"z_field":3},{"b_field":0,"m_field":2}],"z_key":"last"}',
            $result,
        );
    }
}
