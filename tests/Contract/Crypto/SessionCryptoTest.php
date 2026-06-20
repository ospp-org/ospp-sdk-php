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

    private const SCENARIO_KEYS = [
        'full' => ['appEph' => 'appEphemeralFull', 'stationEph' => 'stationEphemeralFull'],
        'minimal' => ['appEph' => 'appEphemeralMinimal', 'stationEph' => 'stationEphemeralMinimal'],
    ];

    /** @return array<string, array{0: array<string, mixed>, 1: array<string, mixed>}> */
    public static function scenarioProvider(): array
    {
        $v = self::vector();
        $cases = [];
        foreach ($v['scenarios'] as $s) {
            $cases[$s['scenario']] = [$s, $v['keys']];
        }

        return $cases;
    }

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

    // ───────────────────────── Function 2: ecdhSharedX + leftPad32 (Pin 1 / §6.5) ─────────────────────────

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $keys
     */
    #[Test]
    #[DataProvider('scenarioProvider')]
    public function ecdh_es_reproduces_vector_both_directions(array $scenario, array $keys): void
    {
        $map = self::SCENARIO_KEYS[$scenario['scenario']];
        $appPriv = hex2bin($keys[$map['appEph']]['privateKeyHex']);
        $appPub = base64_decode($keys[$map['appEph']]['publicKeyCompressedBase64'], true);
        $statPriv = hex2bin($keys['stationStatic']['privateKeyHex']);
        $statPub = base64_decode($keys['stationStatic']['publicKeyCompressedBase64'], true);
        // es = ECDH(appEphemeralPriv, stationStaticPub) — app side == station side (interop crux == TS)
        self::assertSame($scenario['ecdh']['esHex'], bin2hex(SessionCrypto::ecdhSharedX($appPriv, $statPub)));
        self::assertSame($scenario['ecdh']['esHex'], bin2hex(SessionCrypto::ecdhSharedX($statPriv, $appPub)));
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $keys
     */
    #[Test]
    #[DataProvider('scenarioProvider')]
    public function ecdh_ee_reproduces_vector_both_directions(array $scenario, array $keys): void
    {
        $map = self::SCENARIO_KEYS[$scenario['scenario']];
        $appPriv = hex2bin($keys[$map['appEph']]['privateKeyHex']);
        $appPub = base64_decode($keys[$map['appEph']]['publicKeyCompressedBase64'], true);
        $statEphPriv = hex2bin($keys[$map['stationEph']]['privateKeyHex']);
        $statEphPub = base64_decode($keys[$map['stationEph']]['publicKeyCompressedBase64'], true);
        self::assertSame($scenario['ecdh']['eeHex'], bin2hex(SessionCrypto::ecdhSharedX($appPriv, $statEphPub)));
        self::assertSame($scenario['ecdh']['eeHex'], bin2hex(SessionCrypto::ecdhSharedX($statEphPriv, $appPub)));
    }

    #[Test]
    public function ecdh_output_is_exactly_32_bytes(): void
    {
        $keys = self::vector()['keys'];
        $x = SessionCrypto::ecdhSharedX(
            hex2bin($keys['appEphemeralFull']['privateKeyHex']),
            base64_decode($keys['stationStatic']['publicKeyCompressedBase64'], true),
        );
        self::assertSame(32, strlen($x));
    }

    #[Test]
    public function ecdh_bite_mutated_private_key_diverges(): void
    {
        $v = self::vector();
        $keys = $v['keys'];
        $full = $v['scenarios'][0];
        $appPriv = hex2bin($keys['appEphemeralFull']['privateKeyHex']);
        $statPub = base64_decode($keys['stationStatic']['publicKeyCompressedBase64'], true);
        self::assertSame($full['ecdh']['esHex'], bin2hex(SessionCrypto::ecdhSharedX($appPriv, $statPub))); // sanity
        $mutated = $appPriv;
        $last = strlen($mutated) - 1;
        $mutated[$last] = chr(ord($mutated[$last]) ^ 0x01);
        self::assertNotSame($full['ecdh']['esHex'], bin2hex(SessionCrypto::ecdhSharedX($mutated, $statPub)));
    }

    #[Test]
    public function leftPad32_is_unconditional(): void
    {
        // no-op on a full-width 32-byte input
        self::assertSame(str_repeat('ff', 32), bin2hex(SessionCrypto::leftPad32(hex2bin(str_repeat('ff', 32)))));
        // left-pads a short (high-zero-byte) value — the ~1/256 EC-scalar parity case (Pin 1)
        $padded = SessionCrypto::leftPad32(hex2bin(str_repeat('ab', 31)));
        self::assertSame(32, strlen($padded));
        self::assertSame('00'.str_repeat('ab', 31), bin2hex($padded));
    }

    #[Test]
    public function leftPad32_rejects_over_width_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SessionCrypto::leftPad32(hex2bin(str_repeat('ab', 33)));
    }
}
