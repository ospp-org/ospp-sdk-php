<?php

declare(strict_types=1);

namespace Ospp\Protocol\Crypto\Contracts;

/**
 * Interface for HMAC-SHA256 message signing operations.
 *
 * Used for MQTT message integrity verification (OSPP signing modes).
 */
interface SigningServiceInterface
{
    /**
     * Sign an outgoing MQTT message with HMAC-SHA256.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $sessionKey  Base64-encoded 32-byte session key
     * @return string Base64-encoded HMAC
     */
    public function sign(array $payload, string $sessionKey): string;

    /**
     * Verify an incoming MQTT message HMAC-SHA256 signature (timing-safe).
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $mac  Base64-encoded HMAC
     * @param  string  $sessionKey  Base64-encoded 32-byte session key
     */
    public function verify(array $payload, string $mac, string $sessionKey): bool;
}
