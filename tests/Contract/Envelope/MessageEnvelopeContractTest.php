<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Envelope;

use DateTimeImmutable;
use DateTimeZone;
use Ospp\Protocol\Envelope\MessageEnvelope;
use Ospp\Protocol\Enums\MessageType;
use Ospp\Protocol\ValueObjects\MessageId;
use Ospp\Protocol\ValueObjects\ProtocolVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MessageEnvelopeContractTest extends TestCase
{
    #[Test]
    public function toArray_timestamp_format_matches_ISO8601_with_milliseconds(): void
    {
        $envelope = new MessageEnvelope(
            messageId: MessageId::generate(),
            messageType: MessageType::REQUEST,
            action: 'TestAction',
            timestamp: new DateTimeImmutable('2025-06-15T14:30:45.123+00:00'),
            source: 'Server',
            protocolVersion: ProtocolVersion::default(),
            payload: ['key' => 'value'],
        );

        $array = $envelope->toArray();

        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/',
            $array['timestamp'],
        );
    }

    #[Test]
    public function toArray_field_names_are_camelCase(): void
    {
        $envelope = new MessageEnvelope(
            messageId: MessageId::generate(),
            messageType: MessageType::REQUEST,
            action: 'TestAction',
            timestamp: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            source: 'Server',
            protocolVersion: ProtocolVersion::default(),
            payload: [],
        );

        $array = $envelope->toArray();
        $expectedKeys = ['messageId', 'messageType', 'action', 'timestamp', 'source', 'protocolVersion', 'payload'];

        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $array, "Missing key: {$key}");
        }
    }

    #[Test]
    public function toArray_without_mac_has_exactly_7_keys(): void
    {
        $envelope = new MessageEnvelope(
            messageId: MessageId::generate(),
            messageType: MessageType::REQUEST,
            action: 'TestAction',
            timestamp: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            source: 'Server',
            protocolVersion: ProtocolVersion::default(),
            payload: [],
        );

        $array = $envelope->toArray();

        self::assertCount(7, $array);
    }

    #[Test]
    public function toArray_with_mac_has_exactly_8_keys(): void
    {
        $envelope = new MessageEnvelope(
            messageId: MessageId::generate(),
            messageType: MessageType::REQUEST,
            action: 'TestAction',
            timestamp: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            source: 'Server',
            protocolVersion: ProtocolVersion::default(),
            payload: [],
            mac: 'dGVzdC1tYWMtdmFsdWU=',
        );

        $array = $envelope->toArray();

        self::assertCount(8, $array);
        self::assertArrayHasKey('mac', $array);
    }

    #[Test]
    public function toJson_is_compact_without_escaped_slashes(): void
    {
        $envelope = new MessageEnvelope(
            messageId: MessageId::generate(),
            messageType: MessageType::REQUEST,
            action: 'TestAction',
            timestamp: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            source: 'Server',
            protocolVersion: ProtocolVersion::default(),
            payload: ['url' => 'https://example.com/path'],
        );

        $json = $envelope->toJson();

        self::assertStringNotContainsString('\\/', $json);
        self::assertStringNotContainsString("\n", $json);
        self::assertStringNotContainsString('  ', $json);
    }

    #[Test]
    public function toJson_roundtrips_through_json_decode(): void
    {
        $envelope = new MessageEnvelope(
            messageId: MessageId::generate(),
            messageType: MessageType::EVENT,
            action: 'StatusNotification',
            timestamp: new DateTimeImmutable('2025-03-10T08:00:00.500+00:00'),
            source: 'Station',
            protocolVersion: ProtocolVersion::default(),
            payload: ['stationId' => 'ST-001', 'status' => 'available'],
        );

        $decoded = json_decode($envelope->toJson(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($envelope->toArray(), $decoded);
    }

    #[Test]
    public function toArray_messageType_is_PascalCase_string(): void
    {
        $types = [
            [MessageType::REQUEST, 'Request'],
            [MessageType::RESPONSE, 'Response'],
            [MessageType::EVENT, 'Event'],
        ];

        foreach ($types as [$type, $expected]) {
            $envelope = new MessageEnvelope(
                messageId: MessageId::generate(),
                messageType: $type,
                action: 'TestAction',
                timestamp: new DateTimeImmutable('now', new DateTimeZone('UTC')),
                source: 'Server',
                protocolVersion: ProtocolVersion::default(),
                payload: [],
            );

            $array = $envelope->toArray();

            self::assertSame($expected, $array['messageType']);
            self::assertMatchesRegularExpression('/^[A-Z][a-z]+$/', $array['messageType']);
        }
    }

    #[Test]
    public function toArray_protocolVersion_is_semver_string(): void
    {
        $envelope = new MessageEnvelope(
            messageId: MessageId::generate(),
            messageType: MessageType::REQUEST,
            action: 'TestAction',
            timestamp: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            source: 'Server',
            protocolVersion: ProtocolVersion::fromString('0.2.1'),
            payload: [],
        );

        $array = $envelope->toArray();

        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $array['protocolVersion']);
    }

    #[Test]
    public function mac_key_omitted_not_null_when_absent(): void
    {
        $envelope = new MessageEnvelope(
            messageId: MessageId::generate(),
            messageType: MessageType::REQUEST,
            action: 'TestAction',
            timestamp: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            source: 'Server',
            protocolVersion: ProtocolVersion::default(),
            payload: [],
            mac: null,
        );

        $array = $envelope->toArray();

        self::assertFalse(array_key_exists('mac', $array));
    }

    #[Test]
    public function timestamp_with_zero_milliseconds_shows_000(): void
    {
        $envelope = new MessageEnvelope(
            messageId: MessageId::generate(),
            messageType: MessageType::REQUEST,
            action: 'TestAction',
            timestamp: new DateTimeImmutable('2025-01-01T00:00:00.000000+00:00'),
            source: 'Server',
            protocolVersion: ProtocolVersion::default(),
            payload: [],
        );

        $array = $envelope->toArray();

        self::assertStringContainsString('.000Z', $array['timestamp']);
        self::assertSame('2025-01-01T00:00:00.000Z', $array['timestamp']);
    }

    #[Test]
    public function withMac_returns_new_immutable_instance(): void
    {
        $original = new MessageEnvelope(
            messageId: MessageId::generate(),
            messageType: MessageType::REQUEST,
            action: 'TestAction',
            timestamp: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            source: 'Server',
            protocolVersion: ProtocolVersion::default(),
            payload: ['key' => 'value'],
            mac: null,
        );

        $withMac = $original->withMac('dGVzdC1tYWM=');

        self::assertNull($original->mac);
        self::assertSame('dGVzdC1tYWM=', $withMac->mac);
        self::assertNotSame($original, $withMac);
        self::assertSame($original->messageId->value, $withMac->messageId->value);
        self::assertSame($original->action, $withMac->action);
        self::assertSame($original->payload, $withMac->payload);
    }
}
