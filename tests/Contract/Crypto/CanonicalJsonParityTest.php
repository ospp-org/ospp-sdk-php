<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Crypto;

use Ospp\Protocol\Crypto\CanonicalJsonSerializer;
use Ospp\Protocol\Crypto\MacSigner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Canonical-form conformance (ospp-sdk-php side).
 *
 * `fixtures/canonical-form.json` is VENDORED BYTE-IDENTICALLY from the spec:
 * `conformance/test-vectors/crypto/canonical-form.json` at the ref in
 * `.spec-ref`, enforced by `scripts/check-crypto-vectors.sh` in CI. It is the
 * same file sdk-ts vendors, so the two SDKs no longer agree with each other by
 * assertion in a comment — they agree because both are compared to one upstream.
 *
 * The `canonical` strings are the ORACLE. The spec recomputed them from the
 * §4.8.1 rule text in a third implementation rather than adopting either SDK's
 * output, which is the only reason they are evidence: a vector generated from
 * an implementation can only ever confirm that implementation.
 *
 * The fixture is decoded with `json_decode($raw, false)` — objects as
 * `\stdClass`, NOT as associative arrays. That is deliberate and it is the
 * whole point: decoding to arrays would collapse `{}` and `[]` into the same
 * PHP value and the vector that exists to catch that would silently pass.
 */
final class CanonicalJsonParityTest extends TestCase
{
    private static function fixture(string $file): \stdClass
    {
        $raw = file_get_contents(__DIR__.'/fixtures/'.$file);
        self::assertIsString($raw, "shared fixture {$file} must be readable");

        // false => objects decode to \stdClass, preserving {} vs [].
        return json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return iterable<string, array{\stdClass, string, string}>
     */
    public static function vectors(): iterable
    {
        foreach (self::fixture('canonical-form.json')->vectors as $v) {
            yield $v->name => [$v->input, $v->canonical, $v->why];
        }
    }

    #[DataProvider('vectors')]
    public function testCanonicalFormMatchesTheSpecOracle(
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
        self::assertGreaterThanOrEqual(17, iterator_count(self::vectors()));
    }

    /**
     * Falsifiability — the spec's Category 20 check, run here on the vendored copy.
     *
     * A corpus that no longer separates right from wrong passes silently, and a
     * green suite then means nothing. So run the defect this SDK actually
     * shipped — `json_encode` without `JSON_UNESCAPED_LINE_TERMINATORS`, which
     * emits `\u2028`/`\u2029` for two characters §4.8.1 step 3 requires
     * literally — over the same vectors and require that the corpus REJECTS it.
     * If every vector accepts a broken canonicalizer, the discriminating
     * vectors have been removed or weakened and this test says so instead of
     * reporting green.
     *
     * sdk-ts runs the same check against ITS defect (UTF-16 key ordering); PHP
     * arrays never had that one, because they do not reorder integer-like keys
     * and `SORT_STRING` is already a byte comparison.
     */
    #[Test]
    public function theCorpusStillRejectsThePre0140EscapingDefect(): void
    {
        $broken = static function (mixed $data) use (&$broken): mixed {
            if ($data instanceof \stdClass) {
                $props = get_object_vars($data);
                ksort($props, SORT_STRING);
                $out = new \stdClass();
                foreach ($props as $k => $v) {
                    $out->{$k} = $broken($v);
                }

                return $out;
            }
            if (is_array($data)) {
                return array_map($broken, $data);
            }

            return $data;
        };

        $discriminating = [];
        foreach (self::vectors() as $name => [$input, $expected]) {
            // The pre-0.14.0 flag set: no JSON_UNESCAPED_LINE_TERMINATORS.
            $got = json_encode(
                $broken($input),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
            if ($got !== $expected) {
                $discriminating[] = $name;
            }
        }

        self::assertNotEmpty(
            $discriminating,
            'the corpus no longer separates PHP line-terminator escaping from the literal-emission rule',
        );
    }

    /**
     * §4.8 vs §5.3 step 1 — the boundary, and the divergence it hid.
     *
     * Not vendored from the spec: the spec's corpus deliberately carries no
     * message with a `mac`, because §4.8 is defined over any JSON value and
     * says nothing about the field. That silence is what let sdk-ts strip `mac`
     * inside its `canonicalize()` while this SDK stripped it one layer up in
     * `MacSigner` — two different answers for every message that carries one,
     * and no vector in either repo had a `mac` to notice with.
     *
     * @return iterable<string, array{\stdClass, string, string, string}>
     */
    public static function macVectors(): iterable
    {
        foreach (self::fixture('canonical-mac-strip.json')->vectors as $v) {
            yield $v->name => [$v->input, $v->canonical, $v->canonicalForMac, $v->why];
        }
    }

    #[DataProvider('macVectors')]
    public function testMacIsStrippedBySection53AndNotBySection48(
        \stdClass $input,
        string $canonical,
        string $canonicalForMac,
        string $why,
    ): void {
        $serializer = new CanonicalJsonSerializer();

        self::assertSame($canonical, $serializer->serialize($input), "pure §4.8 form keeps mac — {$why}");
        self::assertSame(
            $canonicalForMac,
            (new MacSigner($serializer))->canonicalize($input),
            "§5.3 step 1 removes the top-level mac — {$why}",
        );
    }

    #[Test]
    public function theMacFixtureCarriesTheFullVectorSet(): void
    {
        self::assertGreaterThanOrEqual(4, iterator_count(self::macVectors()));
    }
}
