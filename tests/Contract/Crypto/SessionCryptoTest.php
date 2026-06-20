<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Crypto;

use DateTimeImmutable;
use InvalidArgumentException;
use Ospp\Protocol\Crypto\Ble\SessionCrypto;
use Ospp\Protocol\Crypto\Ble\StationIdentityException;
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

    // ───────────────────────── Function 3: transcriptHash + lp (Pin 4 / §6.5) ─────────────────────────

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $keys
     */
    #[Test]
    #[DataProvider('scenarioProvider')]
    public function transcript_hash_reproduces_vector_from_raw_wire_octets(array $scenario, array $keys): void
    {
        // Pin 4: hash the RAW reassembled wire bytes (wireBase64) — NOT a re-serialised JSON form.
        $hello = base64_decode($scenario['hello']['wireBase64'], true);
        $challenge = base64_decode($scenario['challenge']['wireBase64'], true);
        self::assertSame(
            $scenario['transcript']['transcriptHashHex'],
            bin2hex(SessionCrypto::transcriptHash($hello, $challenge)),
        );
    }

    #[Test]
    public function transcript_hash_bite_byte_flip_changes_the_hash(): void
    {
        $full = self::vector()['scenarios'][0];
        $hello = base64_decode($full['hello']['wireBase64'], true);
        $challenge = base64_decode($full['challenge']['wireBase64'], true);
        self::assertSame($full['transcript']['transcriptHashHex'], bin2hex(SessionCrypto::transcriptHash($hello, $challenge))); // sanity
        $hello[0] = chr(ord($hello[0]) ^ 0x01);
        self::assertNotSame($full['transcript']['transcriptHashHex'], bin2hex(SessionCrypto::transcriptHash($hello, $challenge)));
    }

    // ───────────────────────── Function 4: deriveSessionKeys (Pin 3 / §6.5) ─────────────────────────

    /**
     * @param  array<string, mixed>  $scenario
     * @return array{es: string, ee: string, appNonce: string, stationNonce: string, deviceId: string, transcriptHash: string}
     */
    private static function deriveParams(array $scenario): array
    {
        return [
            'es' => hex2bin($scenario['ecdh']['esHex']),
            'ee' => hex2bin($scenario['ecdh']['eeHex']),
            'appNonce' => base64_decode($scenario['hello']['message']['appNonce'], true),
            'stationNonce' => base64_decode($scenario['challenge']['message']['stationNonce'], true),
            'deviceId' => $scenario['hello']['message']['deviceId'],
            'transcriptHash' => hex2bin($scenario['transcript']['transcriptHashHex']),
        ];
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $keys
     */
    #[Test]
    #[DataProvider('scenarioProvider')]
    public function derive_session_keys_reproduces_all_outputs(array $scenario, array $keys): void
    {
        $p = self::deriveParams($scenario);
        $r = SessionCrypto::deriveSessionKeys($p['es'], $p['ee'], $p['appNonce'], $p['stationNonce'], $p['deviceId'], $p['transcriptHash']);
        $ks = $scenario['keySchedule'];
        self::assertSame($ks['sessionKeyHex'], bin2hex($r['sessionKey']), 'SessionKey');
        self::assertSame($ks['kAppToStationHex'], bin2hex($r['kAppToStation']), 'k_app_to_station');
        self::assertSame($ks['kStationToAppHex'], bin2hex($r['kStationToApp']), 'k_station_to_app');
        self::assertSame($ks['sessionKeyConfirmationBase64'], base64_encode($r['sessionKeyConfirmation']), 'sessionKeyConfirmation');
    }

    #[Test]
    public function derive_session_keys_bite_mutated_es_and_deviceId_diverge(): void
    {
        $full = self::vector()['scenarios'][0];
        $p = self::deriveParams($full);
        $base = SessionCrypto::deriveSessionKeys($p['es'], $p['ee'], $p['appNonce'], $p['stationNonce'], $p['deviceId'], $p['transcriptHash']);
        self::assertSame($full['keySchedule']['sessionKeyHex'], bin2hex($base['sessionKey'])); // sanity
        // mutate es -> different SessionKey AND directional keys
        $mes = $p['es'];
        $mes[0] = chr(ord($mes[0]) ^ 0x01);
        $r1 = SessionCrypto::deriveSessionKeys($mes, $p['ee'], $p['appNonce'], $p['stationNonce'], $p['deviceId'], $p['transcriptHash']);
        self::assertNotSame($full['keySchedule']['sessionKeyHex'], bin2hex($r1['sessionKey']));
        self::assertNotSame($full['keySchedule']['kAppToStationHex'], bin2hex($r1['kAppToStation']));
        // mutate deviceId (Pin 3 info binding, injective LP) -> different SessionKey
        $r2 = SessionCrypto::deriveSessionKeys($p['es'], $p['ee'], $p['appNonce'], $p['stationNonce'], $p['deviceId'].'x', $p['transcriptHash']);
        self::assertNotSame($full['keySchedule']['sessionKeyHex'], bin2hex($r2['sessionKey']));
    }

    // ───────────────────────── Function 5: sessionProof (§6.5.1 / ble-handshake §4.1) ─────────────────────────

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $keys
     */
    #[Test]
    #[DataProvider('scenarioProvider')]
    public function session_proof_reproduces_vector(array $scenario, array $keys): void
    {
        // The NEW §4.1 form (LP / 3-input / decimal-counter / Base64) — replacing the retired
        // §6.5.1 hex/4-input shape. Reproduction proves decimal(counter)=String(counter), not U64BE.
        $sp = $scenario['sessionProof'];
        $sk = hex2bin($scenario['keySchedule']['sessionKeyHex']);
        self::assertSame($sp['sessionProofBase64'], base64_encode(SessionCrypto::sessionProof($sk, $sp['passId'], $sp['counter'])));
    }

    #[Test]
    public function session_proof_bite_counter_and_passId(): void
    {
        $full = self::vector()['scenarios'][0];
        $sp = $full['sessionProof'];
        $sk = hex2bin($full['keySchedule']['sessionKeyHex']);
        self::assertSame($sp['sessionProofBase64'], base64_encode(SessionCrypto::sessionProof($sk, $sp['passId'], $sp['counter']))); // sanity
        self::assertNotSame($sp['sessionProofBase64'], base64_encode(SessionCrypto::sessionProof($sk, $sp['passId'], $sp['counter'] + 1)));
        self::assertNotSame($sp['sessionProofBase64'], base64_encode(SessionCrypto::sessionProof($sk, $sp['passId'].'x', $sp['counter'])));
    }

    // ───────────────────────── Function 6: nonce96 (Pin 5 / §6.5.3) ─────────────────────────

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $keys
     */
    #[Test]
    #[DataProvider('scenarioProvider')]
    public function nonce96_reproduces_vector(array $scenario, array $keys): void
    {
        $count = 0;
        foreach ($scenario['aeadFrames'] as $frame) {
            self::assertSame($frame['nonce96Hex'], bin2hex(SessionCrypto::nonce96($frame['counter'])));
            $count++;
        }
        self::assertGreaterThan(0, $count);
    }

    #[Test]
    public function nonce96_structure_and_bite(): void
    {
        self::assertSame('000000000000000000000001', bin2hex(SessionCrypto::nonce96(1)));
        self::assertSame('000000000000000000000102', bin2hex(SessionCrypto::nonce96(258))); // 0x102
        self::assertSame(12, strlen(SessionCrypto::nonce96(0)));
        self::assertNotSame(bin2hex(SessionCrypto::nonce96(5)), bin2hex(SessionCrypto::nonce96(6)));
    }

    // ───────────────────────── Function 7: sealFrame / openFrame (Pin 6+7 / §6.5.3) ─────────────────────────

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $keys
     */
    #[Test]
    #[DataProvider('scenarioProvider')]
    public function seal_frame_reproduces_vector_ct_and_round_trips(array $scenario, array $keys): void
    {
        $aad = hex2bin($scenario['transcript']['transcriptHashHex']); // Pin 7: AAD = transcriptHash
        $ks = $scenario['keySchedule'];
        foreach ($scenario['aeadFrames'] as $frame) {
            $key = hex2bin($frame['keyRef'] === 'kAppToStation' ? $ks['kAppToStationHex'] : $ks['kStationToAppHex']);
            $sealed = SessionCrypto::sealFrame($key, $frame['counter'], $frame['plaintextUtf8'], $aad);
            self::assertSame($frame['frame']['ct'], base64_encode($sealed), "ct {$frame['direction']}");
            self::assertSame($frame['plaintextUtf8'], SessionCrypto::openFrame($key, $frame['counter'], $sealed, $aad), "round-trip {$frame['direction']}");
        }
    }

    #[Test]
    public function open_frame_rejects_wrong_aad_and_tampered_tag(): void
    {
        $full = self::vector()['scenarios'][0];
        $frame = $full['aeadFrames'][0];
        $key = hex2bin($full['keySchedule']['kAppToStationHex']);
        $aad = hex2bin($full['transcript']['transcriptHashHex']);
        $sealed = SessionCrypto::sealFrame($key, $frame['counter'], $frame['plaintextUtf8'], $aad);
        // wrong AAD binds the frame to the handshake (Pin 7) -> open must fail
        self::assertFalse(SessionCrypto::openFrame($key, $frame['counter'], $sealed, str_repeat("\x00", 32)));
        // tampered tag -> open must fail (AEAD integrity)
        $tampered = $sealed;
        $tampered[strlen($tampered) - 1] = chr(ord($tampered[strlen($tampered) - 1]) ^ 0x01);
        self::assertFalse(SessionCrypto::openFrame($key, $frame['counter'], $tampered, $aad));
    }

    #[Test]
    public function seal_frame_bite_mutated_plaintext_diverges(): void
    {
        $full = self::vector()['scenarios'][0];
        $frame = $full['aeadFrames'][0];
        $key = hex2bin($full['keySchedule']['kAppToStationHex']);
        $aad = hex2bin($full['transcript']['transcriptHashHex']);
        self::assertSame($frame['frame']['ct'], base64_encode(SessionCrypto::sealFrame($key, $frame['counter'], $frame['plaintextUtf8'], $aad))); // sanity
        self::assertNotSame($frame['frame']['ct'], base64_encode(SessionCrypto::sealFrame($key, $frame['counter'], $frame['plaintextUtf8'].'x', $aad)));
    }

    // ───────────────────────── Function 8: verifyStationIdentity (§6.5.2 / Pin 8) ─────────────────────────

    /** @return array<string, mixed> */
    private static function validCert(): array
    {
        return self::vector()['stationIdentity']['cert'];
    }

    private static function serverPubPem(): string
    {
        $pem = file_get_contents(__DIR__.'/fixtures/server-test-pub.pem');
        self::assertNotFalse($pem);

        return $pem;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function certWith(array $overrides): array
    {
        return array_merge(self::validCert(), $overrides);
    }

    #[Test]
    public function verify_station_identity_accepts_valid_cert(): void
    {
        $cert = SessionCrypto::verifyStationIdentity(self::validCert(), self::serverPubPem());
        self::assertSame(self::validCert()['stationId'], $cert['stationId']);
    }

    #[Test]
    public function verify_station_identity_accepts_matching_station_id(): void
    {
        $cert = self::validCert();
        $r = SessionCrypto::verifyStationIdentity($cert, self::serverPubPem(), $cert['stationId']);
        self::assertSame($cert['stationId'], $r['stationId']);
    }

    #[Test]
    public function verify_station_identity_rejects_wrong_public_key(): void
    {
        $wrong = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertNotFalse($wrong);
        $wrongPem = openssl_pkey_get_details($wrong)['key'];
        $this->expectException(StationIdentityException::class);
        SessionCrypto::verifyStationIdentity(self::validCert(), $wrongPem);
    }

    #[Test]
    public function verify_station_identity_rejects_wrong_algorithm(): void
    {
        $this->expectException(StationIdentityException::class);
        SessionCrypto::verifyStationIdentity(self::certWith(['signatureAlgorithm' => 'ECDSA-P384-SHA384']), self::serverPubPem());
    }

    #[Test]
    public function verify_station_identity_rejects_malformed_pubkey(): void
    {
        $this->expectException(StationIdentityException::class);
        SessionCrypto::verifyStationIdentity(self::certWith(['stationPubKey' => 'too-short']), self::serverPubPem());
    }

    #[Test]
    public function verify_station_identity_rejects_invalid_validity_window(): void
    {
        $cert = self::validCert();
        $this->expectException(StationIdentityException::class);
        SessionCrypto::verifyStationIdentity(self::certWith(['expiresAt' => $cert['issuedAt']]), self::serverPubPem());
    }

    #[Test]
    public function verify_station_identity_rejects_tampered_signature(): void
    {
        $cert = self::validCert();
        $sig = base64_decode($cert['signature'], true);
        $sig[strlen($sig) - 1] = chr(ord($sig[strlen($sig) - 1]) ^ 0x01);
        $this->expectException(StationIdentityException::class);
        SessionCrypto::verifyStationIdentity(self::certWith(['signature' => base64_encode($sig)]), self::serverPubPem());
    }

    #[Test]
    public function verify_station_identity_rejects_station_id_mismatch(): void
    {
        $this->expectException(StationIdentityException::class);
        SessionCrypto::verifyStationIdentity(self::validCert(), self::serverPubPem(), 'stn_wrong');
    }

    #[Test]
    public function verify_station_identity_freshness_is_runtime_gated(): void
    {
        // The vector cert is timeless; absolute freshness (now < expiresAt) is a RUNTIME gate
        // exercised only when a clock is supplied — real freshness lands at B5.
        $cert = self::validCert();
        $expires = (new DateTimeImmutable($cert['expiresAt']))->getTimestamp();
        $issued = (new DateTimeImmutable($cert['issuedAt']))->getTimestamp();
        $threw = false;
        try {
            SessionCrypto::verifyStationIdentity($cert, self::serverPubPem(), null, $expires + 1);
        } catch (StationIdentityException) {
            $threw = true;
        }
        self::assertTrue($threw, 'expected expiry rejection when now >= expiresAt');
        $ok = SessionCrypto::verifyStationIdentity($cert, self::serverPubPem(), null, $issued + 1000);
        self::assertSame($cert['stationId'], $ok['stationId']);
    }

    #[Test]
    public function verify_station_identity_throws_2013_on_failure(): void
    {
        try {
            SessionCrypto::verifyStationIdentity(self::certWith(['signatureAlgorithm' => 'X']), self::serverPubPem());
            self::fail('expected StationIdentityException');
        } catch (StationIdentityException $e) {
            self::assertSame(2013, $e->getCode());
        }
    }

    #[Test]
    public function verify_station_identity_bite_tampered_body_fails(): void
    {
        $cert = self::validCert();
        $this->expectException(StationIdentityException::class);
        SessionCrypto::verifyStationIdentity(self::certWith(['stationId' => $cert['stationId'].'x']), self::serverPubPem());
    }
}
