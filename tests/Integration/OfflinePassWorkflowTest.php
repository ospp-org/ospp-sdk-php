<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Integration;

use OneStopPay\OsppProtocol\Crypto\CanonicalJsonSerializer;
use OneStopPay\OsppProtocol\Crypto\EcdsaService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OfflinePassWorkflowTest extends TestCase
{
    private EcdsaService $ecdsa;

    private string $privateKey;

    private string $publicKey;

    private function skipIfEcdsaUnavailable(): void
    {
        if (! extension_loaded('openssl')) {
            self::markTestSkipped('OpenSSL extension is not available.');
        }

        // Verify that EC key generation works on this platform
        $config = [
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];

        $key = @openssl_pkey_new($config);

        if ($key === false) {
            self::markTestSkipped('ECDSA P-256 key generation is not supported on this platform.');
        }
    }

    protected function setUp(): void
    {
        $this->skipIfEcdsaUnavailable();

        $this->ecdsa = new EcdsaService(new CanonicalJsonSerializer());
        $keyPair = $this->ecdsa->generateKeyPair();
        $this->privateKey = $keyPair['privateKey'];
        $this->publicKey = $keyPair['publicKey'];
    }

    #[Test]
    public function sign_and_verify_offline_pass_roundtrip(): void
    {
        $passData = [
            'passId' => 'pass_550e8400-e29b-41d4-a716-446655440000',
            'userId' => 'user_660e8400-e29b-41d4-a716-446655440001',
            'stationId' => 'ST-001',
            'maxEnergy' => 50.0,
            'validUntil' => '2025-12-31T23:59:59.999Z',
            'epoch' => 42,
        ];

        $signature = $this->ecdsa->signOfflinePass($passData, $this->privateKey);

        self::assertNotEmpty($signature);
        self::assertNotFalse(base64_decode($signature, true), 'Signature should be valid base64');

        $signedPassData = array_merge($passData, [
            'signature' => $signature,
            'signatureAlgorithm' => 'ECDSA-P256-SHA256',
        ]);

        $verified = $this->ecdsa->verifyOfflinePass($signedPassData, $this->publicKey);

        self::assertTrue($verified);
    }

    #[Test]
    public function tampered_pass_data_fails_verification(): void
    {
        $passData = [
            'passId' => 'pass_550e8400-e29b-41d4-a716-446655440000',
            'userId' => 'user_660e8400-e29b-41d4-a716-446655440001',
            'stationId' => 'ST-001',
            'maxEnergy' => 50.0,
            'validUntil' => '2025-12-31T23:59:59.999Z',
            'epoch' => 42,
        ];

        $signature = $this->ecdsa->signOfflinePass($passData, $this->privateKey);

        $tamperedPassData = array_merge($passData, [
            'passId' => 'pass_TAMPERED-0000-0000-0000-000000000000',
            'signature' => $signature,
            'signatureAlgorithm' => 'ECDSA-P256-SHA256',
        ]);

        $verified = $this->ecdsa->verifyOfflinePass($tamperedPassData, $this->publicKey);

        self::assertFalse($verified);
    }

    #[Test]
    public function missing_signature_field_returns_false(): void
    {
        $passData = [
            'passId' => 'pass_550e8400-e29b-41d4-a716-446655440000',
            'userId' => 'user_660e8400-e29b-41d4-a716-446655440001',
            'stationId' => 'ST-001',
            'maxEnergy' => 50.0,
        ];

        $verified = $this->ecdsa->verifyOfflinePass($passData, $this->publicKey);

        self::assertFalse($verified);
    }

    #[Test]
    public function empty_signature_returns_false(): void
    {
        $passData = [
            'passId' => 'pass_550e8400-e29b-41d4-a716-446655440000',
            'userId' => 'user_660e8400-e29b-41d4-a716-446655440001',
            'stationId' => 'ST-001',
            'signature' => '',
            'signatureAlgorithm' => 'ECDSA-P256-SHA256',
        ];

        $verified = $this->ecdsa->verifyOfflinePass($passData, $this->publicKey);

        self::assertFalse($verified);
    }

    #[Test]
    public function canonical_serialization_is_deterministic(): void
    {
        $serializer = new CanonicalJsonSerializer();

        $passData = [
            'passId' => 'pass_550e8400-e29b-41d4-a716-446655440000',
            'userId' => 'user_660e8400-e29b-41d4-a716-446655440001',
            'stationId' => 'ST-001',
            'maxEnergy' => 50.0,
            'validUntil' => '2025-12-31T23:59:59.999Z',
            'epoch' => 42,
        ];

        $json1 = $serializer->serialize($passData);
        $json2 = $serializer->serialize($passData);

        self::assertSame($json1, $json2);

        // Also test with different key order — should produce same output
        $reordered = [
            'epoch' => 42,
            'validUntil' => '2025-12-31T23:59:59.999Z',
            'maxEnergy' => 50.0,
            'stationId' => 'ST-001',
            'userId' => 'user_660e8400-e29b-41d4-a716-446655440001',
            'passId' => 'pass_550e8400-e29b-41d4-a716-446655440000',
        ];

        $json3 = $serializer->serialize($reordered);

        self::assertSame($json1, $json3);
    }

    #[Test]
    public function signature_stripping_preserves_other_fields(): void
    {
        $passData = [
            'passId' => 'pass_550e8400-e29b-41d4-a716-446655440000',
            'userId' => 'user_660e8400-e29b-41d4-a716-446655440001',
            'stationId' => 'ST-001',
            'maxEnergy' => 50.0,
            'validUntil' => '2025-12-31T23:59:59.999Z',
            'epoch' => 42,
        ];

        $signature = $this->ecdsa->signOfflinePass($passData, $this->privateKey);

        $signedPassData = array_merge($passData, [
            'signature' => $signature,
            'signatureAlgorithm' => 'ECDSA-P256-SHA256',
        ]);

        // All original fields should be present in the signed data
        foreach ($passData as $key => $value) {
            self::assertArrayHasKey($key, $signedPassData, "Original field '{$key}' should be preserved");
            self::assertSame($value, $signedPassData[$key], "Original field '{$key}' value should be unchanged");
        }

        // Signature fields should also be present
        self::assertArrayHasKey('signature', $signedPassData);
        self::assertArrayHasKey('signatureAlgorithm', $signedPassData);
        self::assertSame($signature, $signedPassData['signature']);
        self::assertSame('ECDSA-P256-SHA256', $signedPassData['signatureAlgorithm']);
    }
}
