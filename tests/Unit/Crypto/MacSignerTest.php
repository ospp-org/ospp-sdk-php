<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Crypto;

use Ospp\Protocol\Crypto\CanonicalJsonSerializer;
use Ospp\Protocol\Crypto\MacSigner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MacSignerTest extends TestCase
{
    private MacSigner $signer;

    /** Base64-encoded 32-byte session key (deterministic for tests) */
    private string $sessionKey;

    protected function setUp(): void
    {
        $this->signer = new MacSigner(new CanonicalJsonSerializer());
        // 32 zero bytes encoded as base64
        $this->sessionKey = base64_encode(str_repeat("\x00", 32));
    }

    #[Test]
    public function signProducesBase64String(): void
    {
        $payload = ['action' => 'StartService', 'stationId' => 'ST-001'];

        $mac = $this->signer->sign($payload, $this->sessionKey);

        // Must be valid base64
        self::assertNotEmpty($mac);
        self::assertNotFalse(base64_decode($mac, true));

        // HMAC-SHA256 produces 32 bytes -> 44 characters in base64 (with padding)
        self::assertSame(44, strlen($mac));
    }

    #[Test]
    public function signRemovesMacFieldFromPayloadBeforeSigning(): void
    {
        $payload = ['action' => 'StartService', 'stationId' => 'ST-001'];
        $payloadWithMac = array_merge($payload, ['mac' => 'should-be-stripped']);

        $mac1 = $this->signer->sign($payload, $this->sessionKey);
        $mac2 = $this->signer->sign($payloadWithMac, $this->sessionKey);

        // Both must produce the same MAC since 'mac' is stripped before signing
        self::assertSame($mac1, $mac2);
    }

    #[Test]
    public function verifyReturnsTrueForMatchingSignature(): void
    {
        $payload = ['action' => 'StartService', 'bayId' => 'BAY-01'];

        $mac = $this->signer->sign($payload, $this->sessionKey);

        self::assertTrue($this->signer->verify($payload, $mac, $this->sessionKey));
    }

    #[Test]
    public function verifyReturnsFalseForTamperedPayload(): void
    {
        $payload = ['action' => 'StartService', 'bayId' => 'BAY-01'];

        $mac = $this->signer->sign($payload, $this->sessionKey);

        $tampered = ['action' => 'StartService', 'bayId' => 'BAY-99'];

        self::assertFalse($this->signer->verify($tampered, $mac, $this->sessionKey));
    }

    #[Test]
    public function verifyReturnsFalseForInvalidBase64Mac(): void
    {
        $payload = ['action' => 'Heartbeat'];

        // '!!!' is not valid strict base64
        self::assertFalse($this->signer->verify($payload, '!!!not-base64!!!', $this->sessionKey));
    }

    #[Test]
    public function canonicalizeRemovesMacFieldAndReturnsCanonicalJson(): void
    {
        $payload = [
            'z_key' => 'last',
            'a_key' => 'first',
            'mac' => 'should-be-removed',
        ];

        $canonical = $this->signer->canonicalize($payload);

        self::assertSame('{"a_key":"first","z_key":"last"}', $canonical);
        self::assertStringNotContainsString('mac', $canonical);
    }

    #[Test]
    public function signAndVerifyRoundtripWithKnownSessionKey(): void
    {
        // Known 32-byte key (all 0xAB)
        $knownKey = base64_encode(str_repeat("\xAB", 32));

        $payload = [
            'protocolVersion' => '1.0.0',
            'messageId' => 'msg-123',
            'action' => 'BootNotification',
            'payload' => [
                'vendor' => 'TestVendor',
                'model' => 'TestModel',
            ],
        ];

        $mac = $this->signer->sign($payload, $knownKey);

        // Must verify with same key and payload
        self::assertTrue($this->signer->verify($payload, $mac, $knownKey));

        // Must fail with different key
        $differentKey = base64_encode(str_repeat("\xCD", 32));
        self::assertFalse($this->signer->verify($payload, $mac, $differentKey));
    }
}
