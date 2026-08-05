<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Crypto;

use Ospp\Protocol\Crypto\CanonicalJsonSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cross-language canonical-form parity (ospp-sdk-php side).
 *
 * The shared fixture canonical-json-vectors.json is BYTE-IDENTICAL with sdk-ts
 * (tests/crypto/fixtures/canonical-json-vectors.json). Each vector gives a JSON
 * value and the exact canonical string BOTH SDKs must produce — so a
 * canonicalization difference between the two languages turns one repo's suite
 * RED instead of turning up as a MAC failure on a live fleet.
 *
 * The spec names these two implementations as authoritative for
 * canonicalization, so a disagreement between them is not a bug in one of them;
 * it is a disagreement about what the protocol IS.
 *
 * The fixture is decoded with `json_decode($raw, false)` — objects as
 * `\stdClass`, NOT as associative arrays. That is deliberate and it is the
 * whole point: decoding to arrays would collapse `{}` and `[]` into the same
 * PHP value and the vector that exists to catch that would silently pass.
 */
final class CanonicalJsonParityTest extends TestCase
{
    /**
     * @return iterable<string, array{\stdClass, string, string}>
     */
    public static function vectors(): iterable
    {
        $raw = file_get_contents(__DIR__.'/fixtures/canonical-json-vectors.json');
        self::assertIsString($raw, 'shared canonical fixture must be readable');

        // false => objects decode to \stdClass, preserving {} vs [].
        $doc = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);

        foreach ($doc->vectors as $v) {
            yield $v->name => [$v->input, $v->canonical, $v->why];
        }
    }

    #[DataProvider('vectors')]
    public function testCanonicalFormMatchesTheSharedVector(
        \stdClass $input,
        string $expected,
        string $why,
    ): void {
        self::assertSame(
            $expected,
            (new CanonicalJsonSerializer())->serialize($input),
            $why,
        );
    }

    /**
     * The fixture must carry the whole set — a truncated copy would pass
     * vacuously.
     */
    #[Test]
    public function theFixtureCarriesTheFullVectorSet(): void
    {
        self::assertGreaterThanOrEqual(11, iterator_count(self::vectors()));
    }
}
