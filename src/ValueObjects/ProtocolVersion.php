<?php

declare(strict_types=1);

namespace Ospp\Protocol\ValueObjects;

use InvalidArgumentException;

/**
 * Semantic version for OSPP protocol (e.g., "0.1.0").
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
        // Constraints from spec/schemas/common/mqtt-envelope.schema.json
        // (protocolVersion field): pattern "^\d+\.\d+\.\d+$", maxLength 32.
        if (mb_strlen($version) > 32) {
            throw new InvalidArgumentException(
                sprintf('Protocol version too long (max 32 chars): "%s".', $version),
            );
        }

        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Invalid protocol version format: "%s". Expected "MAJOR.MINOR.PATCH" with non-negative integers.', $version),
            );
        }

        [$major, $minor, $patch] = explode('.', $version);

        return new self(
            major: (int) $major,
            minor: (int) $minor,
            patch: (int) $patch,
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

    /**
     * Returns the OSPP protocol wire version that envelopes will carry by default.
     *
     * The returned value is the spec-mandated wire `protocolVersion` field
     * (per `spec/02-transport.md` and `spec/08-configuration.md` `ProtocolVersion`
     * configuration-key default), NOT the SDK package version. Spec v0.4.0
     * deliberately did not bump the wire field; future spec minor cycles
     * will revisit per-message envelope version discrimination.
     *
     * Frameworks may override via {@see self::setDefaultResolver()}.
     */
    public static function default(): self
    {
        $version = self::$defaultResolver !== null
            ? (self::$defaultResolver)()
            : '0.2.1';

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
