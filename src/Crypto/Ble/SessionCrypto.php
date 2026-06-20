<?php

declare(strict_types=1);

namespace Ospp\Protocol\Crypto\Ble;

use InvalidArgumentException;

/**
 * BLE SessionCrypto — the §6.5 handshake cryptographic pipeline (ECDH P-256 key
 * agreement, HKDF key schedule, AEAD channel, StationIdentity cert verification),
 * ported from the TypeScript SDK and validated byte-identically against the SAME
 * conformance corpus (conformance/test-vectors/crypto/ble-handshake-keyschedule.json)
 * — ADR-001 cross-language lockstep. Reference (mirrored, do not reinvent): the
 * validated oracle spec tools/ble-crypto.mjs.
 *
 * Pure static methods over raw byte strings. PHP-A implements functions 1-4
 * (validatePublicKey, ecdhSharedX, transcriptHash, deriveSessionKeys); sessionProof,
 * nonce96, the AEAD channel (sealFrame/openFrame), and verifyStationIdentity are PHP-B.
 */
final class SessionCrypto
{
    /**
     * Pin 2 / §6.5.2 — public-key validation (Normative).
     *
     * Decodes a P-256 public key (compressed 33-byte 0x02/0x03 or uncompressed
     * 65-byte 0x04 SEC1 bytes), asserts it is a valid point on the curve, and
     * rejects malformed / off-curve / out-of-field / identity encodings. MUST be
     * called on every received public key before any ECDH use (es/ee). On failure
     * the caller aborts the handshake (2013 BLE_AUTH_FAILED).
     *
     * @throws InvalidArgumentException on any invalid key.
     */
    public static function validatePublicKey(string $publicKey): void
    {
        if (@openssl_pkey_get_public(self::spkiPem($publicKey)) === false) {
            throw new InvalidArgumentException('invalid BLE P-256 public key (not a valid SEC1 curve point)');
        }
    }

    /**
     * Build a P-256 SubjectPublicKeyInfo PEM from raw SEC1 point bytes (compressed
     * or uncompressed). OpenSSL validates the point (on-curve, decompresses
     * compressed form) when the key is loaded.
     */
    private static function spkiPem(string $point): string
    {
        $algId = "\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
        $bitStr = "\x03".chr(1 + strlen($point))."\x00".$point;
        $inner = $algId.$bitStr;
        $der = "\x30".chr(strlen($inner)).$inner;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
    }
}
