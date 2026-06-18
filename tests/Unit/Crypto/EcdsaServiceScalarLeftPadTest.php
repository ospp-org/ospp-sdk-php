<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Crypto;

use Ospp\Protocol\Crypto\CanonicalJsonSerializer;
use Ospp\Protocol\Crypto\EcdsaService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the P-256 private-scalar left-pad fix.
 *
 * OpenSSL's openssl_pkey_get_details() returns the EC private scalar `d` as
 * big-endian bytes with leading zero bytes stripped. ~1/256 of freshly
 * generated P-256 keys have a high zero byte and therefore come back as a
 * 31-byte (or shorter) scalar. EcdsaService::sign() guards on an exact 32-byte
 * length, so before the left-pad fix it throws on those keys — the recurring
 * ~1/256 "Expected an EC P-256 (prime256v1) private key with a 32-byte scalar"
 * flake. The fix left-pads `d` to 32 bytes at key-loading; the big-endian
 * integer value is unchanged (gmp_import yields the identical scalar), so the
 * produced signature is byte-identical for normal keys.
 *
 * The fixtures below are captured, not generated at runtime, so the test is
 * deterministic (a runtime keygen loop would itself be ~1/256 flaky).
 */
final class EcdsaServiceScalarLeftPadTest extends TestCase
{
    private EcdsaService $ecdsa;

    protected function setUp(): void
    {
        $this->ecdsa = new EcdsaService(new CanonicalJsonSerializer());
    }

    /**
     * A real P-256 key whose private scalar `d` is 31 bytes (OpenSSL stripped a
     * leading 0x00). Captured once via a keygen loop; deterministic thereafter.
     */
    private const SHORT_SCALAR_PRIVATE_PEM = <<<'PEM'
        -----BEGIN PRIVATE KEY-----
        MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgAEc74FlOPtxselj/
        bvogx9ZriuK0H5Ltsd62d9Ua+9+hRANCAATuJnNF4qlA3BRTYxSdQhvZ9SCB0YY3
        wE6WDZ7n9JTqNf5+4Ipg1RdljwiPmMSE7e0jRjUiZr2vvRXkEVzvH9w7
        -----END PRIVATE KEY-----
        PEM;

    private const SHORT_SCALAR_PUBLIC_PEM = <<<'PEM'
        -----BEGIN PUBLIC KEY-----
        MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE7iZzReKpQNwUU2MUnUIb2fUggdGG
        N8BOlg2e5/SU6jX+fuCKYNUXZY8Ij5jEhO3tI0Y1Ima9r70V5BFc7x/cOw==
        -----END PUBLIC KEY-----
        PEM;

    /**
     * A normal 32-byte-scalar key + the RFC 6979 deterministic signature it
     * produces over GOLDEN_MESSAGE, captured on the PRE-fix code. The left-pad
     * is a no-op for 32-byte scalars, so this signature MUST stay byte-identical
     * after the fix — the zero-output-change / contract-stability guard.
     */
    private const GOLDEN_PRIVATE_PEM = <<<'PEM'
        -----BEGIN PRIVATE KEY-----
        MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQg4wurzMB8M20HGW6z
        HaNJuEwtKlg8h7bZ9Pt/LvPasqWhRANCAATU95MnZu6I3pLmJvHfPgfZNUzAKYZZ
        Rf/Dpz/kYJmen5+oJCjhmL/TpDWfOm+VCUnzWsmlwiQkL/qGWVmytIKO
        -----END PRIVATE KEY-----
        PEM;

    private const GOLDEN_PUBLIC_PEM = <<<'PEM'
        -----BEGIN PUBLIC KEY-----
        MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE1PeTJ2buiN6S5ibx3z4H2TVMwCmG
        WUX/w6c/5GCZnp+fqCQo4Zi/06Q1nzpvlQlJ81rJpcIkJC/6hllZsrSCjg==
        -----END PUBLIC KEY-----
        PEM;

    private const GOLDEN_MESSAGE = 'OSPP EC-scalar golden vector v1';

    private const GOLDEN_SIGNATURE = 'MEUCIQDIlxfd3ie7jJPSN3dkOW+4ZljiaKRV+VFsVSRrZimkTwIgT5ICxXnDquGQqOnV75Bhpolp74Kgoki0PLWBypPr2qY=';

    #[Test]
    public function signSucceedsWith31ByteLeadingZeroScalar(): void
    {
        // Sanity: this fixture's private scalar really is shorter than 32 bytes
        // (OpenSSL stripped the leading zero) — the exact condition that trips
        // the pre-fix length guard.
        $details = openssl_pkey_get_details(openssl_pkey_get_private(self::SHORT_SCALAR_PRIVATE_PEM));
        self::assertLessThan(
            32,
            strlen($details['ec']['d']),
            'fixture must have a leading-zero-stripped (<32-byte) private scalar',
        );

        // Pre-fix: sign() throws "Expected ... 32-byte scalar". Post-fix: it
        // left-pads d to 32 bytes and produces a verifiable signature.
        $payload = 'offline-receipt-payload';
        $signature = $this->ecdsa->sign($payload, self::SHORT_SCALAR_PRIVATE_PEM);

        self::assertTrue(
            $this->ecdsa->verify($payload, $signature, self::SHORT_SCALAR_PUBLIC_PEM),
            'signature produced from a 31-byte-scalar key must verify',
        );
    }

    #[Test]
    public function leftPadIsByteIdenticalNoOpForNormal32ByteScalar(): void
    {
        // The left-pad never touches a 32-byte scalar (str_pad of a 32-byte
        // string is the string itself), so the RFC 6979 deterministic signature
        // over a normal key MUST equal the value captured before the fix.
        $signature = $this->ecdsa->sign(self::GOLDEN_MESSAGE, self::GOLDEN_PRIVATE_PEM);

        self::assertSame(
            self::GOLDEN_SIGNATURE,
            $signature,
            'normal-key signature MUST be byte-identical after the left-pad fix (zero contract change)',
        );
        self::assertTrue(
            $this->ecdsa->verify(self::GOLDEN_MESSAGE, $signature, self::GOLDEN_PUBLIC_PEM),
        );
    }
}
