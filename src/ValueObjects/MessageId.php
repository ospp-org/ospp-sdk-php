<?php

declare(strict_types=1);

namespace Ospp\Protocol\ValueObjects;

use InvalidArgumentException;

final readonly class MessageId implements \JsonSerializable, \Stringable
{
    private const VALID_PREFIXES = ['msg_', 'cmd_', 'err_'];

    public function __construct(
        public string $value,
    ) {
        if ($value === '') {
            throw new InvalidArgumentException('MessageId cannot be empty.');
        }

        $hasValidPrefix = false;
        foreach (self::VALID_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                $hasValidPrefix = true;
                break;
            }
        }

        if (! $hasValidPrefix) {
            throw new InvalidArgumentException(
                sprintf(
                    'MessageId must start with one of [%s], got: "%s".',
                    implode(', ', self::VALID_PREFIXES),
                    $value,
                ),
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
     * @param  string  $prefix  One of 'msg_', 'cmd_', 'err_' (default: 'msg_')
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
