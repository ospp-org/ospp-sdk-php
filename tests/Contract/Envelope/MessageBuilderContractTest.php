<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Envelope;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Ospp\Protocol\Envelope\MessageBuilder;
use Ospp\Protocol\ValueObjects\MessageId;
use Ospp\Protocol\ValueObjects\ProtocolVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MessageBuilderContractTest extends TestCase
{
    #[Test]
    public function REQUEST_type_gets_cmd_prefix(): void
    {
        $envelope = MessageBuilder::request('TestAction')->build();

        self::assertStringStartsWith('cmd_', $envelope->messageId->value);
    }

    #[Test]
    public function RESPONSE_type_gets_msg_prefix(): void
    {
        $envelope = MessageBuilder::response('TestAction')->build();

        self::assertStringStartsWith('msg_', $envelope->messageId->value);
    }

    #[Test]
    public function EVENT_type_gets_msg_prefix(): void
    {
        $envelope = MessageBuilder::event('TestAction')->build();

        self::assertStringStartsWith('msg_', $envelope->messageId->value);
    }

    #[Test]
    public function server_source_for_request_response_event(): void
    {
        $request = MessageBuilder::request('TestAction')->build();
        $response = MessageBuilder::response('TestAction')->build();
        $event = MessageBuilder::event('TestAction')->build();

        self::assertSame('server', $request->source);
        self::assertSame('server', $response->source);
        self::assertSame('server', $event->source);
    }

    #[Test]
    public function station_source_for_stationRequest_stationEvent(): void
    {
        $stationRequest = MessageBuilder::stationRequest('BootNotification')->build();
        $stationEvent = MessageBuilder::stationEvent('StatusNotification')->build();

        self::assertSame('station', $stationRequest->source);
        self::assertSame('station', $stationEvent->source);
    }

    #[Test]
    public function default_protocolVersion_is_1_0_0(): void
    {
        $envelope = MessageBuilder::request('TestAction')->build();

        self::assertSame('1.0.0', $envelope->protocolVersion->value);
    }

    #[Test]
    public function default_timestamp_is_UTC_now(): void
    {
        $before = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $envelope = MessageBuilder::request('TestAction')->build();
        $after = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $envelopeTimestamp = $envelope->timestamp->getTimestamp();

        self::assertGreaterThanOrEqual($before->getTimestamp() - 2, $envelopeTimestamp);
        self::assertLessThanOrEqual($after->getTimestamp() + 2, $envelopeTimestamp);
    }

    #[Test]
    public function correlatedTo_preserves_messageId_from_request(): void
    {
        $request = MessageBuilder::request('StartService')
            ->withMessageId(MessageId::fromString('cmd_550e8400-e29b-41d4-a716-446655440000'))
            ->build();

        $response = MessageBuilder::response('StartService')
            ->correlatedTo($request)
            ->build();

        self::assertSame($request->messageId->value, $response->messageId->value);
        self::assertSame('cmd_550e8400-e29b-41d4-a716-446655440000', $response->messageId->value);
    }

    #[Test]
    public function builder_is_immutable(): void
    {
        $b1 = MessageBuilder::request('TestAction');
        $b2 = $b1->withPayload(['key' => 'value']);

        $envelope1 = $b1->build();
        $envelope2 = $b2->build();

        self::assertSame([], $envelope1->payload);
        self::assertSame(['key' => 'value'], $envelope2->payload);
    }

    #[Test]
    public function build_with_empty_action_throws_InvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action is required');

        MessageBuilder::request('')->build();
    }
}
