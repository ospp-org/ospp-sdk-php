<?php

declare(strict_types=1);

namespace Ospp\Protocol\Envelope;

use DateTimeImmutable;
use Ospp\Protocol\Enums\MessageType;
use Ospp\Protocol\ValueObjects\MessageId;
use Ospp\Protocol\ValueObjects\ProtocolVersion;

final readonly class MessageEnvelope
{
    public function __construct(
        public MessageId $messageId,
        public MessageType $messageType,
        public string $action,
        public DateTimeImmutable $timestamp,
        public string $source,
        public ProtocolVersion $protocolVersion,
        /** @var array<string, mixed> */
        public array $payload,
        public ?string $mac = null,
    ) {}

    public function isSigned(): bool
    {
        return $this->mac !== null;
    }

    public function expectsResponse(): bool
    {
        return $this->messageType === MessageType::REQUEST;
    }

    public function isEvent(): bool
    {
        return $this->messageType === MessageType::EVENT;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'messageId' => $this->messageId->value,
            'messageType' => $this->messageType->value,
            'action' => $this->action,
            'timestamp' => $this->timestamp->format('Y-m-d\TH:i:s.v\Z'),
            'source' => $this->source,
            'protocolVersion' => $this->protocolVersion->value,
            'payload' => $this->payload,
        ];

        if ($this->mac !== null) {
            $data['mac'] = $this->mac;
        }

        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function getPayloadStationId(): ?string
    {
        $stationId = $this->payload['stationId'] ?? null;

        return is_string($stationId) ? $stationId : null;
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
}
