<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Crypto;

use Ospp\Protocol\Crypto\CanonicalJsonSerializer;
use Ospp\Protocol\Crypto\EcdsaService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EcdsaServiceTest extends TestCase
{
    private EcdsaService $ecdsa;

    protected function setUp(): void
    {
        $this->ecdsa = new EcdsaService(new CanonicalJsonSerializer());
    }

    /**
     * Generate an EC key pair, skipping the test if the environment does not
     * support ECDSA P-256 key generation (e.g. missing OpenSSL config).
     *
     * @return array{privateKey: string, publicKey: string}
     */
    private function requireKeyPair(): array
    {
        try {
            return $this->ecdsa->generateKeyPair();
        } catch (RuntimeException $e) {
            self::markTestSkipped('ECDSA P-256 key generation unavailable: ' . $e->getMessage());
        }
    }

    #[Test]
    public function generateKeyPairReturnsPemEncodedKeys(): void
    {
        $keyPair = $this->requireKeyPair();

        self::assertArrayHasKey('privateKey', $keyPair);
        self::assertArrayHasKey('publicKey', $keyPair);

        self::assertTrue(
            str_starts_with($keyPair['privateKey'], '-----BEGIN EC PRIVATE KEY-----')
            || str_starts_with($keyPair['privateKey'], '-----BEGIN PRIVATE KEY-----'),
            'Private key must be PEM-encoded (SEC1 or PKCS#8 format)',
        );
        self::assertTrue(
            str_contains($keyPair['privateKey'], '-----END EC PRIVATE KEY-----')
            || str_contains($keyPair['privateKey'], '-----END PRIVATE KEY-----'),
            'Private key must have PEM end marker',
        );

        self::assertStringStartsWith('-----BEGIN PUBLIC KEY-----', $keyPair['publicKey']);
        self::assertStringContainsString('-----END PUBLIC KEY-----', $keyPair['publicKey']);
    }

    #[Test]
    public function signAndVerifyRoundtripWithGeneratedKeys(): void
    {
        $keyPair = $this->requireKeyPair();

        $data = 'Hello, OSPP protocol!';

        $signature = $this->ecdsa->sign($data, $keyPair['privateKey']);

        // Signature must be valid base64
        self::assertNotFalse(base64_decode($signature, true));

        // Must verify with the matching public key
        self::assertTrue($this->ecdsa->verify($data, $signature, $keyPair['publicKey']));
    }

    #[Test]
    public function verifyFailsWithWrongData(): void
    {
        $keyPair = $this->requireKeyPair();

        $data = 'original data';
        $signature = $this->ecdsa->sign($data, $keyPair['privateKey']);

        self::assertFalse($this->ecdsa->verify('tampered data', $signature, $keyPair['publicKey']));
    }

    #[Test]
    public function verifyFailsWithInvalidBase64Signature(): void
    {
        $keyPair = $this->requireKeyPair();

        $data = 'some data';

        // '!!!' is not valid strict base64
        self::assertFalse($this->ecdsa->verify($data, '!!!invalid-base64!!!', $keyPair['publicKey']));
    }

    #[Test]
    public function signOfflinePassStripsSignatureAndSignatureAlgorithmFields(): void
    {
        $keyPair = $this->requireKeyPair();

        $passData = [
            'passId' => 'pass-001',
            'userId' => 'user-001',
            'signature' => 'old-signature-should-be-stripped',
            'signatureAlgorithm' => 'ES256',
        ];

        $passDataWithout = [
            'passId' => 'pass-001',
            'userId' => 'user-001',
        ];

        $sig1 = $this->ecdsa->signOfflinePass($passData, $keyPair['privateKey']);
        $sig2 = $this->ecdsa->signOfflinePass($passDataWithout, $keyPair['privateKey']);

        // Both should sign the same canonical data, but ECDSA is non-deterministic
        // so we verify both signatures against the stripped data instead
        $serializer = new CanonicalJsonSerializer();
        $canonical = $serializer->serialize($passDataWithout);

        self::assertTrue($this->ecdsa->verify($canonical, $sig1, $keyPair['publicKey']));
        self::assertTrue($this->ecdsa->verify($canonical, $sig2, $keyPair['publicKey']));
    }

    #[Test]
    public function verifyOfflinePassWithValidSignature(): void
    {
        $keyPair = $this->requireKeyPair();

        $passData = [
            'passId' => 'pass-001',
            'userId' => 'user-001',
            'maxEnergy' => 50,
        ];

        $signature = $this->ecdsa->signOfflinePass($passData, $keyPair['privateKey']);

        $signedPassData = array_merge($passData, [
            'signature' => $signature,
            'signatureAlgorithm' => 'ES256',
        ]);

        self::assertTrue($this->ecdsa->verifyOfflinePass($signedPassData, $keyPair['publicKey']));
    }

    #[Test]
    public function verifyOfflinePassReturnsFalseWhenSignatureFieldMissing(): void
    {
        $keyPair = $this->requireKeyPair();

        $passData = [
            'passId' => 'pass-001',
            'userId' => 'user-001',
        ];

        // No 'signature' key at all
        self::assertFalse($this->ecdsa->verifyOfflinePass($passData, $keyPair['publicKey']));

        // Empty string signature
        $passData['signature'] = '';
        self::assertFalse($this->ecdsa->verifyOfflinePass($passData, $keyPair['publicKey']));
    }

    #[Test]
    public function signThrowsRuntimeExceptionWithInvalidKey(): void
    {
        $this->expectException(RuntimeException::class);

        $this->ecdsa->sign('data', 'this-is-not-a-pem-key');
    }
}
