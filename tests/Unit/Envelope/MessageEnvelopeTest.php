<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Unit\Envelope;

use DateTimeImmutable;
use OneStopPay\OsppProtocol\Enums\MessageType;
use OneStopPay\OsppProtocol\Envelope\MessageEnvelope;
use OneStopPay\OsppProtocol\ValueObjects\MessageId;
use OneStopPay\OsppProtocol\ValueObjects\ProtocolVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MessageEnvelopeTest extends TestCase
{
    private function makeEnvelope(
        ?MessageId $messageId = null,
        MessageType $messageType = MessageType::REQUEST,
        string $action = 'BootNotification',
        ?DateTimeImmutable $timestamp = null,
        string $source = 'station',
        ?ProtocolVersion $protocolVersion = null,
        array $payload = [],
        ?string $mac = null,
    ): MessageEnvelope {
        return new MessageEnvelope(
            messageId: $messageId ?? MessageId::generate('msg_'),
            messageType: $messageType,
            action: $action,
            timestamp: $timestamp ?? new DateTimeImmutable('2025-01-15T10:30:00.000Z'),
            source: $source,
            protocolVersion: $protocolVersion ?? ProtocolVersion::default(),
            payload: $payload,
            mac: $mac,
        );
    }

    // ---------------------------------------------------------------
    // Constructor — property assignment
    // ---------------------------------------------------------------

    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $messageId = MessageId::generate('cmd_');
        $timestamp = new DateTimeImmutable('2025-06-01T12:00:00.000Z');
        $version = new ProtocolVersion(1, 0, 0);
        $payload = ['stationId' => 'ST-001', 'model' => 'X100'];

        $envelope = new MessageEnvelope(
            messageId: $messageId,
            messageType: MessageType::REQUEST,
            action: 'BootNotification',
            timestamp: $timestamp,
            source: 'station',
            protocolVersion: $version,
            payload: $payload,
            mac: 'abc123mac',
        );

        self::assertSame($messageId, $envelope->messageId);
        self::assertSame(MessageType::REQUEST, $envelope->messageType);
        self::assertSame('BootNotification', $envelope->action);
        self::assertSame($timestamp, $envelope->timestamp);
        self::assertSame('station', $envelope->source);
        self::assertSame($version, $envelope->protocolVersion);
        self::assertSame($payload, $envelope->payload);
        self::assertSame('abc123mac', $envelope->mac);
    }

    #[Test]
    public function constructorDefaultsMacToNull(): void
    {
        $envelope = $this->makeEnvelope();

        self::assertNull($envelope->mac);
    }

    // ---------------------------------------------------------------
    // isSigned()
    // ---------------------------------------------------------------

    #[Test]
    public function isSignedReturnsTrueWhenMacPresent(): void
    {
        $envelope = $this->makeEnvelope(mac: 'hmac-sha256-value');

        self::assertTrue($envelope->isSigned());
    }

    #[Test]
    public function isSignedReturnsFalseWhenMacNull(): void
    {
        $envelope = $this->makeEnvelope(mac: null);

        self::assertFalse($envelope->isSigned());
    }

    // ---------------------------------------------------------------
    // expectsResponse()
    // ---------------------------------------------------------------

    #[Test]
    public function expectsResponseTrueForRequest(): void
    {
        $envelope = $this->makeEnvelope(messageType: MessageType::REQUEST);

        self::assertTrue($envelope->expectsResponse());
    }

    #[Test]
    public function expectsResponseFalseForResponse(): void
    {
        $envelope = $this->makeEnvelope(messageType: MessageType::RESPONSE);

        self::assertFalse($envelope->expectsResponse());
    }

    #[Test]
    public function expectsResponseFalseForEvent(): void
    {
        $envelope = $this->makeEnvelope(messageType: MessageType::EVENT);

        self::assertFalse($envelope->expectsResponse());
    }

    // ---------------------------------------------------------------
    // isEvent()
    // ---------------------------------------------------------------

    #[Test]
    public function isEventTrueForEvent(): void
    {
        $envelope = $this->makeEnvelope(messageType: MessageType::EVENT);

        self::assertTrue($envelope->isEvent());
    }

    #[Test]
    public function isEventFalseForRequest(): void
    {
        $envelope = $this->makeEnvelope(messageType: MessageType::REQUEST);

        self::assertFalse($envelope->isEvent());
    }

    #[Test]
    public function isEventFalseForResponse(): void
    {
        $envelope = $this->makeEnvelope(messageType: MessageType::RESPONSE);

        self::assertFalse($envelope->isEvent());
    }

    // ---------------------------------------------------------------
    // toArray()
    // ---------------------------------------------------------------

    #[Test]
    public function toArrayIncludesAllFieldsWithoutMac(): void
    {
        $messageId = MessageId::fromString('msg_550e8400-e29b-41d4-a716-446655440000');
        $timestamp = new DateTimeImmutable('2025-01-15T10:30:00.000Z');
        $version = new ProtocolVersion(1, 0, 0);
        $payload = ['stationId' => 'ST-001'];

        $envelope = new MessageEnvelope(
            messageId: $messageId,
            messageType: MessageType::REQUEST,
            action: 'BootNotification',
            timestamp: $timestamp,
            source: 'station',
            protocolVersion: $version,
            payload: $payload,
        );

        $array = $envelope->toArray();

        self::assertSame('msg_550e8400-e29b-41d4-a716-446655440000', $array['messageId']);
        self::assertSame('REQUEST', $array['messageType']);
        self::assertSame('BootNotification', $array['action']);
        self::assertSame('2025-01-15T10:30:00.000Z', $array['timestamp']);
        self::assertSame('station', $array['source']);
        self::assertSame('1.0.0', $array['protocolVersion']);
        self::assertSame(['stationId' => 'ST-001'], $array['payload']);
        self::assertArrayNotHasKey('mac', $array);
    }

    #[Test]
    public function toArrayIncludesMacWhenPresent(): void
    {
        $envelope = $this->makeEnvelope(mac: 'hmac-value-here');

        $array = $envelope->toArray();

        self::assertArrayHasKey('mac', $array);
        self::assertSame('hmac-value-here', $array['mac']);
    }

    #[Test]
    public function toArrayExcludesMacWhenNull(): void
    {
        $envelope = $this->makeEnvelope(mac: null);

        $array = $envelope->toArray();

        self::assertArrayNotHasKey('mac', $array);
    }

    #[Test]
    public function toArrayTimestampFormatIncludesMilliseconds(): void
    {
        $timestamp = new DateTimeImmutable('2025-06-15T08:45:30.123Z');
        $envelope = $this->makeEnvelope(timestamp: $timestamp);

        $array = $envelope->toArray();

        self::assertSame('2025-06-15T08:45:30.123Z', $array['timestamp']);
    }

    // ---------------------------------------------------------------
    // toJson()
    // ---------------------------------------------------------------

    #[Test]
    public function toJsonProducesValidJson(): void
    {
        $envelope = $this->makeEnvelope(
            payload: ['stationId' => 'ST-001'],
        );

        $json = $envelope->toJson();
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('messageId', $decoded);
        self::assertArrayHasKey('messageType', $decoded);
        self::assertArrayHasKey('action', $decoded);
        self::assertArrayHasKey('timestamp', $decoded);
        self::assertArrayHasKey('source', $decoded);
        self::assertArrayHasKey('protocolVersion', $decoded);
        self::assertArrayHasKey('payload', $decoded);
    }

    #[Test]
    public function toJsonIsCompactNoWhitespace(): void
    {
        $envelope = $this->makeEnvelope();
        $json = $envelope->toJson();

        // Compact JSON should not contain newlines or indentation
        self::assertStringNotContainsString("\n", $json);
        self::assertStringNotContainsString('    ', $json);
    }

    #[Test]
    public function toJsonDoesNotEscapeSlashes(): void
    {
        $envelope = $this->makeEnvelope(
            payload: ['url' => 'https://example.com/path'],
        );

        $json = $envelope->toJson();

        self::assertStringContainsString('https://example.com/path', $json);
        self::assertStringNotContainsString('\\/', $json);
    }

    // ---------------------------------------------------------------
    // getPayloadStationId()
    // ---------------------------------------------------------------

    #[Test]
    public function getPayloadStationIdReturnsStringWhenPresent(): void
    {
        $envelope = $this->makeEnvelope(
            payload: ['stationId' => 'ST-001'],
        );

        self::assertSame('ST-001', $envelope->getPayloadStationId());
    }

    #[Test]
    public function getPayloadStationIdReturnsNullWhenMissing(): void
    {
        $envelope = $this->makeEnvelope(payload: []);

        self::assertNull($envelope->getPayloadStationId());
    }

    #[Test]
    public function getPayloadStationIdReturnsNullWhenNotString(): void
    {
        $envelope = $this->makeEnvelope(
            payload: ['stationId' => 12345],
        );

        self::assertNull($envelope->getPayloadStationId());
    }

    #[Test]
    public function getPayloadStationIdReturnsNullWhenArrayValue(): void
    {
        $envelope = $this->makeEnvelope(
            payload: ['stationId' => ['nested' => 'value']],
        );

        self::assertNull($envelope->getPayloadStationId());
    }

    #[Test]
    public function getPayloadStationIdReturnsNullWhenNullValue(): void
    {
        $envelope = $this->makeEnvelope(
            payload: ['stationId' => null],
        );

        self::assertNull($envelope->getPayloadStationId());
    }

    // ---------------------------------------------------------------
    // withMac()
    // ---------------------------------------------------------------

    #[Test]
    public function withMacReturnsNewInstanceWithMac(): void
    {
        $original = $this->makeEnvelope(mac: null);
        $signed = $original->withMac('new-mac-value');

        self::assertNull($original->mac);
        self::assertSame('new-mac-value', $signed->mac);
    }

    #[Test]
    public function withMacPreservesAllOtherFields(): void
    {
        $original = $this->makeEnvelope(
            action: 'Heartbeat',
            source: 'server',
            payload: ['status' => 'ok'],
        );

        $signed = $original->withMac('mac-value');

        self::assertTrue($original->messageId->equals($signed->messageId));
        self::assertSame($original->messageType, $signed->messageType);
        self::assertSame($original->action, $signed->action);
        self::assertSame($original->timestamp, $signed->timestamp);
        self::assertSame($original->source, $signed->source);
        self::assertTrue($original->protocolVersion->equals($signed->protocolVersion));
        self::assertSame($original->payload, $signed->payload);
    }

    #[Test]
    public function withMacReturnsDistinctInstance(): void
    {
        $original = $this->makeEnvelope();
        $signed = $original->withMac('mac');

        self::assertNotSame($original, $signed);
    }

    #[Test]
    public function withMacCanReplacePreviousMac(): void
    {
        $envelope = $this->makeEnvelope(mac: 'old-mac');
        $updated = $envelope->withMac('new-mac');

        self::assertSame('old-mac', $envelope->mac);
        self::assertSame('new-mac', $updated->mac);
    }
}
