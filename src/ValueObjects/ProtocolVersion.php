<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\ValueObjects;

use InvalidArgumentException;

/**
 * Semantic version for OSPP protocol (e.g., "1.0.0").
 * Immutable. Supports major version compatibility checking.
 */
final class ProtocolVersion implements \JsonSerializable, \Stringable
{
    public readonly string $value;

    public function __construct(
        public readonly int $major,
        public readonly int $minor,
        public readonly int $patch,
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

    private static ?\Closure $defaultResolver = null;

    /**
     * Set a custom resolver for the default protocol version.
     * Allows frameworks to provide config-driven defaults (e.g., Laravel config()).
     */
    public static function setDefaultResolver(?\Closure $resolver): void
    {
        self::$defaultResolver = $resolver;
    }

    public static function default(): self
    {
        $version = self::$defaultResolver !== null
            ? (self::$defaultResolver)()
            : '1.0.0';

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
