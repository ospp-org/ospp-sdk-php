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
     * Is this version a member of the set the peer supports?
     *
     * VERSIONING.md: "**Negotiation is exact match.** At boot the station
     * declares one version in `BootNotification`'s envelope. The server holds a
     * **set** of versions it supports. If the declared version is a member of
     * that set, the server responds `Accepted`. If it is not, the server MUST
     * reject with error code `1007` (`PROTOCOL_VERSION_MISMATCH`) and MUST
     * include the `supportedVersions` array."
     *
     * This replaces `isCompatibleWith()`, which encoded "same MAJOR is
     * compatible". That rule is deleted rather than narrowed: MAJOR is `0` for
     * every version OSPP has shipped, so it classified `0.1.0` and `0.10.0` as
     * compatible while the pre-1.0 policy directly above it licences breaking
     * changes between `0.x` minors. The contradiction cost money — a `0.4.0`
     * station accepted by a `0.3.0` server delivers a full session and emits
     * `SessionEnded` with a `reason` the older schema rejects, and SessionEnded
     * is the sole billing source when no StopService was issued. Session
     * delivered, never billed, on a pairing the rule told the server to accept.
     *
     * An empty set supports nothing: a server that has configured no versions
     * accepts no station, rather than silently accepting every one.
     *
     * @param  iterable<self>  $supportedVersions  the peer's supported set
     */
    public function isSupportedBy(iterable $supportedVersions): bool
    {
        foreach ($supportedVersions as $supported) {
            if ($this->equals($supported)) {
                return true;
            }
        }

        return false;
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
