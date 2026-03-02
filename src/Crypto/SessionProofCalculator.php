<?php

declare(strict_types=1);

namespace Ospp\Protocol\Crypto;

/**
 * Computes and verifies BLE sessionProof values.
 *
 * sessionProof = HMAC-SHA256(
 *   key:  SessionKey,
 *   data: UTF8(offlinePassId) || "|" || BE32(txCounter) || "|" || UTF8(bayId) || "|" || UTF8(serviceId)
 * )
 *
 * Output: hex-encoded lowercase, 64 characters (256 bits).
 */
final class SessionProofCalculator
{
    /**
     * Compute a sessionProof.
     *
     * @param  string  $sessionKey  Raw 32-byte session key
     * @param  string  $offlinePassId  The offline pass identifier
     * @param  int  $txCounter  Monotonically increasing transaction counter
     * @param  string  $bayId  Bay identifier
     * @param  string  $serviceId  Service identifier
     * @return string Hex-encoded lowercase HMAC-SHA256 (64 characters)
     */
    public function compute(
        string $sessionKey,
        string $offlinePassId,
        int $txCounter,
        string $bayId,
        string $serviceId,
    ): string {
        $data = $offlinePassId
            . '|'
            . pack('N', $txCounter)
            . '|'
            . $bayId
            . '|'
            . $serviceId;

        return hash_hmac('sha256', $data, $sessionKey);
    }

    /**
     * Verify a sessionProof using timing-safe comparison.
     *
     * @param  string  $proof  Received hex-encoded sessionProof
     * @param  string  $sessionKey  Raw 32-byte session key
     * @param  string  $offlinePassId  The offline pass identifier
     * @param  int  $txCounter  Monotonically increasing transaction counter
     * @param  string  $bayId  Bay identifier
     * @param  string  $serviceId  Service identifier
     */
    public function verify(
        string $proof,
        string $sessionKey,
        string $offlinePassId,
        int $txCounter,
        string $bayId,
        string $serviceId,
    ): bool {
        $expected = $this->compute($sessionKey, $offlinePassId, $txCounter, $bayId, $serviceId);

        return hash_equals($expected, $proof);
    }
}
