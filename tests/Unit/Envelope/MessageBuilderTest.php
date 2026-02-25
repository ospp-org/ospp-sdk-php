<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Envelope;

use DateTimeImmutable;
use InvalidArgumentException;
use Ospp\Protocol\Enums\MessageType;
use Ospp\Protocol\Envelope\MessageBuilder;
use Ospp\Protocol\Envelope\MessageEnvelope;
use Ospp\Protocol\ValueObjects\MessageId;
use Ospp\Protocol\ValueObjects\ProtocolVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MessageBuilderTest extends TestCase
{
    // ---------------------------------------------------------------
    // response() factory
    // ---------------------------------------------------------------

    #[Test]
    public function responseCreatesResponseWithServerSource(): void
    {
        $envelope = MessageBuilder::response('BootNotification')->build();

        self::assertSame(MessageType::RESPONSE, $envelope->messageType);
        self::assertSame('server', $envelope->source);
        self::assertSame('BootNotification', $envelope->action);
    }

    #[Test]
    public function responseGeneratesMsgPrefix(): void
    {
        $envelope = MessageBuilder::response('Heartbeat')->build();

        self::assertStringStartsWith('msg_', $envelope->messageId->value);
    }

    // ---------------------------------------------------------------
    // request() factory
    // ---------------------------------------------------------------

    #[Test]
    public function requestCreatesRequestWithServerSource(): void
    {
        $envelope = MessageBuilder::request('StartService')->build();

        self::assertSame(MessageType::REQUEST, $envelope->messageType);
        self::assertSame('server', $envelope->source);
        self::assertSame('StartService', $envelope->action);
    }

    #[Test]
    public function requestGeneratesCmdPrefix(): void
    {
        $envelope = MessageBuilder::request('StopService')->build();

        self::assertStringStartsWith('cmd_', $envelope->messageId->value);
    }

    // ---------------------------------------------------------------
    // event() factory
    // ---------------------------------------------------------------

    #[Test]
    public function eventCreatesEventWithServerSource(): void
    {
        $envelope = MessageBuilder::event('StatusNotification')->build();

        self::assertSame(MessageType::EVENT, $envelope->messageType);
        self::assertSame('server', $envelope->source);
        self::assertSame('StatusNotification', $envelope->action);
    }

    #[Test]
    public function eventGeneratesMsgPrefix(): void
    {
        $envelope = MessageBuilder::event('MeterValues')->build();

        self::assertStringStartsWith('msg_', $envelope->messageId->value);
    }

    // ---------------------------------------------------------------
    // stationRequest() factory
    // ---------------------------------------------------------------

    #[Test]
    public function stationRequestCreatesRequestWithStationSource(): void
    {
        $envelope = MessageBuilder::stationRequest('BootNotification')->build();

        self::assertSame(MessageType::REQUEST, $envelope->messageType);
        self::assertSame('station', $envelope->source);
        self::assertSame('BootNotification', $envelope->action);
    }

    #[Test]
    public function stationRequestGeneratesCmdPrefix(): void
    {
        $envelope = MessageBuilder::stationRequest('Heartbeat')->build();

        self::assertStringStartsWith('cmd_', $envelope->messageId->value);
    }

    // ---------------------------------------------------------------
    // stationEvent() factory
    // ---------------------------------------------------------------

    #[Test]
    public function stationEventCreatesEventWithStationSource(): void
    {
        $envelope = MessageBuilder::stationEvent('StatusNotification')->build();

        self::assertSame(MessageType::EVENT, $envelope->messageType);
        self::assertSame('station', $envelope->source);
        self::assertSame('StatusNotification', $envelope->action);
    }

    #[Test]
    public function stationEventGeneratesMsgPrefix(): void
    {
        $envelope = MessageBuilder::stationEvent('MeterValues')->build();

        self::assertStringStartsWith('msg_', $envelope->messageId->value);
    }

    // ---------------------------------------------------------------
    // build() — auto-fills defaults
    // ---------------------------------------------------------------

    #[Test]
    public function buildFillsTimestampWhenNotSet(): void
    {
        $before = new DateTimeImmutable('now');
        $envelope = MessageBuilder::response('Heartbeat')->build();
        $after = new DateTimeImmutable('now');

        self::assertGreaterThanOrEqual($before, $envelope->timestamp);
        self::assertLessThanOrEqual($after, $envelope->timestamp);
    }

    #[Test]
    public function buildFillsProtocolVersionWhenNotSet(): void
    {
        $envelope = MessageBuilder::response('Heartbeat')->build();

        self::assertSame('1.0.0', $envelope->protocolVersion->value);
    }

    #[Test]
    public function buildFillsMessageIdWhenNotSet(): void
    {
        $envelope = MessageBuilder::response('Heartbeat')->build();

        self::assertNotEmpty($envelope->messageId->value);
    }

    #[Test]
    public function buildDefaultsPayloadToEmptyArray(): void
    {
        $envelope = MessageBuilder::response('Heartbeat')->build();

        self::assertSame([], $envelope->payload);
    }

    #[Test]
    public function buildDefaultsMacToNull(): void
    {
        $envelope = MessageBuilder::response('Heartbeat')->build();

        self::assertNull($envelope->mac);
    }

    // ---------------------------------------------------------------
    // build() — prefix logic (REQUEST = cmd_, others = msg_)
    // ---------------------------------------------------------------

    #[Test]
    public function buildRequestGetsCmdPrefixAutomatically(): void
    {
        $envelope = MessageBuilder::request('GetConfiguration')->build();

        self::assertStringStartsWith('cmd_', $envelope->messageId->value);
    }

    #[Test]
    public function buildResponseGetsMsgPrefixAutomatically(): void
    {
        $envelope = MessageBuilder::response('GetConfiguration')->build();

        self::assertStringStartsWith('msg_', $envelope->messageId->value);
    }

    #[Test]
    public function buildEventGetsMsgPrefixAutomatically(): void
    {
        $envelope = MessageBuilder::event('StatusNotification')->build();

        self::assertStringStartsWith('msg_', $envelope->messageId->value);
    }

    // ---------------------------------------------------------------
    // withPayload()
    // ---------------------------------------------------------------

    #[Test]
    public function withPayloadSetsPayload(): void
    {
        $payload = ['stationId' => 'ST-001', 'model' => 'X100'];

        $envelope = MessageBuilder::response('BootNotification')
            ->withPayload($payload)
            ->build();

        self::assertSame($payload, $envelope->payload);
    }

    #[Test]
    public function withPayloadReplacesExistingPayload(): void
    {
        $envelope = MessageBuilder::response('Heartbeat')
            ->withPayload(['first' => true])
            ->withPayload(['second' => true])
            ->build();

        self::assertSame(['second' => true], $envelope->payload);
    }

    // ---------------------------------------------------------------
    // withMac()
    // ---------------------------------------------------------------

    #[Test]
    public function withMacSetsMac(): void
    {
        $envelope = MessageBuilder::response('Heartbeat')
            ->withMac('hmac-sha256-value')
            ->build();

        self::assertSame('hmac-sha256-value', $envelope->mac);
    }

    // ---------------------------------------------------------------
    // withMessageId()
    // ---------------------------------------------------------------

    #[Test]
    public function withMessageIdOverridesAutoGenerated(): void
    {
        $customId = MessageId::fromString('msg_custom-id-value-here-abcdef123456');

        $envelope = MessageBuilder::response('Heartbeat')
            ->withMessageId($customId)
            ->build();

        self::assertTrue($customId->equals($envelope->messageId));
    }

    #[Test]
    public function withMessageIdPrefixNotOverriddenByBuildLogic(): void
    {
        // Even for a REQUEST, if we explicitly set a msg_ id, it stays
        $customId = MessageId::fromString('msg_custom-id-value-here-abcdef123456');

        $envelope = MessageBuilder::request('StartService')
            ->withMessageId($customId)
            ->build();

        self::assertStringStartsWith('msg_', $envelope->messageId->value);
    }

    // ---------------------------------------------------------------
    // withTimestamp()
    // ---------------------------------------------------------------

    #[Test]
    public function withTimestampOverridesAutoGenerated(): void
    {
        $timestamp = new DateTimeImmutable('2020-01-01T00:00:00.000Z');

        $envelope = MessageBuilder::response('Heartbeat')
            ->withTimestamp($timestamp)
            ->build();

        self::assertSame($timestamp, $envelope->timestamp);
    }

    // ---------------------------------------------------------------
    // withProtocolVersion()
    // ---------------------------------------------------------------

    #[Test]
    public function withProtocolVersionOverridesDefault(): void
    {
        $version = new ProtocolVersion(2, 1, 0);

        $envelope = MessageBuilder::response('Heartbeat')
            ->withProtocolVersion($version)
            ->build();

        self::assertTrue($version->equals($envelope->protocolVersion));
    }

    // ---------------------------------------------------------------
    // correlatedTo()
    // ---------------------------------------------------------------

    #[Test]
    public function correlatedToReusesRequestMessageId(): void
    {
        $request = MessageBuilder::stationRequest('BootNotification')
            ->withPayload(['stationId' => 'ST-001'])
            ->build();

        $response = MessageBuilder::response('BootNotification')
            ->correlatedTo($request)
            ->build();

        self::assertTrue($request->messageId->equals($response->messageId));
    }

    #[Test]
    public function correlatedToPreservesOtherBuilderSettings(): void
    {
        $request = MessageBuilder::stationRequest('BootNotification')->build();

        $payload = ['status' => 'Accepted', 'interval' => 300];
        $response = MessageBuilder::response('BootNotification')
            ->withPayload($payload)
            ->correlatedTo($request)
            ->build();

        self::assertSame('server', $response->source);
        self::assertSame(MessageType::RESPONSE, $response->messageType);
        self::assertSame($payload, $response->payload);
    }

    // ---------------------------------------------------------------
    // build() — validation errors
    // ---------------------------------------------------------------

    #[Test]
    public function buildThrowsOnEmptyAction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action is required to build a MessageEnvelope.');

        MessageBuilder::response('')->build();
    }

    #[Test]
    public function buildThrowsWhenMessageTypeIsNull(): void
    {
        // The private constructor always receives a messageType from the factory methods,
        // but the guard exists as a defensive check. We use Reflection to reach it.
        $builder = MessageBuilder::response('Heartbeat');

        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('messageType');

        // Create a new builder clone with messageType set to null via Reflection
        // Since the class is readonly, we need to create a raw instance
        $rawBuilder = $reflection->newInstanceWithoutConstructor();

        // Copy all properties from the original builder
        foreach ($reflection->getProperties() as $prop) {
            $prop->setAccessible(true);
            if ($prop->getName() === 'messageType') {
                $prop->setValue($rawBuilder, null);
            } elseif ($prop->getName() === 'action') {
                $prop->setValue($rawBuilder, 'Heartbeat');
            } else {
                $prop->setValue($rawBuilder, $prop->getValue($builder));
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MessageType is required to build a MessageEnvelope.');

        $rawBuilder->build();
    }

    // ---------------------------------------------------------------
    // Immutability — chained calls return new builder instances
    // ---------------------------------------------------------------

    #[Test]
    public function chainedCallsDoNotMutateOriginalBuilder(): void
    {
        $builder = MessageBuilder::response('Heartbeat');
        $withPayload = $builder->withPayload(['key' => 'value']);
        $withMac = $builder->withMac('mac-value');

        $envelopeOriginal = $builder->build();
        $envelopeWithPayload = $withPayload->build();
        $envelopeWithMac = $withMac->build();

        self::assertSame([], $envelopeOriginal->payload);
        self::assertNull($envelopeOriginal->mac);
        self::assertSame(['key' => 'value'], $envelopeWithPayload->payload);
        self::assertSame('mac-value', $envelopeWithMac->mac);
    }

    // ---------------------------------------------------------------
    // Full integration — all builder methods combined
    // ---------------------------------------------------------------

    #[Test]
    public function fullBuilderChainProducesCorrectEnvelope(): void
    {
        $messageId = MessageId::generate('cmd_');
        $timestamp = new DateTimeImmutable('2025-03-01T14:00:00.000Z');
        $version = new ProtocolVersion(1, 1, 0);
        $payload = ['stationId' => 'ST-001', 'bayId' => 1];

        $envelope = MessageBuilder::request('StartService')
            ->withMessageId($messageId)
            ->withTimestamp($timestamp)
            ->withProtocolVersion($version)
            ->withPayload($payload)
            ->withMac('full-chain-mac')
            ->build();

        self::assertTrue($messageId->equals($envelope->messageId));
        self::assertSame(MessageType::REQUEST, $envelope->messageType);
        self::assertSame('StartService', $envelope->action);
        self::assertSame($timestamp, $envelope->timestamp);
        self::assertSame('server', $envelope->source);
        self::assertTrue($version->equals($envelope->protocolVersion));
        self::assertSame($payload, $envelope->payload);
        self::assertSame('full-chain-mac', $envelope->mac);
    }
}
