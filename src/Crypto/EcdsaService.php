<?php

declare(strict_types=1);

namespace Ospp\Protocol\Crypto;

use Ospp\Protocol\Crypto\Contracts\EcdsaServiceInterface;
use RuntimeException;

/**
 * ECDSA P-256 signing service for offline pass operations.
 *
 * Uses OpenSSL with the prime256v1 (P-256) curve and SHA-256 digest.
 * All signatures are DER-encoded and returned as Base64 strings.
 */
final class EcdsaService implements EcdsaServiceInterface
{
    public function __construct(
        private readonly CanonicalJsonSerializer $canonicalJsonSerializer,
    ) {}

    public function sign(string $data, string $privateKeyPem): string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if ($privateKey === false) {
            throw new RuntimeException('Failed to load ECDSA private key: ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($privateKey);

        if ($details === false) {
            throw new RuntimeException('Failed to get private key details: ' . openssl_error_string());
        }

        if (($details['type'] ?? -1) !== OPENSSL_KEYTYPE_EC) {
            throw new RuntimeException('Expected an EC private key, got type: ' . ($details['type'] ?? 'unknown'));
        }

        $signature = '';
        $result = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if ($result === false) {
            throw new RuntimeException('ECDSA signing failed: ' . openssl_error_string());
        }

        return base64_encode($signature);
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
