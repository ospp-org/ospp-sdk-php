<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Crypto;

use Ospp\Protocol\Crypto\CanonicalJsonSerializer;
use Ospp\Protocol\Crypto\EcdsaService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TAMPER REJECTION — the negative direction, which this SDK had never proven.
 *
 * Every other crypto test here proves that a signature this SDK PRODUCES verifies.
 * A round trip is green for an implementation that verifies nothing at all, as long
 * as it signs and checks with the same broken rule. The conformance corpus could not
 * close it either: every vector under tests/Fixtures/test-vectors/invalid/ is
 * SCHEMA-invalid, and ConformanceVectorTest's own `validate()` returns `bool` —
 * the error object is discarded at the function boundary, so "rejected" there
 * cannot mean "the signature was wrong" even if someone wanted it to.
 *
 * Meanwhile conformance/test-cases asks an implementer, in four places
 * (TC-SEC-001 §50-51, TC-SEC-004 §34, TC-OFF-002 §122, TC-OFF-005 §220), to prove
 * a tampered message is refused. Until this file, nothing in any of the three trees
 * could pass that test — we asked for a test we could not pass ourselves.
 *
 * `fixtures/tamper-rejection.json` is VENDORED BYTE-IDENTICALLY from the spec
 * (`conformance/test-vectors/crypto/tamper-rejection.json` at the ref in
 * `.spec-ref`), enforced by `scripts/check-crypto-vectors.sh`. Each vector carries
 * the exact bytes the signature covers, the signature, and the key inline, so this
 * file contains no canonicalisation of its own — six per-surface rules restated here
 * would be six more chances to get one wrong, and a green test proving only that the
 * copy agrees with itself.
 *
 * THREE MUTATION CLASSES, all of which the corpus must contain:
 *   BODY — the message moved, the signature is byte-identical → the signature is
 *          bound to CONTENT.
 *   SIG  — the signature moved by exactly ONE BIT, message byte-identical, DER
 *          framing intact → the signature is CHECKED, and not by the DER parser.
 *   KEY  — nothing moved; a different published key is offered → binding is to an
 *          IDENTITY.
 *
 * ANTI-VACUITY, and it is a separate test that runs per vector: the UNTAMPERED base
 * must VERIFY. Without it a wrong key format, a bad Base64 decode or a renamed
 * method would all return false and be scored as a pass. A proof of refusal that
 * cannot tell refusal from breakage proves nothing.
 */
final class TamperRejectionTest extends TestCase
{
    private const FIXTURE = __DIR__.'/fixtures/tamper-rejection.json';

    /** Floors mirrored from the spec-side verifier, deliberately: two repositories agreeing the corpus is big enough beats one. */
    private const MIN_VECTORS = 12;

    private const MIN_SURFACES = 8;

    /** @return array<string, mixed> */
    private static function corpus(): array
    {
        $raw = file_get_contents(self::FIXTURE);
        self::assertIsString($raw, 'vendored tamper corpus is unreadable');

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Verify one portable triple with THIS SDK's primitives.
     *
     * ECDSA goes through EcdsaService, the class a consumer would use. The two BLE
     * transcripts and the §5.4 message MAC are raw HMAC over bytes the corpus already
     * carries — there is no payload object to hand to MacSigner::verify(), which
     * canonicalises an array — so they use hash_hmac directly, which is the same
     * primitive MacSigner:38 calls one line down.
     *
     * @param array<string, mixed> $side
     */
    private function verifySide(string $algorithm, array $side): bool
    {
        $bytes = base64_decode($side['signedBytesBase64'], true);
        self::assertIsString($bytes, 'signedBytesBase64 is not valid Base64');

        if ($algorithm === 'HMAC-SHA256') {
            $key = base64_decode($side['keyMaterial'], true);
            self::assertIsString($key, 'keyMaterial is not valid Base64');
            $expected = hash_hmac('sha256', $bytes, $key, true);
            $actual = base64_decode($side['signature'], true);

            return is_string($actual) && hash_equals($expected, $actual);
        }

        return (new EcdsaService(new CanonicalJsonSerializer))->verify($bytes, $side['signature'], $side['keyMaterial']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function vectors(): iterable
    {
        foreach (self::corpus()['vectors'] as $v) {
            yield $v['id'] => [$v];
        }
    }

    #[Test]
    public function the_vendored_corpus_is_large_enough_to_mean_something(): void
    {
        $c = self::corpus();
        $vectors = $c['vectors'];

        self::assertGreaterThanOrEqual(self::MIN_VECTORS, count($vectors));
        self::assertGreaterThanOrEqual(
            self::MIN_SURFACES,
            count(array_unique(array_column($vectors, 'surface')))
        );
        self::assertSame($c['count'], count($vectors), 'declared count must match the array');
    }

    #[Test]
    public function all_three_mutation_classes_are_represented(): void
    {
        $classes = array_unique(array_column(self::corpus()['vectors'], 'class'));
        foreach (['BODY', 'SIG', 'KEY'] as $expected) {
            self::assertContains($expected, $classes, "mutation class {$expected} is missing");
        }
    }

    /**
     * ANTI-VACUITY. If this fails for a vector, that vector's refusal below is worthless.
     *
     * @param array<string, mixed> $v
     */
    #[Test]
    #[DataProvider('vectors')]
    public function the_untampered_base_verifies(array $v): void
    {
        self::assertTrue(
            $this->verifySide($v['portable']['algorithm'], $v['portable']['base']),
            "{$v['id']}: the UNTAMPERED base must verify, otherwise the refusal proves nothing"
        );
    }

    /** @param array<string, mixed> $v */
    #[Test]
    #[DataProvider('vectors')]
    public function the_tampered_document_is_refused(array $v): void
    {
        self::assertFalse(
            $this->verifySide($v['portable']['algorithm'], $v['portable']['tampered']),
            "{$v['id']}: {$v['what']} — this MUST be refused"
        );
    }

    /** @param array<string, mixed> $v */
    #[Test]
    #[DataProvider('vectors')]
    public function the_mutation_is_exactly_what_the_vector_claims(array $v): void
    {
        $base = $v['portable']['base'];
        $tampered = $v['portable']['tampered'];

        if ($v['class'] === 'SIG') {
            self::assertSame($base['signedBytesBase64'], $tampered['signedBytesBase64'], 'class SIG must not move the message');
            $x = base64_decode($base['signature'], true);
            $y = base64_decode($tampered['signature'], true);
            self::assertIsString($x);
            self::assertIsString($y);
            self::assertSame(strlen($x), strlen($y), 'class SIG must preserve DER framing');
            $bits = 0;
            for ($i = 0; $i < strlen($x); $i++) {
                $bits += substr_count(decbin(ord($x[$i]) ^ ord($y[$i])), '1');
            }
            self::assertSame(1, $bits, 'class SIG must differ by exactly one bit');
        } elseif ($v['class'] === 'BODY') {
            self::assertSame($base['signature'], $tampered['signature'], 'class BODY must leave the signature byte-identical');
            self::assertNotSame($base['signedBytesBase64'], $tampered['signedBytesBase64'], 'class BODY must actually move the message');
        } else {
            self::assertSame($base['signature'], $tampered['signature']);
            self::assertSame($base['signedBytesBase64'], $tampered['signedBytesBase64']);
            self::assertNotSame($base['keyMaterial'], $tampered['keyMaterial'], 'class KEY must offer a DIFFERENT key');
        }
    }
}
