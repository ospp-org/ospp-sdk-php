<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Contract\Crypto;

use OneStopPay\OsppProtocol\Crypto\CanonicalJsonSerializer;
use OneStopPay\OsppProtocol\Crypto\EcdsaService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EcdsaServiceContractTest extends TestCase
{
    private EcdsaService $service;

    protected function setUp(): void
    {
        $this->service = new EcdsaService(new CanonicalJsonSerializer());
    }

    private function skipIfEcdsaUnavailable(): void
    {
        try {
            $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
            if ($key === false) {
                self::markTestSkipped('ECDSA P-256 unavailable');
            }
        } catch (\Throwable) {
            self::markTestSkipped('ECDSA P-256 unavailable');
        }
    }

    #[Test]
    public function generated_key_pair_uses_P256_curve(): void
    {
        $this->skipIfEcdsaUnavailable();

        $keyPair = $this->service->generateKeyPair();

        $privateKey = openssl_pkey_get_private($keyPair['privateKey']);
        self::assertNotFalse($privateKey, 'Private key should be loadable');

        $details = openssl_pkey_get_details($privateKey);
        self::assertNotFalse($details, 'Key details should be retrievable');
        self::assertSame('prime256v1', $details['ec']['curve_name']);
    }

    #[Test]
    public function signature_is_DER_encoded_base64(): void
    {
        $this->skipIfEcdsaUnavailable();

        $keyPair = $this->service->generateKeyPair();
        $signatureBase64 = $this->service->sign('test data', $keyPair['privateKey']);

        $decoded = base64_decode($signatureBase64, true);
        self::assertNotFalse($decoded, 'Signature should be valid base64');
        self::assertNotEmpty($decoded, 'Decoded signature should not be empty');

        // DER-encoded signatures start with 0x30 (SEQUENCE tag)
        self::assertSame(0x30, ord($decoded[0]), 'First byte should be 0x30 (DER SEQUENCE)');
    }

    #[Test]
    public function signOfflinePass_strips_signature_and_signatureAlgorithm(): void
    {
        $this->skipIfEcdsaUnavailable();

        $keyPair = $this->service->generateKeyPair();

        $passDataWithSig = [
            'userId' => 'user-1',
            'stationId' => 'ST-001',
            'signature' => 'old-signature',
            'signatureAlgorithm' => 'ES256',
        ];

        $passDataWithoutSig = [
            'userId' => 'user-1',
            'stationId' => 'ST-001',
        ];

        $sig1 = $this->service->signOfflinePass($passDataWithSig, $keyPair['privateKey']);
        $sig2 = $this->service->signOfflinePass($passDataWithoutSig, $keyPair['privateKey']);

        // Both should produce the same signature since signature/signatureAlgorithm are stripped
        // Note: ECDSA is non-deterministic, so we verify that both sign the same canonical data
        // by checking that each signature verifies the same content
        self::assertTrue(
            $this->service->verify(
                (new CanonicalJsonSerializer())->serialize($passDataWithoutSig),
                $sig1,
                $keyPair['publicKey'],
            ),
            'Signature from pass with signature fields should verify against stripped data',
        );

        self::assertTrue(
            $this->service->verify(
                (new CanonicalJsonSerializer())->serialize($passDataWithoutSig),
                $sig2,
                $keyPair['publicKey'],
            ),
            'Signature from pass without signature fields should verify against stripped data',
        );
    }

    #[Test]
    public function verifyOfflinePass_returns_false_for_non_string_signature(): void
    {
        $this->skipIfEcdsaUnavailable();

        $keyPair = $this->service->generateKeyPair();

        $passWithIntSignature = ['userId' => 'user-1', 'signature' => 123];
        self::assertFalse($this->service->verifyOfflinePass($passWithIntSignature, $keyPair['publicKey']));

        $passWithNullSignature = ['userId' => 'user-1', 'signature' => null];
        self::assertFalse($this->service->verifyOfflinePass($passWithNullSignature, $keyPair['publicKey']));
    }

    #[Test]
    public function sign_rejects_RSA_private_key(): void
    {
        $this->skipIfEcdsaUnavailable();

        $rsaKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        self::assertNotFalse($rsaKey, 'RSA key generation should succeed');

        $rsaPem = '';
        openssl_pkey_export($rsaKey, $rsaPem);

        $this->expectException(RuntimeException::class);
        $this->service->sign('test data', $rsaPem);
    }

    #[Test]
    public function sign_with_empty_string_data_succeeds(): void
    {
        $this->skipIfEcdsaUnavailable();

        $keyPair = $this->service->generateKeyPair();
        $signature = $this->service->sign('', $keyPair['privateKey']);

        self::assertNotEmpty($signature);
        // Should be valid base64
        self::assertNotFalse(base64_decode($signature, true));
    }

    #[Test]
    public function corrupted_signature_returns_false(): void
    {
        $this->skipIfEcdsaUnavailable();

        $keyPair = $this->service->generateKeyPair();
        $data = 'important data to sign';

        $signatureBase64 = $this->service->sign($data, $keyPair['privateKey']);
        $signatureBytes = base64_decode($signatureBase64, true);
        self::assertNotFalse($signatureBytes);

        // Flip one byte in the signature
        $corruptedBytes = $signatureBytes;
        $lastIndex = strlen($corruptedBytes) - 1;
        $corruptedBytes[$lastIndex] = chr(ord($corruptedBytes[$lastIndex]) ^ 0xFF);
        $corruptedBase64 = base64_encode($corruptedBytes);

        self::assertFalse($this->service->verify($data, $corruptedBase64, $keyPair['publicKey']));
    }

    #[Test]
    public function cross_key_pair_verification_fails(): void
    {
        $this->skipIfEcdsaUnavailable();

        $keyPair1 = $this->service->generateKeyPair();
        $keyPair2 = $this->service->generateKeyPair();

        $data = 'cross-key test data';
        $signatureFromPair1 = $this->service->sign($data, $keyPair1['privateKey']);

        self::assertFalse(
            $this->service->verify($data, $signatureFromPair1, $keyPair2['publicKey']),
            'Signature from key pair 1 should not verify with key pair 2 public key',
        );
    }

    #[Test]
    public function sign_throws_RuntimeException_with_invalid_key_string(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service->sign('data', 'not-a-key');
    }
}
