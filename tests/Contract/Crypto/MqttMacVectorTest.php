<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Crypto;

use Ospp\Protocol\Crypto\CanonicalJsonSerializer;
use Ospp\Protocol\Crypto\MacSigner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Envelope-MAC conformance against the spec's own vector (§5.4).
 *
 * `fixtures/mqtt-mac.json` is VENDORED BYTE-IDENTICALLY from
 * `conformance/test-vectors/crypto/mqtt-mac.json` at the ref in `.spec-ref`,
 * enforced by `scripts/check-crypto-vectors.sh`.
 *
 * WHY THIS TEST EXISTS AT ALL, and it is not "one more vector". Until
 * 2026-09-09 this SDK vendored four of the spec's five crypto vectors and this
 * was the missing one — for nine releases, while `check-crypto-vectors.sh`
 * printed "OK — vendored crypto corpus byte-identical" every run. The gate
 * iterated the VENDORED directory, so a file that was never copied was never
 * walked and never missed. Vendoring it without consuming it would repeat the
 * same mistake one layer up: a file nothing reads is a file whose corruption
 * nothing reports.
 *
 * The vector pins ONE thing the formula did not say in words until spec 0.13.0:
 * the HMAC key is the DECODED 32 bytes of `sessionKey`, not the 44-character
 * Base64 text that carries it on the wire. Both reference implementations
 * already decoded; the spec never said so. A reader following the formula
 * literally produces `macIfKeyNotDecoded` and fails against every conforming
 * peer — with no error anywhere, because the messages that carry a MAC most
 * often are EVENTs, which are refused silently.
 *
 * The negative assertion is therefore not decoration. `macIfKeyNotDecoded` is
 * recorded by the spec precisely so an implementer who computes it recognises
 * the mistake instead of hunting a canonicalization bug, and the vector's own
 * comment says a verifier MUST assert it differs.
 */
final class MqttMacVectorTest extends TestCase
{
    /** @var array<string, mixed> */
    private static array $vector;

    private static MacSigner $signer;

    public static function setUpBeforeClass(): void
    {
        $raw = file_get_contents(__DIR__.'/fixtures/mqtt-mac.json');
        self::assertIsString($raw, 'the vendored mqtt-mac.json vector must be readable');

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::$vector = $decoded;
        self::$signer = new MacSigner(new CanonicalJsonSerializer());
    }

    /** @return array<string, mixed> */
    private static function message(): array
    {
        /** @var array<string, mixed> $m */
        $m = self::$vector['message'];

        return $m;
    }

    #[Test]
    public function the_canonical_form_is_the_bytes_the_vector_pins(): void
    {
        $canonical = self::$signer->canonicalize(self::message());

        self::assertSame(
            self::$vector['canonicalJson'],
            $canonical,
            'canonical form diverged from the spec vector — this is a canonicalization defect, not a key defect',
        );

        // The byte length is carried separately in the vector and is the cheapest
        // discriminator an integrator can check first: a length mismatch is
        // canonicalization, a length match with a MAC mismatch is the key.
        self::assertSame(
            self::$vector['canonicalLengthBytes'],
            strlen($canonical),
            'canonical byte length diverged from the spec vector',
        );
    }

    #[Test]
    public function the_mac_is_the_one_the_spec_computed(): void
    {
        /** @var array<string, mixed> $key */
        $key = self::$vector['key'];

        self::assertSame(
            self::$vector['mac'],
            self::$signer->sign(self::message(), (string) $key['sessionKeyBase64']),
            'MacSigner disagrees with the spec vector for §5.4',
        );
    }

    #[Test]
    public function the_key_is_the_decoded_bytes_and_not_the_base64_text(): void
    {
        /** @var array<string, mixed> $key */
        $key = self::$vector['key'];
        $sessionKeyBase64 = (string) $key['sessionKeyBase64'];

        // The vector's own key facts, asserted rather than assumed: 44 characters
        // of Base64 carrying 32 bytes, reproducible from its recorded derivation
        // so the value can be regenerated from nothing but the file.
        self::assertSame($key['base64TextLengthChars'], strlen($sessionKeyBase64));
        $rawKey = base64_decode($sessionKeyBase64, true);
        self::assertIsString($rawKey);
        self::assertSame($key['decodedLengthBytes'], strlen($rawKey));
        self::assertSame($key['hex'], bin2hex($rawKey));
        self::assertSame(
            $key['hex'],
            hash('sha256', 'OSPP MQTT MAC test vector v1'),
            'the vector key no longer reproduces from its own recorded derivation',
        );

        // THE NEGATIVE. Passing the 44-character Base64 TEXT as the HMAC key —
        // the literal reading of the pre-0.13.0 formula — must produce the value
        // the vector records as non-conforming, and that value must differ from
        // the real MAC. Asserting both halves is what makes this a control:
        // equality alone would pass if the two were somehow the same string.
        /** @var array<string, mixed> $notDecoded */
        $notDecoded = self::$vector['macIfKeyNotDecoded'];
        $literalReading = base64_encode(
            hash_hmac('sha256', (string) self::$vector['canonicalJson'], $sessionKeyBase64, true),
        );

        self::assertSame($notDecoded['value'], $literalReading);
        self::assertNotSame(
            self::$vector['mac'],
            $literalReading,
            'the conforming MAC and the undecoded-key MAC are identical — the vector has lost its discriminating power',
        );
    }

    #[Test]
    public function verification_accepts_the_vector_mac_and_refuses_a_tampered_envelope(): void
    {
        /** @var array<string, mixed> $key */
        $key = self::$vector['key'];
        $sessionKey = (string) $key['sessionKeyBase64'];
        $mac = (string) self::$vector['mac'];

        self::assertTrue(
            self::$signer->verify(self::message(), $mac, $sessionKey),
            'verify() rejected the spec vector its own sign() reproduces',
        );

        // One field changed, same MAC. §5.5 step 5: reject.
        $tampered = self::message();
        $tampered['timestamp'] = '2000-01-01T00:00:00.000Z';

        self::assertFalse(
            self::$signer->verify($tampered, $mac, $sessionKey),
            'verify() accepted an envelope whose timestamp was changed under a fixed MAC',
        );
    }
}
