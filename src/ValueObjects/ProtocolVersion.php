<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\ValueObjects;

use InvalidArgumentException;

/**
 * Semantic version for OSPP protocol (e.g., "1.0.0").
 * Immutable. Supports major version compatibility checking.
 */
final readonly class ProtocolVersion implements \JsonSerializable, \Stringable
{
    public string $value;

    public function __construct(
        public int $major,
        public int $minor,
        public int $patch,
    ) {
        if ($major < 0 || $minor < 0 || $patch < 0) {
            throw new InvalidArgumentException('Version components must be non-negative.');
        }

        $this->value = "{$major}.{$minor}.{$patch}";
    }

    public static function fromString(string $version): self
    {
        $parts = explode('.', $version);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException(
                sprintf('Invalid protocol version format: "%s". Expected "MAJOR.MINOR.PATCH".', $version),
            );
        }

        return new self(
            major: (int) $parts[0],
            minor: (int) $parts[1],
            patch: (int) $parts[2],
        );
    }

    public static function default(string $version = '1.0.0'): self
    {
        return self::fromString($version);
    }

    /**
     * OSPP compatibility rule: same MAJOR version is compatible.
     * Different MAJOR version triggers 1007 PROTOCOL_VERSION_MISMATCH.
     */
    public function isCompatibleWith(self $other): bool
    {
        return $this->major === $other->major;
    }

    public function equals(self $other): bool
    {
        return $this->major === $other->major
            && $this->minor === $other->minor
            && $this->patch === $other->patch;
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
