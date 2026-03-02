<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Integration;

use DateTimeImmutable;
use Ospp\Protocol\Envelope\MessageBuilder;
use Ospp\Protocol\ValueObjects\MessageId;
use Ospp\Protocol\ValueObjects\ProtocolVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnvelopeSerializationTest extends TestCase
{
    #[Test]
    public function boot_notification_request_json_snapshot(): void
    {
        $envelope = MessageBuilder::request('BootNotification')
            ->withMessageId(MessageId::fromString('cmd_550e8400-e29b-41d4-a716-446655440000'))
            ->withTimestamp(new DateTimeImmutable('2025-01-15T10:30:00.500+00:00'))
            ->withProtocolVersion(ProtocolVersion::fromString('0.1.0'))
            ->withPayload(['vendor' => 'AcmeCorp', 'model' => 'OSP-100'])
            ->build();

        $expectedJson = '{"messageId":"cmd_550e8400-e29b-41d4-a716-446655440000","messageType":"Request","action":"BootNotification","timestamp":"2025-01-15T10:30:00.500Z","source":"Server","protocolVersion":"0.1.0","payload":{"vendor":"AcmeCorp","model":"OSP-100"}}';

        self::assertSame($expectedJson, $envelope->toJson());
    }

    #[Test]
    public function heartbeat_response_json_snapshot(): void
    {
        $envelope = MessageBuilder::response('Heartbeat')
            ->withMessageId(MessageId::fromString('msg_660e8400-e29b-41d4-a716-446655440001'))
            ->withTimestamp(new DateTimeImmutable('2025-02-20T12:00:00.000+00:00'))
            ->withProtocolVersion(ProtocolVersion::fromString('0.1.0'))
            ->withPayload(['currentTime' => '2025-02-20T12:00:00.000Z'])
            ->build();

        $expectedJson = '{"messageId":"msg_660e8400-e29b-41d4-a716-446655440001","messageType":"Response","action":"Heartbeat","timestamp":"2025-02-20T12:00:00.000Z","source":"Server","protocolVersion":"0.1.0","payload":{"currentTime":"2025-02-20T12:00:00.000Z"}}';

        self::assertSame($expectedJson, $envelope->toJson());
    }

    #[Test]
    public function start_service_request_with_mac_snapshot(): void
    {
        $envelope = MessageBuilder::request('StartService')
            ->withMessageId(MessageId::fromString('cmd_770e8400-e29b-41d4-a716-446655440002'))
            ->withTimestamp(new DateTimeImmutable('2025-03-01T08:15:30.250+00:00'))
            ->withProtocolVersion(ProtocolVersion::fromString('0.1.0'))
            ->withPayload(['stationId' => 'ST-001', 'bayId' => 'BAY-01'])
            ->withMac('dGVzdC1tYWMtdmFsdWU=')
            ->build();

        $expectedJson = '{"messageId":"cmd_770e8400-e29b-41d4-a716-446655440002","messageType":"Request","action":"StartService","timestamp":"2025-03-01T08:15:30.250Z","source":"Server","protocolVersion":"0.1.0","payload":{"stationId":"ST-001","bayId":"BAY-01"},"mac":"dGVzdC1tYWMtdmFsdWU="}';

        self::assertSame($expectedJson, $envelope->toJson());
    }

    #[Test]
    public function event_message_json_snapshot(): void
    {
        $envelope = MessageBuilder::event('StatusNotification')
            ->withMessageId(MessageId::fromString('msg_880e8400-e29b-41d4-a716-446655440003'))
            ->withTimestamp(new DateTimeImmutable('2025-04-10T16:45:00.999+00:00'))
            ->withProtocolVersion(ProtocolVersion::fromString('0.1.0'))
            ->withPayload(['stationId' => 'ST-002', 'bayId' => 'BAY-03', 'status' => 'available'])
            ->build();

        $expectedJson = '{"messageId":"msg_880e8400-e29b-41d4-a716-446655440003","messageType":"Event","action":"StatusNotification","timestamp":"2025-04-10T16:45:00.999Z","source":"Server","protocolVersion":"0.1.0","payload":{"stationId":"ST-002","bayId":"BAY-03","status":"available"}}';

        self::assertSame($expectedJson, $envelope->toJson());
    }

    #[Test]
    public function json_field_order_is_deterministic(): void
    {
        $buildEnvelope = fn () => MessageBuilder::request('Heartbeat')
            ->withMessageId(MessageId::fromString('cmd_990e8400-e29b-41d4-a716-446655440004'))
            ->withTimestamp(new DateTimeImmutable('2025-05-01T00:00:00.000+00:00'))
            ->withProtocolVersion(ProtocolVersion::fromString('0.1.0'))
            ->withPayload(['stationId' => 'ST-001'])
            ->build();

        $json1 = $buildEnvelope()->toJson();
        $json2 = $buildEnvelope()->toJson();
        $json3 = $buildEnvelope()->toJson();

        self::assertSame($json1, $json2);
        self::assertSame($json2, $json3);
    }

    #[Test]
    public function payload_with_url_does_not_escape_slashes(): void
    {
        $envelope = MessageBuilder::request('UpdateFirmware')
            ->withMessageId(MessageId::fromString('cmd_aa0e8400-e29b-41d4-a716-446655440005'))
            ->withTimestamp(new DateTimeImmutable('2025-06-01T00:00:00.000+00:00'))
            ->withProtocolVersion(ProtocolVersion::fromString('0.1.0'))
            ->withPayload([
                'stationId' => 'ST-001',
                'firmwareUrl' => 'https://example.com/firmware/v2.1.0/update.bin',
            ])
            ->build();

        $json = $envelope->toJson();

        self::assertStringContainsString('https://example.com/firmware/v2.1.0/update.bin', $json);
        self::assertStringNotContainsString('\\/', $json);
    }
}
