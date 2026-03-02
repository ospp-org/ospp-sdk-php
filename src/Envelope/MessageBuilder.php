<?php

declare(strict_types=1);

namespace Ospp\Protocol\Envelope;

use DateTimeImmutable;
use InvalidArgumentException;
use Ospp\Protocol\Enums\MessageType;
use Ospp\Protocol\ValueObjects\MessageId;
use Ospp\Protocol\ValueObjects\ProtocolVersion;

final readonly class MessageBuilder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        private ?MessageId $messageId,
        private ?MessageType $messageType,
        private ?string $action,
        private ?DateTimeImmutable $timestamp,
        private string $source,
        private ?ProtocolVersion $protocolVersion,
        private array $payload,
        private ?string $mac,
    ) {}

    public static function response(string $action): self
    {
        return new self(
            messageId: null,
            messageType: MessageType::RESPONSE,
            action: $action,
            timestamp: null,
            source: 'Server',
            protocolVersion: null,
            payload: [],
            mac: null,
        );
    }

    public static function request(string $action): self
    {
        return new self(
            messageId: null,
            messageType: MessageType::REQUEST,
            action: $action,
            timestamp: null,
            source: 'Server',
            protocolVersion: null,
            payload: [],
            mac: null,
        );
    }

    public static function event(string $action): self
    {
        return new self(
            messageId: null,
            messageType: MessageType::EVENT,
            action: $action,
            timestamp: null,
            source: 'Server',
            protocolVersion: null,
            payload: [],
            mac: null,
        );
    }

    public static function stationRequest(string $action): self
    {
        return new self(
            messageId: null,
            messageType: MessageType::REQUEST,
            action: $action,
            timestamp: null,
            source: 'Station',
            protocolVersion: null,
            payload: [],
            mac: null,
        );
    }

    public static function stationEvent(string $action): self
    {
        return new self(
            messageId: null,
            messageType: MessageType::EVENT,
            action: $action,
            timestamp: null,
            source: 'Station',
            protocolVersion: null,
            payload: [],
            mac: null,
        );
    }

    public function withMessageId(MessageId $messageId): self
    {
        return new self(
            messageId: $messageId,
            messageType: $this->messageType,
            action: $this->action,
            timestamp: $this->timestamp,
            source: $this->source,
            protocolVersion: $this->protocolVersion,
            payload: $this->payload,
            mac: $this->mac,
        );
    }

    public function correlatedTo(MessageEnvelope $request): self
    {
        return new self(
            messageId: $request->messageId,
            messageType: $this->messageType,
            action: $this->action,
            timestamp: $this->timestamp,
            source: $this->source,
            protocolVersion: $this->protocolVersion,
            payload: $this->payload,
            mac: $this->mac,
        );
    }

    public function withTimestamp(DateTimeImmutable $timestamp): self
    {
        return new self(
            messageId: $this->messageId,
            messageType: $this->messageType,
            action: $this->action,
            timestamp: $timestamp,
            source: $this->source,
            protocolVersion: $this->protocolVersion,
            payload: $this->payload,
            mac: $this->mac,
        );
    }

    public function withProtocolVersion(ProtocolVersion $version): self
    {
        return new self(
            messageId: $this->messageId,
            messageType: $this->messageType,
            action: $this->action,
            timestamp: $this->timestamp,
            source: $this->source,
            protocolVersion: $version,
            payload: $this->payload,
            mac: $this->mac,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function withPayload(array $payload): self
    {
        return new self(
            messageId: $this->messageId,
            messageType: $this->messageType,
            action: $this->action,
            timestamp: $this->timestamp,
            source: $this->source,
            protocolVersion: $this->protocolVersion,
            payload: $payload,
            mac: $this->mac,
        );
    }

    public function withMac(string $mac): self
    {
        return new self(
            messageId: $this->messageId,
            messageType: $this->messageType,
            action: $this->action,
            timestamp: $this->timestamp,
            source: $this->source,
            protocolVersion: $this->protocolVersion,
            payload: $this->payload,
            mac: $mac,
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function build(): MessageEnvelope
    {
        if ($this->action === null || $this->action === '') {
            throw new InvalidArgumentException('Action is required to build a MessageEnvelope.');
        }

        if ($this->messageType === null) {
            throw new InvalidArgumentException('MessageType is required to build a MessageEnvelope.');
        }

        $messageId = $this->messageId ?? MessageId::generate(
            $this->messageType === MessageType::REQUEST ? 'cmd_' : 'msg_',
        );

        return new MessageEnvelope(
            messageId: $messageId,
            messageType: $this->messageType,
            action: $this->action,
            timestamp: $this->timestamp ?? new DateTimeImmutable('now'),
            source: $this->source,
            protocolVersion: $this->protocolVersion ?? ProtocolVersion::default(),
            payload: $this->payload,
            mac: $this->mac,
        );
    }
}
