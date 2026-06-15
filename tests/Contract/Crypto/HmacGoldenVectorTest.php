<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Crypto;

use Ospp\Protocol\Crypto\CanonicalJsonSerializer;
use Ospp\Protocol\Crypto\MacSigner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cross-language HMAC golden-vector parity.
 *
 * These vectors are BYTE-IDENTICAL with sdk-ts (tests/crypto/fixtures/hmac-golden-vectors.json).
 * Both `expectedCanonicalJson` and `expectedMac` were produced by an EXTERNAL oracle — never by an
 * SDK — so this test pins ospp-sdk-php's MacSigner against an independent ground truth that sdk-ts is
 * pinned against too. If PHP's canonical form or HMAC ever drifts from the spec / from sdk-ts, the
 * matching golden test in one of the two repos goes RED.
 *
 * Oracle: expectedCanonicalJson = OSPP Canonical Form (spec §4.8); expectedMac = openssl HMAC-SHA256.
 * Real spec payloads, safe-zone only (ASCII keys, integer/string scalars, no empty objects, no floats —
 * money/meter fields are integer atomic units per credit-amount.schema.json "integer, no floating point").
 */
final class HmacGoldenVectorTest extends TestCase
{
    private MacSigner $signer;

    protected function setUp(): void
    {
        $this->signer = new MacSigner(new CanonicalJsonSerializer());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function vectorProvider(): array
    {
        $fixture = self::loadFixture();
        $cases = [];
        foreach ($fixture['vectors'] as $vector) {
            $cases[$vector['name']] = [$vector, $fixture['sessionKeyBase64']];
        }

        return $cases;
    }

    /**
     * @param  array<string, mixed>  $vector
     */
    #[Test]
    #[DataProvider('vectorProvider')]
    public function canonical_form_matches_external_oracle(array $vector, string $sessionKey): void
    {
        self::assertSame(
            $vector['expectedCanonicalJson'],
            $this->signer->canonicalize($vector['message']),
            "Canonical-form divergence on golden vector '{$vector['name']}': "
            .'PHP CanonicalJsonSerializer != external oracle (and therefore != sdk-ts).',
        );
    }

    /**
     * @param  array<string, mixed>  $vector
     */
    #[Test]
    #[DataProvider('vectorProvider')]
    public function hmac_matches_external_oracle(array $vector, string $sessionKey): void
    {
        self::assertSame(
            $vector['expectedMac'],
            $this->signer->sign($vector['message'], $sessionKey),
            "HMAC divergence on golden vector '{$vector['name']}': "
            .'PHP MacSigner != external openssl oracle (and therefore != sdk-ts).',
        );
    }

    /**
     * @param  array<string, mixed>  $vector
     */
    #[Test]
    #[DataProvider('vectorProvider')]
    public function verify_accepts_the_oracle_mac(array $vector, string $sessionKey): void
    {
        self::assertTrue(
            $this->signer->verify($vector['message'], $vector['expectedMac'], $sessionKey),
            "verify() rejected the externally-computed MAC for vector '{$vector['name']}'.",
        );
    }

    /**
     * @return array{vectors: list<array<string, mixed>>, sessionKeyBase64: string}
     */
    private static function loadFixture(): array
    {
        $path = __DIR__.'/fixtures/hmac-golden-vectors.json';
        $json = file_get_contents($path);
        self::assertNotFalse($json, "Missing fixture: {$path}");

        /** @var array{vectors: list<array<string, mixed>>, sessionKeyBase64: string} $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
