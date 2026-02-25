<?php

declare(strict_types=1);

namespace Ospp\Protocol\Crypto;

/**
 * HMAC-SHA256 message signer and verifier.
 *
 * Signing: Remove mac field -> Canonical JSON -> HMAC-SHA256(base64_decode(sessionKey), canonical) -> base64_encode
 * Verify: Recompute expected MAC -> timing-safe hash_equals on raw bytes
 */
final class MacSigner
{
    public function __construct(
        private readonly CanonicalJsonSerializer $serializer,
    ) {}

    /**
     * Sign a message payload with HMAC-SHA256.
     *
     * @param  array<string, mixed>  $payload  Message payload (WITHOUT the `mac` field)
     * @param  string  $sessionKey  Base64-encoded 32-byte session key
     * @return string Base64-encoded HMAC-SHA256 signature
     */
    public function sign(array $payload, string $sessionKey): string
    {
        unset($payload['mac']);

        $canonical = $this->serializer->serialize($payload);
        $decoded = base64_decode($sessionKey, true);
        $rawKey = $decoded !== false ? $decoded : '';
        $mac = hash_hmac('sha256', $canonical, $rawKey, true);

        return base64_encode($mac);
    }

    /**
     * Verify a message's HMAC-SHA256 signature using timing-safe comparison.
     *
     * @param  array<string, mixed>  $payload  Message payload (WITHOUT the `mac` field)
     * @param  string  $mac  Received Base64-encoded MAC
     * @param  string  $sessionKey  Base64-encoded 32-byte session key
     */
    public function verify(array $payload, string $mac, string $sessionKey): bool
    {
        $expectedMac = $this->sign($payload, $sessionKey);

        $receivedBytes = base64_decode($mac, true);
        $expectedBytes = base64_decode($expectedMac, true);

        if ($receivedBytes === false || $expectedBytes === false) {
            return false;
        }

        return hash_equals($expectedBytes, $receivedBytes);
    }

    /**
     * Get the canonical JSON representation of a payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function canonicalize(array $payload): string
    {
        unset($payload['mac']);

        return $this->serializer->serialize($payload);
    }
}
