<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

use Ospp\Protocol\Crypto\MessageSigningRegistry;

/**
 * The `MessageSigningMode` configuration key — spec/06-security.md §5.1.
 *
 * Two modes, and the default is `All`:
 *
 * - `All` (default) — HMAC on every MQTT message except the three structural
 *   exemptions of §5.6. Every deployment.
 * - `None` — no HMAC, TLS-only integrity. Development and test harnesses only.
 *   It exists so a test suite can exercise the message layer without a
 *   key-management fixture, and for no other reason. It MUST NOT be used in
 *   production.
 *
 * `Critical` is REMOVED rather than deprecated: with everything signed it
 * selected nothing, and the protocol is unreleased, so there is no installed
 * base a compatibility window would serve.
 *
 * The mode is `Static` — it takes effect at the next reboot, not immediately.
 * It is bound to the session key, which is issued at boot; a mid-session change
 * would leave one peer signing and the other not, and with both directions
 * failing closed (§5.7) the station goes silent both ways.
 *
 * Both values are PascalCase. Lowercase spellings that appeared in three places
 * were drift, not an alternative form, and a receiver MUST NOT accept them —
 * which a backed enum gives for free via {@see self::tryFrom()}.
 */
enum SigningMode: string
{
    case ALL = 'All';
    case NONE = 'None';

    /** §5.1: "`All` **(default)**". */
    public static function default(): self
    {
        return self::ALL;
    }

    /**
     * Must this message carry a `mac`?
     *
     * The three structural exemptions are checked FIRST and hold in every mode:
     * "Their exemption is unconditional: it holds in `All` mode, and it is not
     * something a deployment can turn off" (§5.6).
     *
     * Otherwise `All` signs everything — there is no per-message judgement left,
     * so an action this SDK has never heard of is signed rather than exempted.
     */
    public function requiresMac(string $action, MessageType $messageType): bool
    {
        if (MessageSigningRegistry::isStructurallyExempt($action, $messageType)) {
            return false;
        }

        return match ($this) {
            self::ALL => true,
            self::NONE => false,
        };
    }

    /**
     * The verification side of the same question.
     *
     * Deliberately identical: §5.7 makes the two paths the same condition read
     * from two ends, and a receiver that expected a MAC the sender did not owe
     * would reject conforming traffic.
     */
    public function requiresMacVerification(string $action, MessageType $messageType): bool
    {
        return $this->requiresMac($action, $messageType);
    }
}
