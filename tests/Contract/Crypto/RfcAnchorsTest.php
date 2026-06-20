<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Crypto;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RFC anti-circularity anchors for the BLE SessionCrypto primitives (PHP side).
 *
 * Proves PHP's primitives — OpenSSL ECDH P-256 (openssl_pkey_derive), HKDF-SHA256
 * (hash_hkdf), and ext-sodium ChaCha20-Poly1305 IETF — reproduce the PUBLISHED RFC
 * test vectors byte-for-byte: RFC 5903 §8.1, RFC 5869 A.1/A.2, RFC 8439 §2.8.2.
 *
 * Why (the EC-scalar lesson): "byte-identical to the spec oracle / to sdk-ts" only
 * means "correct" once the primitives are independently anchored on an external
 * truth we do not control. Expected values are loaded from the vendored corpus
 * (rfc-primitive-anchors.json, byte-identical to spec + sdk-ts); the RFC inputs are
 * the published constants, mirroring spec tools/ble-crypto.mjs. This test is
 * deliberately independent of the SessionCrypto class (inline primitives).
 */
final class RfcAnchorsTest extends TestCase
{
    /** @return array<string, array<string, mixed>> */
    private static function anchors(): array
    {
        $json = file_get_contents(__DIR__.'/fixtures/rfc-primitive-anchors.json');
        self::assertNotFalse($json);
        /** @var array{anchors: list<array<string, mixed>>} $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $byRfc = [];
        foreach ($decoded['anchors'] as $a) {
            $byRfc[$a['rfc']] = $a;
        }

        return $byRfc;
    }

    private static function seq(int $start, int $end): string
    {
        $s = '';
        for ($i = $start; $i <= $end; $i++) {
            $s .= chr($i);
        }

        return $s;
    }

    private static function leftPad32(string $b): string
    {
        return str_pad($b, 32, "\x00", STR_PAD_LEFT);
    }

    // Minimal P-256 SEC1 private key (scalar only) + SPKI public key DER → PEM.
    private static function pemPriv(string $scalar32): string
    {
        $der = "\x30\x31\x02\x01\x01\x04\x20".$scalar32."\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

        return "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
    }

    private static function pemPub(string $point): string
    {
        $algId = "\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
        $bitStr = "\x03".chr(1 + strlen($point))."\x00".$point;
        $inner = $algId.$bitStr;
        $der = "\x30".chr(strlen($inner)).$inner;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    #[Test]
    public function ecdh_p256_reproduces_rfc_5903(): void
    {
        $a = self::anchors()['RFC 5903 §8.1'];
        $i = hex2bin('C88F01F510D9AC3F70A292DAA2316DE544E9AAB8AFE84049C62A9C57862D1433');
        $gr = hex2bin('04'.'D12DFB5289C8D4F81208B70270398C342296970A0BCCB74C736FC7554494BF63'.'56FBF3CA366CC23E8157854C13C58D6AAC23F046ADA30F8353E74F33039872AB');
        $priv = openssl_pkey_get_private(self::pemPriv($i));
        $pub = openssl_pkey_get_public(self::pemPub($gr));
        self::assertNotFalse($priv, 'priv key load: '.openssl_error_string());
        self::assertNotFalse($pub, 'pub key load: '.openssl_error_string());
        $x = bin2hex(self::leftPad32(openssl_pkey_derive($pub, $priv)));
        self::assertSame($a['sharedSecretX'], $x, 'OpenSSL ECDH P-256 (Pin 1 X) != RFC 5903 §8.1');
    }

    #[Test]
    public function hkdf_sha256_reproduces_rfc_5869(): void
    {
        $a1 = self::anchors()['RFC 5869 A.1'];
        $ikm = str_repeat("\x0b", 22);
        $salt = hex2bin('000102030405060708090a0b0c');
        $info = hex2bin('f0f1f2f3f4f5f6f7f8f9');
        self::assertSame($a1['prk'], bin2hex(hash_hmac('sha256', $ikm, $salt, true)), 'RFC 5869 A.1 PRK');
        self::assertSame($a1['okm'], bin2hex(hash_hkdf('sha256', $ikm, 42, $info, $salt)), 'RFC 5869 A.1 OKM');

        $a2 = self::anchors()['RFC 5869 A.2'];
        $ikm2 = self::seq(0x00, 0x4f);
        $salt2 = self::seq(0x60, 0xaf);
        $info2 = self::seq(0xb0, 0xff);
        self::assertSame($a2['prk'], bin2hex(hash_hmac('sha256', $ikm2, $salt2, true)), 'RFC 5869 A.2 PRK');
        self::assertSame($a2['okm'], bin2hex(hash_hkdf('sha256', $ikm2, 82, $info2, $salt2)), 'RFC 5869 A.2 OKM');
    }

    #[Test]
    public function chacha20poly1305_ietf_reproduces_rfc_8439(): void
    {
        $a = self::anchors()['RFC 8439 §2.8.2'];
        $key = self::seq(0x80, 0x9f);
        $nonce = hex2bin('070000004041424344454647');
        $aad = hex2bin('50515253c0c1c2c3c4c5c6c7');
        $plaintext = "Ladies and Gentlemen of the class of '99: If I could offer you only one tip for the future, sunscreen would be it.";
        $sealed = sodium_crypto_aead_chacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $key);
        self::assertSame($a['ciphertext'], bin2hex(substr($sealed, 0, -16)), 'RFC 8439 ciphertext');
        self::assertSame($a['tag'], bin2hex(substr($sealed, -16)), 'RFC 8439 Poly1305 tag');
        $opened = sodium_crypto_aead_chacha20poly1305_ietf_decrypt($sealed, $aad, $nonce, $key);
        self::assertSame($plaintext, $opened, 'RFC 8439 round-trip');
    }
}
