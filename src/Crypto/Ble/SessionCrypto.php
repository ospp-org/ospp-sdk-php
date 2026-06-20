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
    // Pin 3 key-schedule constants — VERBATIM from spec generate-ble-vectors.mjs /
    // 06-security.md §6.5. Do NOT alter — cross-language byte-identity depends on them.
    private const SALT_V2 = 'OSPP_BLE_SESSION_V2';
    private const KDF_LABEL_A2S = 'OSPP-BLE-v0.6.0-key-app-to-station';
    private const KDF_LABEL_S2A = 'OSPP-BLE-v0.6.0-key-station-to-app';
    private const SESSION_CONFIRM_LABEL = 'AuthResponse_OK';

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
     * LP(x) = U16BE(byteLength(x)) ‖ x — the single length-prefix used by the HKDF
     * `info` (Pin 3), the handshake transcript (Pin 4), and the sessionProof
     * (§6.5.1). Length-prefixing makes concatenations injective (closes finding
     * N23). Mirrors ble-crypto.mjs lp; pack('n', ...) is U16BE.
     */
    public static function lp(string $x): string
    {
        if (strlen($x) > 0xffff) {
            throw new InvalidArgumentException('lp: value exceeds the U16 length prefix');
        }

        return pack('n', strlen($x)).$x;
    }

    /**
     * Pin 4 / §6.5 — handshake transcript hash.
     *   transcriptHash = SHA-256( LP16(helloBytes) ‖ LP16(challengeBytes) )
     * Over the RAW, fully-reassembled wire octets exactly as transmitted/received —
     * MUST NOT parse the JSON and re-serialise / canonicalise it (the deliberate
     * opposite of Pin 8). Mirrors ble-crypto.mjs.
     */
    public static function transcriptHash(string $helloBytes, string $challengeBytes): string
    {
        return hash('sha256', self::lp($helloBytes).self::lp($challengeBytes), true);
    }

    /**
     * Pin 3 / §6.5 — BLE session key schedule, directional sub-keys, and key
     * confirmation. Constants verbatim (mirrors generate-ble-vectors.mjs):
     *
     *   IKM        = es ‖ ee ‖ appNonce ‖ stationNonce          (4 × 32 = 128 bytes)
     *   SessionKey = HKDF-SHA256(IKM, salt=OSPP_BLE_SESSION_V2, info=LP(deviceId)‖LP(transcriptHash), 32)
     *   k_app→stn  = HKDF-Expand(SessionKey, "OSPP-BLE-v0.6.0-key-app-to-station", 32)
     *   k_stn→app  = HKDF-Expand(SessionKey, "OSPP-BLE-v0.6.0-key-station-to-app", 32)
     *   confirm    = HMAC-SHA256(SessionKey, "AuthResponse_OK")
     *
     * `hash_hkdf(algo, ikm, length, info, salt)` does the full Extract+Expand for
     * SessionKey; the directional keys are HKDF-Expand-ONLY of SessionKey, computed
     * manually as a single 32-byte block T(1) = HMAC-SHA256(SessionKey, label ‖ 0x01).
     * The 256-bit inputs (es/ee, both nonces, transcriptHash) MUST be exactly 32 bytes.
     *
     * @return array{sessionKey: string, kAppToStation: string, kStationToApp: string, sessionKeyConfirmation: string} raw bytes (Base64 at the message layer)
     */
    public static function deriveSessionKeys(
        string $es,
        string $ee,
        string $appNonce,
        string $stationNonce,
        string $deviceId,
        string $transcriptHash,
    ): array {
        foreach (['es' => $es, 'ee' => $ee, 'appNonce' => $appNonce, 'stationNonce' => $stationNonce, 'transcriptHash' => $transcriptHash] as $name => $value) {
            if (strlen($value) !== 32) {
                throw new InvalidArgumentException("deriveSessionKeys: {$name} must be 32 bytes (got ".strlen($value).')');
            }
        }

        $ikm = $es.$ee.$appNonce.$stationNonce;
        $info = self::lp($deviceId).self::lp($transcriptHash);
        $sessionKey = hash_hkdf('sha256', $ikm, 32, $info, self::SALT_V2);

        return [
            'sessionKey' => $sessionKey,
            'kAppToStation' => self::hkdfExpand32($sessionKey, self::KDF_LABEL_A2S),
            'kStationToApp' => self::hkdfExpand32($sessionKey, self::KDF_LABEL_S2A),
            'sessionKeyConfirmation' => hash_hmac('sha256', self::SESSION_CONFIRM_LABEL, $sessionKey, true),
        ];
    }

    /** HKDF-Expand of a PRK for a single 32-byte block: T(1) = HMAC-SHA256(prk, info ‖ 0x01). */
    private static function hkdfExpand32(string $prk, string $info): string
    {
        return hash_hmac('sha256', $info."\x01", $prk, true);
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
