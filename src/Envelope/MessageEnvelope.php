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

    /**
     * Serialise for the wire, and refuse to hand back bytes no publisher may send.
     *
     * `spec/02-transport.md` §10.2.1 states the emitter obligation as a MUST NOT, and
     * the emitter is the only party that can honour it — it is the only one holding
     * the bytes before they exist on the wire. Enforcing here rather than at each
     * caller means the refusal cannot be forgotten at one of them.
     *
     * This is fail-closed by design and the alternative is worse than an exception:
     * an envelope over the cap is one the broker drops for exceeding its declared
     * `maximumPacketSize`, so the caller loses the message either way — the only
     * question is whether it learns why here or watches a PUBLISH disappear.
     *
     * @throws \InvalidArgumentException when the serialisation exceeds the envelope cap
     */
    public function toJson(): string
    {
        $json = json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        EnvelopeSizeGuard::assertWithinCap($json, $this->action);

        return $json;
    }

    /**
     * Serialised size in bytes, for a caller that wants to decide rather than be
     * refused — sizing a receive buffer, or trimming a catalog before it is built.
     */
    public function serializedByteLength(): int
    {
        return strlen(json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
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
