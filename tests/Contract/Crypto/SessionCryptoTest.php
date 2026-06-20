<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Crypto;

use InvalidArgumentException;
use Ospp\Protocol\Crypto\Ble\SessionCrypto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * BLE SessionCrypto — validated byte-identically against the SAME conformance
 * corpus (ble-handshake-keyschedule.json) that sdk-ts is pinned against. This is
 * the cross-language LOCKSTEP judge (ADR-001): every value PHP produces MUST equal
 * the vector == the value sdk-ts already reproduces — byte-identical or it is a
 * lockstep bug, not acceptable variation. Reference: spec tools/ble-crypto.mjs.
 * PHP-A covers functions 1-4 (validatePublicKey, ecdhSharedX, transcriptHash,
 * deriveSessionKeys); the rest is PHP-B.
 */
final class SessionCryptoTest extends TestCase
{
    private const KEY_LABELS = [
        'stationStatic', 'appEphemeralFull', 'stationEphemeralFull',
        'appEphemeralMinimal', 'stationEphemeralMinimal',
    ];

    /** @return array<string, mixed> */
    private static function vector(): array
    {
        $json = file_get_contents(__DIR__.'/fixtures/ble-handshake-keyschedule.json');
        self::assertNotFalse($json);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    // ───────────────────────── Function 1: validatePublicKey (Pin 2 / §6.5.2) ─────────────────────────

    #[Test]
    public function validate_public_key_accepts_every_corpus_key(): void
    {
        $keys = self::vector()['keys'];
        $count = 0;
        foreach (self::KEY_LABELS as $label) {
            SessionCrypto::validatePublicKey(base64_decode($keys[$label]['publicKeyCompressedBase64'], true));
            SessionCrypto::validatePublicKey(hex2bin($keys[$label]['publicKeyUncompressedHex']));
            $count += 2;
        }
        self::assertSame(10, $count, 'all corpus keys (compressed + uncompressed) validated without exception');
    }

    /** @return array<string, array{0: string}> */
    public static function badKeyProvider(): array
    {
        return [
            'invalid prefix 0x05' => ["\x05".str_repeat("\xff", 32)],
            'off-curve 0x02|0xff*32' => ["\x02".str_repeat("\xff", 32)],
            'truncated (20 bytes)' => [str_repeat("\x02", 20)],
            'empty' => [''],
        ];
    }

    #[Test]
    #[DataProvider('badKeyProvider')]
    public function validate_public_key_rejects_bad_keys(string $bad): void
    {
        $this->expectException(InvalidArgumentException::class);
        SessionCrypto::validatePublicKey($bad);
    }

    #[Test]
    public function validate_public_key_bite_byte_flips_in_a_valid_key_are_rejected(): void
    {
        $valid = base64_decode(self::vector()['keys']['appEphemeralFull']['publicKeyCompressedBase64'], true);
        SessionCrypto::validatePublicKey($valid); // sanity: the real key is accepted
        $rejected = 0;
        for ($i = 1; $i < strlen($valid); $i++) {
            $m = $valid;
            $m[$i] = chr(ord($m[$i]) ^ 0xff);
            try {
                SessionCrypto::validatePublicKey($m);
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }
        self::assertGreaterThan(0, $rejected, 'the curve/field check must catch mutations (a no-op validator rejects 0)');
    }
}
