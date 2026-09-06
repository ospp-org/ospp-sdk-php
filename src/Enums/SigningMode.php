<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

use Ospp\Protocol\Crypto\MessageSigningRegistry;

/**
 * DEPRECATED at spec 0.34.0, and RETAINED for one reason only.
 *
 * `MessageSigningMode` is no longer a configuration key: it was withdrawn from the
 * Chapter 08 registry, which went 29 keys to 28. Signing is **unconditional** — every
 * MQTT message MUST carry a `mac` except the three STRUCTURAL exemptions of §5.6, and
 * those are exempt because of *when they happen*, never because of a choice.
 *
 * WHY THE ENUM SURVIVES ANYWAY. `boot-notification-request` still carries an OPTIONAL
 * `messageSigningMode` field, itself deprecated and deliberately retained: that schema is
 * `additionalProperties: false` and stations already send the field, so removing it would
 * refuse their boot. This enum is that field's type and nothing else.
 *
 * The only conforming value is now `All`. A station reporting `None` is not selecting a
 * mode — it is announcing that every non-exempt message it sends will be refused `1013`.
 *
 * WHAT TO CALL INSTEAD. `requiresMac()` and `requiresMacVerification()` below are
 * deprecated with the key. They dispatch on a mode that no longer exists, so a caller
 * holding `None` gets `false` and would skip a check the protocol requires. Ask
 * `MessageSigningRegistry::isStructurallyExempt($action, $messageType)` directly: it is
 * the whole of the question now
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
    /** @deprecated 0.33.0 Signing is unconditional; use MessageSigningRegistry::isStructurallyExempt(). */
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
    /** @deprecated 0.33.0 Signing is unconditional; use MessageSigningRegistry::isStructurallyExempt(). */
    public function requiresMacVerification(string $action, MessageType $messageType): bool
    {
        return $this->requiresMac($action, $messageType);
    }
}
