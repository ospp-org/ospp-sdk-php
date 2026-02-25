<?php

declare(strict_types=1);

namespace Ospp\Protocol\Crypto\Contracts;

/**
 * Interface for ECDSA P-256 digital signature operations.
 *
 * Used for offline pass signing and verification (OSPP offline mode).
 * Separate from SigningServiceInterface which handles HMAC-SHA256 for MQTT messages.
 */
interface EcdsaServiceInterface
{
    /**
     * Sign raw data with ECDSA P-256 using SHA-256 digest.
     *
     * @param  string  $data  The data to sign
     * @param  string  $privateKeyPem  PEM-encoded EC private key
     * @return string Base64-encoded DER signature
     *
     * @throws \RuntimeException If the key is invalid or signing fails
     */
    public function sign(string $data, string $privateKeyPem): string;

    /**
     * Verify an ECDSA P-256 signature.
     *
     * @param  string  $data  The original signed data
     * @param  string  $signatureBase64  Base64-encoded DER signature
     * @param  string  $publicKeyPem  PEM-encoded EC public key
     * @return bool True if the signature is valid
     *
     * @throws \RuntimeException If the key is invalid or verification fails
     */
    public function verify(string $data, string $signatureBase64, string $publicKeyPem): bool;

    /**
     * Sign an offline pass data array.
     *
     * Removes signature/signatureAlgorithm fields, canonicalizes the remaining
     * data to JSON, and signs with ECDSA P-256.
     *
     * @param  array<string, mixed>  $passData  The pass data (signature fields will be stripped)
     * @param  string  $privateKeyPem  PEM-encoded EC private key
     * @return string Base64-encoded DER signature
     */
    public function signOfflinePass(array $passData, string $privateKeyPem): string;

    /**
     * Verify an offline pass signature.
     *
     * Extracts the signature, removes signature fields, canonicalizes,
     * and verifies against the public key.
     *
     * @param  array<string, mixed>  $passData  The full pass data including signature
     * @param  string  $publicKeyPem  PEM-encoded EC public key
     * @return bool True if the pass signature is valid
     */
    public function verifyOfflinePass(array $passData, string $publicKeyPem): bool;

    /**
     * Generate a new ECDSA P-256 key pair.
     *
     * @return array{privateKey: string, publicKey: string} PEM-encoded key pair
     *
     * @throws \RuntimeException If key generation fails
     */
    public function generateKeyPair(): array;
}
