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
     * Pin 1 — left-pad a big-endian byte string to exactly 32 bytes, applied
     * UNCONDITIONALLY to every ECDH shared-secret X (06-security.md §6.5 Pin 1):
     * a no-op at full width, a correction when a backend strips leading zero bytes.
     *
     * Empirical note: PHP's openssl_pkey_derive returns the X coordinate at fixed
     * 32-byte width and does NOT strip (8000-iteration brute force found no short
     * output). The ~1/256 leading-zero strip is the openssl_pkey_get_details
     * EC-scalar path (the 0.5.7 class fixed in EcdsaService), not the ECDH derive
     * path. The left-pad is kept unconditional per the spec rule, for cross-backend
     * byte-parity (mbedTLS / other OpenSSL builds may strip) and is unit-tested on a
     * short input.
     */
    public static function leftPad32(string $bytes): string
    {
        if (strlen($bytes) > 32) {
            throw new InvalidArgumentException('leftPad32: input '.strlen($bytes).' > 32 bytes');
        }

        return str_pad($bytes, 32, "\x00", STR_PAD_LEFT);
    }

    /**
     * Pin 1 / §6.5 — ECDH P-256 shared secret = X-coordinate, big-endian, 32 bytes,
     * zero-left-padded. Mirrors ble-crypto.mjs ecdhSharedX (OpenSSL backend).
     *
     * @param  string  $privateScalar  32-byte raw private scalar.
     * @param  string  $peerPublicKey  peer SEC1 public-key bytes (compressed or uncompressed);
     *                                 validate it first with validatePublicKey().
     */
    public static function ecdhSharedX(string $privateScalar, string $peerPublicKey): string
    {
        $priv = openssl_pkey_get_private(self::ecPrivatePem($privateScalar));
        $pub = openssl_pkey_get_public(self::spkiPem($peerPublicKey));
        if ($priv === false || $pub === false) {
            throw new InvalidArgumentException('ecdhSharedX: invalid key material');
        }
        $secret = openssl_pkey_derive($pub, $priv);
        if ($secret === false) {
            throw new InvalidArgumentException('ecdhSharedX: openssl_pkey_derive failed: '.openssl_error_string());
        }

        return self::leftPad32($secret);
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

    /** Build a P-256 SEC1 EC private key PEM from a raw 32-byte scalar (left-padded defensively). */
    private static function ecPrivatePem(string $scalar): string
    {
        $scalar = self::leftPad32($scalar); // exactly 32 bytes for the OCTET STRING in the template
        $der = "\x30\x31\x02\x01\x01\x04\x20".$scalar."\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

        return "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
    }
}
