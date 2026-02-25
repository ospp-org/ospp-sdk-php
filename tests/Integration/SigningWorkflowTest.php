<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Integration;

use Ospp\Protocol\Actions\OsppAction;
use Ospp\Protocol\Crypto\CanonicalJsonSerializer;
use Ospp\Protocol\Crypto\CriticalMessageRegistry;
use Ospp\Protocol\Crypto\MacSigner;
use Ospp\Protocol\Envelope\MessageBuilder;
use Ospp\Protocol\Enums\SigningMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SigningWorkflowTest extends TestCase
{
    private MacSigner $signer;

    private string $sessionKey;

    protected function setUp(): void
    {
        $this->signer = new MacSigner(new CanonicalJsonSerializer());
        $this->sessionKey = base64_encode(random_bytes(32));
    }

    #[Test]
    public function build_sign_verify_roundtrip(): void
    {
        $envelope = MessageBuilder::request('StartService')
            ->withPayload(['stationId' => 'ST-001', 'bayId' => 'BAY-01'])
            ->build();

        $array = $envelope->toArray();
        $mac = $this->signer->sign($array, $this->sessionKey);
        $verified = $this->signer->verify($array, $mac, $this->sessionKey);

        self::assertTrue($verified);
    }

    #[Test]
    public function tampered_payload_fails_verification(): void
    {
        $envelope = MessageBuilder::request('StartService')
            ->withPayload(['stationId' => 'ST-001', 'bayId' => 'BAY-01'])
            ->build();

        $array = $envelope->toArray();
        $mac = $this->signer->sign($array, $this->sessionKey);

        // Tamper with the payload
        $tampered = $array;
        $tampered['payload']['stationId'] = 'ST-HACKED';

        $verified = $this->signer->verify($tampered, $mac, $this->sessionKey);

        self::assertFalse($verified);
    }

    #[Test]
    public function signing_mode_ALL_signs_every_action(): void
    {
        self::assertCount(24, OsppAction::all());

        foreach (OsppAction::all() as $action) {
            self::assertTrue(
                SigningMode::ALL->shouldSign($action),
                "SigningMode::ALL should sign action '{$action}'",
            );
        }
    }

    #[Test]
    public function signing_mode_CRITICAL_matches_registry(): void
    {
        foreach (OsppAction::all() as $action) {
            $expected = CriticalMessageRegistry::isCritical($action);
            $actual = SigningMode::CRITICAL->shouldSign($action);

            self::assertSame(
                $expected,
                $actual,
                "SigningMode::CRITICAL->shouldSign('{$action}') should match CriticalMessageRegistry::isCritical('{$action}')",
            );
        }
    }

    #[Test]
    public function signing_mode_NONE_skips_all(): void
    {
        foreach (OsppAction::all() as $action) {
            self::assertFalse(
                SigningMode::NONE->shouldSign($action),
                "SigningMode::NONE should not sign action '{$action}'",
            );
        }
    }

    #[Test]
    public function complex_nested_payload_sign_verify(): void
    {
        $envelope = MessageBuilder::request('ChangeConfiguration')
            ->withPayload([
                'stationId' => 'ST-001',
                'configuration' => [
                    'key' => 'HeartbeatInterval',
                    'value' => '60',
                    'metadata' => [
                        'source' => 'admin',
                        'notes' => 'Zwiększono interwał',
                        'tags' => ['production', 'critical'],
                    ],
                ],
                'nested' => [
                    'deep' => [
                        'unicode' => "\u{1F600} emoji test",
                        'url' => 'https://example.com/config?key=value&other=1',
                    ],
                ],
            ])
            ->build();

        $array = $envelope->toArray();
        $mac = $this->signer->sign($array, $this->sessionKey);
        $verified = $this->signer->verify($array, $mac, $this->sessionKey);

        self::assertTrue($verified);
    }

    #[Test]
    public function different_session_keys_produce_different_MACs(): void
    {
        $envelope = MessageBuilder::request('Heartbeat')
            ->withPayload(['stationId' => 'ST-001'])
            ->build();

        $array = $envelope->toArray();

        $key1 = base64_encode(random_bytes(32));
        $key2 = base64_encode(random_bytes(32));

        $mac1 = $this->signer->sign($array, $key1);
        $mac2 = $this->signer->sign($array, $key2);

        self::assertNotSame($mac1, $mac2);
    }
}
