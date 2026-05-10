<?php

declare(strict_types=1);

namespace Ospp\Protocol\ValueObjects;

use InvalidArgumentException;

final readonly class MessageId implements \JsonSerializable, \Stringable
{
    public function __construct(
        public string $value,
    ) {
        if ($value === '') {
            throw new InvalidArgumentException('MessageId cannot be empty.');
        }

        if (mb_strlen($value) > 64) {
            throw new InvalidArgumentException(
                sprintf('MessageId must be at most 64 characters, got %d.', mb_strlen($value)),
            );
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Generate a new unique MessageId with the given prefix.
     *
     * The prefix is a SHOULD-only convention per spec/spec/03-messages.md
     * (§ messageId prefixes table). Implementations MUST NOT rely on the
     * prefix for routing — use the `action` field. Prefix is preserved here
     * for human readability and log filtering only.
     *
     * @param  string  $prefix  Conventional prefix (e.g., 'msg_', 'cmd_'); default 'msg_'
     */
    public static function generate(string $prefix = 'msg_'): self
    {
        $bytes = random_bytes(16);

        // Set version 4 (bits 48-51)
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        // Set variant (bits 64-65)
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $uuid = sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6)),
        );

        return new self($prefix . $uuid);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
