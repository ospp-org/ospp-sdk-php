<?php

declare(strict_types=1);

namespace Ospp\Protocol\Crypto;

use Mdanter\Ecc\Crypto\Key\PrivateKey;
use Mdanter\Ecc\Crypto\Signature\Signature;
use Mdanter\Ecc\Crypto\Signature\Signer;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Random\RandomGeneratorFactory;
use Mdanter\Ecc\Serializer\Signature\DerSignatureSerializer;
use Ospp\Protocol\Crypto\Contracts\EcdsaServiceInterface;
use RuntimeException;

/**
 * ECDSA P-256 signing and verification for OSPP.
 *
 * Source: spec/06-security.md §4.1 (algorithm inventory), §4.3 + §6.2
 * (RFC 6979 deterministic nonces are normative MUST for all software-based
 * ECDSA signing — hardware secure elements with internal RNG are exempt).
 *
 * Signing uses paragonie/ecc (RFC 6979 via HMAC-DRBG nonce derivation).
 * The prior implementation used openssl_sign, which generates a random nonce
 * per signature — non-compliant with the spec and non-reproducible across
 * runs. Verification continues to use openssl_verify since verify is
 * nonce-agnostic and accepts any DER ECDSA-P256-SHA256 signature.
 *
 * Requires ext-gmp for the underlying big-integer arithmetic.
 */
final class EcdsaService implements EcdsaServiceInterface
{
    public function __construct(
        private readonly CanonicalJsonSerializer $canonicalJsonSerializer,
    ) {}

