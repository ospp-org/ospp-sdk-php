<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Crypto;

use Ospp\Protocol\Crypto\CanonicalJsonSerializer;
use Ospp\Protocol\Crypto\MacSigner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MacSignerContractTest extends TestCase
{
    private MacSigner $signer;

    private CanonicalJsonSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new CanonicalJsonSerializer();
        $this->signer = new MacSigner($this->serializer);
    }

    // ── Vector 1: Empty payload ──
    // Note: PHP empty array [] is non-associative, serializes to '[]' not '{}'.

    #[Test]
    public function vector1_empty_payload_canonical(): void
    {
        $payload = [];
        $canonical = $this->signer->canonicalize($payload);

        // PHP empty array is sequential (non-associative), so it serializes as '[]'
        self::assertSame('[]', $canonical);
    }

    #[Test]
    public function vector1_empty_payload_sign(): void
    {
        $key = base64_encode(str_repeat("\x00", 32));
        $payload = [];
        $expectedCanonical = '[]';
        $expectedMac = base64_encode(hash_hmac('sha256', $expectedCanonical, str_repeat("\x00", 32), true));

        $mac = $this->signer->sign($payload, $key);

        self::assertSame($expectedMac, $mac);
    }

    #[Test]
    public function vector1_empty_payload_verify(): void
    {
        $key = base64_encode(str_repeat("\x00", 32));
        $payload = [];
        $expectedCanonical = '[]';
        $expectedMac = base64_encode(hash_hmac('sha256', $expectedCanonical, str_repeat("\x00", 32), true));

        self::assertTrue($this->signer->verify($payload, $expectedMac, $key));
    }

    // ── Vector 2: Sorted keys ──

    #[Test]
    public function vector2_sorted_keys_canonical(): void
    {
        $payload = ['stationId' => 'ST-001', 'action' => 'StartService'];
        $canonical = $this->signer->canonicalize($payload);

        self::assertSame('{"action":"StartService","stationId":"ST-001"}', $canonical);
    }

    #[Test]
    public function vector2_sorted_keys_sign(): void
    {
        $key = base64_encode(str_repeat("\xAB", 32));
        $payload = ['stationId' => 'ST-001', 'action' => 'StartService'];
        $expectedCanonical = '{"action":"StartService","stationId":"ST-001"}';
        $expectedMac = base64_encode(hash_hmac('sha256', $expectedCanonical, str_repeat("\xAB", 32), true));

        $mac = $this->signer->sign($payload, $key);

        self::assertSame($expectedMac, $mac);
    }

    #[Test]
    public function vector2_sorted_keys_verify(): void
    {
        $key = base64_encode(str_repeat("\xAB", 32));
        $payload = ['stationId' => 'ST-001', 'action' => 'StartService'];
        $expectedCanonical = '{"action":"StartService","stationId":"ST-001"}';
        $expectedMac = base64_encode(hash_hmac('sha256', $expectedCanonical, str_repeat("\xAB", 32), true));

        self::assertTrue($this->signer->verify($payload, $expectedMac, $key));
    }

    // ── Vector 3: Nested ──

    #[Test]
    public function vector3_nested_canonical(): void
    {
        $payload = ['z' => ['b' => 2, 'a' => 1], 'a' => 'first'];
        $canonical = $this->signer->canonicalize($payload);

        self::assertSame('{"a":"first","z":{"a":1,"b":2}}', $canonical);
    }

    #[Test]
    public function vector3_nested_sign(): void
    {
        $key = base64_encode('test-key-32-bytes-long-exactly!!');
        $payload = ['z' => ['b' => 2, 'a' => 1], 'a' => 'first'];
        $expectedCanonical = '{"a":"first","z":{"a":1,"b":2}}';
        $expectedMac = base64_encode(hash_hmac('sha256', $expectedCanonical, 'test-key-32-bytes-long-exactly!!', true));

        $mac = $this->signer->sign($payload, $key);

        self::assertSame($expectedMac, $mac);
    }

    #[Test]
    public function vector3_nested_verify(): void
    {
        $key = base64_encode('test-key-32-bytes-long-exactly!!');
        $payload = ['z' => ['b' => 2, 'a' => 1], 'a' => 'first'];
        $expectedCanonical = '{"a":"first","z":{"a":1,"b":2}}';
        $expectedMac = base64_encode(hash_hmac('sha256', $expectedCanonical, 'test-key-32-bytes-long-exactly!!', true));

        self::assertTrue($this->signer->verify($payload, $expectedMac, $key));
    }

    // ── Additional tests ──

    #[Test]
    public function sign_strips_mac_field(): void
    {
        $key = base64_encode(str_repeat("\x01", 32));
        $payloadWithMac = ['action' => 'Test', 'mac' => 'some-old-mac-value'];
        $payloadWithoutMac = ['action' => 'Test'];

        $macWith = $this->signer->sign($payloadWithMac, $key);
        $macWithout = $this->signer->sign($payloadWithoutMac, $key);

        self::assertSame($macWithout, $macWith);
    }

    #[Test]
    public function verify_rejects_empty_mac_string(): void
    {
        $key = base64_encode(str_repeat("\x01", 32));
        $payload = ['action' => 'Test'];

        self::assertFalse($this->signer->verify($payload, '', $key));
    }

    #[Test]
    public function verify_rejects_truncated_mac(): void
    {
        $key = base64_encode(str_repeat("\x01", 32));
        $payload = ['action' => 'Test'];

        $validMac = $this->signer->sign($payload, $key);
        // Remove multiple characters to ensure the decoded bytes differ in length
        $truncatedMac = substr($validMac, 0, -4);

        self::assertFalse($this->signer->verify($payload, $truncatedMac, $key));
    }

    #[Test]
    public function sign_output_length_is_44_chars(): void
    {
        $key = base64_encode(str_repeat("\x01", 32));
        $payload = ['action' => 'Test'];

        $mac = $this->signer->sign($payload, $key);

        // base64 of 32 bytes = ceil(32/3)*4 = 44 characters (with padding)
        self::assertSame(44, strlen($mac));
    }

    #[Test]
    public function canonicalize_matches_CanonicalJsonSerializer(): void
    {
        $payload = ['z' => 'last', 'a' => 'first', 'mac' => 'should-be-stripped'];

        $fromSigner = $this->signer->canonicalize($payload);

        $payloadWithoutMac = $payload;
        unset($payloadWithoutMac['mac']);
        $fromSerializer = $this->serializer->serialize($payloadWithoutMac);

        self::assertSame($fromSerializer, $fromSigner);
    }

    #[Test]
    public function verify_rejects_mac_from_different_key(): void
    {
        $key1 = base64_encode(str_repeat("\x01", 32));
        $key2 = base64_encode(str_repeat("\x02", 32));
        $payload = ['action' => 'Test'];

        $macFromKey1 = $this->signer->sign($payload, $key1);

        self::assertFalse($this->signer->verify($payload, $macFromKey1, $key2));
    }
}
