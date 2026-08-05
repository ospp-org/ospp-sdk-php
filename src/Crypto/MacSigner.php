<?php

declare(strict_types=1);

namespace Ospp\Protocol\Crypto;

/**
 * HMAC-SHA256 message signer and verifier.
 *
 * Signing: Remove mac field -> Canonical JSON -> HMAC-SHA256(base64_decode(sessionKey), canonical) -> base64_encode
 * Verify: Recompute expected MAC -> timing-safe hash_equals on raw bytes
 *
 * Both directions fail closed (spec/06-security.md §5.7). They are the same
 * condition — no usable key — read from two ends, and they act differently only
 * in HOW they refuse: a sender raises, because its alternatives are to publish
 * unsigned or to drop silently and the clause forbids both; a receiver returns
 * false, because rejecting a message is its normal business.
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
     * @param  string  $sessionKey  Base64-encoded session key
     * @return string Base64-encoded HMAC-SHA256 signature
     *
     * @throws \RuntimeException when no usable session key is held. §5.7:
     *   "A sender holding no key MUST refuse to send. It MUST NOT publish the
     *   message unsigned. It MUST log the refusal and surface it to the
     *   operator, and MUST NOT silently drop it without a record." Raising is
     *   the only response that is neither of the two the clause forbids.
     */
    public function sign(array $payload, string $sessionKey): string
    {
        $rawKey = self::decodeKey($sessionKey);

        if ($rawKey === null) {
            // Sending unsigned is the option that makes the immediate symptom go
            // away and hands the attacker the whole mechanism: "a server that
            // publishes an unsigned StartService has produced exactly the
            // message the MAC exists to stop, and has taught the fleet to accept
            // it." The recovery is always the same and already exists — get a
            // key, which means boot.
            throw new \RuntimeException(
                'Refusing to sign: no usable session key is held. A sender with no key MUST NOT '
                .'publish unsigned (spec 06-security.md §5.7). Recover by booting, which issues one.',
            );
        }

        unset($payload['mac']);

        $canonical = $this->serializer->serialize($payload);

        return base64_encode(hash_hmac('sha256', $canonical, $rawKey, true));
    }

    /**
     * Verify a message's HMAC-SHA256 signature using timing-safe comparison.
     *
     * @param  array<string, mixed>  $payload  Message payload (WITHOUT the `mac` field)
     * @param  string  $mac  Received Base64-encoded MAC
     * @param  string  $sessionKey  Base64-encoded session key
     */
    public function verify(array $payload, string $mac, string $sessionKey): bool
    {
        // §5.7 Receiving: "No session key held for the peer | 1013 MAC_MISSING |
        // Reject the message. A receiver that holds no key cannot verify, and
        // cannot therefore accept."
        if (self::decodeKey($sessionKey) === null) {
            return false;
        }

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

    /**
     * Decode a session key, or null if none is usable.
     *
     * "Usable" is: non-blank, and valid base64. Length is deliberately NOT
     * checked. §5.2 requires the SERVER to generate 32 bytes, but
     * `boot-notification-response.schema.json` accepts a base64 string of 1 to
     * 1024 characters and no clause makes a station reject a conforming
     * response that carries a different length. An SDK stricter than the schema
     * would refuse a server the schema admits.
     */
    private static function decodeKey(string $sessionKey): ?string
    {
        if (trim($sessionKey) === '') {
            return null;
        }

        $decoded = base64_decode($sessionKey, true);

        if ($decoded === false || $decoded === '') {
            return null;
        }

        return $decoded;
    }
}