    public function sign(string $data, string $privateKeyPem): string
    {
        $opensslKey = openssl_pkey_get_private($privateKeyPem);

        if ($opensslKey === false) {
            throw new RuntimeException('Failed to load ECDSA private key: ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($opensslKey);

        if ($details === false) {
            throw new RuntimeException('Failed to get private key details: ' . openssl_error_string());
        }

        if (($details['type'] ?? -1) !== OPENSSL_KEYTYPE_EC) {
            throw new RuntimeException('Expected an EC private key, got type: ' . ($details['type'] ?? 'unknown'));
        }

        $curveName = $details['ec']['curve_name'] ?? null;
        $scalarBytes = $details['ec']['d'] ?? null;

        // openssl_pkey_get_details() returns the EC private scalar `d` big-endian
        // with leading zero bytes stripped, so a scalar with a high zero byte
        // (~1/256 of generated P-256 keys) comes back as 31 (or fewer) bytes.
        // Left-pad to the fixed 32-byte width: the big-endian integer value is
        // unchanged (gmp_import below yields the identical scalar, hence a
        // byte-identical signature), while the fixed-width invariant the guard
        // and downstream code assume now holds. A scalar longer than 32 bytes is
        // left untouched and still rejected below (str_pad never truncates).
        if (is_string($scalarBytes) && strlen($scalarBytes) < 32) {
            $scalarBytes = str_pad($scalarBytes, 32, "\x00", STR_PAD_LEFT);
        }

        if ($curveName !== 'prime256v1' || ! is_string($scalarBytes) || strlen($scalarBytes) !== 32) {
            throw new RuntimeException(
                'Expected an EC P-256 (prime256v1) private key with a 32-byte scalar',
            );
        }

        // openssl_pkey_get_details() returns the EC private scalar `d` as raw
        // big-endian bytes; gmp_import converts that to the GMP integer
        // paragonie/ecc expects for PrivateKey construction.
        $scalar = gmp_import($scalarBytes);
        $adapter = EccFactory::getAdapter();
        $generator = EccFactory::getNistCurves()->generator256();
        $privateKey = new PrivateKey($adapter, $generator, $scalar);

        // SHA-256 the message ourselves and hand the hash (as GMP int) to
        // the signer. paragonie/ecc Signer::sign expects an already-digested
        // value; double-hashing would produce signatures that do not verify
        // against openssl_verify (which hashes the message once internally).
        $hash = gmp_import(hash('sha256', $data, true));

        // RFC 6979 deterministic nonce: HMAC-DRBG seeded from (privateKey, hash).
        // The factory's signature is fixed; passing the same inputs across runs
        // produces the same k, hence the same (r, s) and the same DER bytes.
        $hmacRng = RandomGeneratorFactory::getHmacRandomGenerator($privateKey, $hash, 'sha256');
        $k = $hmacRng->generate($generator->getOrder());

        $signer = new Signer($adapter);
        $signature = $signer->sign($privateKey, $hash, $k);

        // Low-s normalization (anti-malleability). RFC 6979 alone leaves `s`
        // in either half of the order; the industry convention — followed by
        // BIP-66 in Bitcoin, @noble/curves p256 by default, OpenSSL ≥ 1.1,
        // and the OSPP cross-language test corpus — is to canonicalise to the
        // lower half so two compliant implementations produce byte-identical
        // signatures over the same (key, message). Without this step, PHP and
        // sdk-ts produce the same `r` but a complemented `s` whenever raw `s`
        // exceeds n/2 (verifies on both sides, but breaks byte-equality and
        // the byte-reproducibility guarantee published examples rely on).
        $order = $generator->getOrder();
        $halfOrder = gmp_div($order, 2);
        $s = $signature->getS();

        if (gmp_cmp($s, $halfOrder) > 0) {
            $signature = new Signature($signature->getR(), gmp_sub($order, $s));
        }

        $derSerializer = new DerSignatureSerializer();

        return base64_encode($derSerializer->serialize($signature));
    }

    public function verify(string $data, string $signatureBase64, string $publicKeyPem): bool
    {
        $publicKey = openssl_pkey_get_public($publicKeyPem);

        if ($publicKey === false) {
            throw new RuntimeException('Failed to load ECDSA public key: ' . openssl_error_string());
        }

        $signature = base64_decode($signatureBase64, true);

        if ($signature === false) {
            return false;
        }

        $result = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($result === -1) {
            throw new RuntimeException('ECDSA verification error: ' . openssl_error_string());
        }

        return $result === 1;
    }

    /**
     * @param  array<string, mixed>  $passData
     */
    public function signOfflinePass(array $passData, string $privateKeyPem): string
    {
        unset($passData['signature'], $passData['signatureAlgorithm']);

        $canonicalJson = $this->canonicalJsonSerializer->serialize($passData);

        return $this->sign($canonicalJson, $privateKeyPem);
    }

    /**
     * @param  array<string, mixed>  $passData
     */
    public function verifyOfflinePass(array $passData, string $publicKeyPem): bool
    {
        $signatureBase64 = $passData['signature'] ?? null;

        if (! is_string($signatureBase64) || $signatureBase64 === '') {
            return false;
        }

        unset($passData['signature'], $passData['signatureAlgorithm']);

        $canonicalJson = $this->canonicalJsonSerializer->serialize($passData);

        return $this->verify($canonicalJson, $signatureBase64, $publicKeyPem);
    }

    /**
     * @return array{privateKey: string, publicKey: string}
     */
    public function generateKeyPair(): array
    {
        $config = [
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];

        $key = openssl_pkey_new($config);

        if ($key === false) {
            throw new RuntimeException('Failed to generate ECDSA P-256 key pair: ' . openssl_error_string());
        }

        $privateKeyPem = '';
        $exportResult = openssl_pkey_export($key, $privateKeyPem);

        if ($exportResult === false) {
            throw new RuntimeException('Failed to export ECDSA private key: ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($key);

        if ($details === false) {
            throw new RuntimeException('Failed to get ECDSA key details: ' . openssl_error_string());
        }

        /** @var string $publicKeyPem */
        $publicKeyPem = $details['key'];

        return [
            'privateKey' => $privateKeyPem,
            'publicKey' => $publicKeyPem,
        ];
    }
}
