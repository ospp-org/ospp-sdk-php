<?php

declare(strict_types=1);

namespace Ospp\Protocol\Crypto;

use Ospp\Protocol\Enums\MessageType;

/**
 * The three messages that structurally cannot carry a verifiable MAC.
 *
 * Everything on the wire is signed (spec/06-security.md §5.1). Of the 47
 * message types in Chapter 03's catalogue, 44 are signed and 3 are exempt, and
 * the three are exempt because of what they ARE, not because of what they are
 * judged to be worth:
 *
 * - BootNotification REQUEST — it PRECEDES the session key. There is no key to
 *   sign with.
 * - BootNotification RESPONSE — it CARRIES the session key. A MAC computed with
 *   the key delivered inside the same message is cryptographically void: a
 *   forger who could substitute the message could substitute the key and
 *   produce a matching MAC.
 * - ConnectionLost (LWT) — it REPLACES the station. It is registered with the
 *   broker at CONNECT time and published after the station is gone; on a
 *   reconnect the station holds key N-1 while the server has rotated to key N,
 *   so a will-MAC is guaranteed stale on arrival.
 *
 * Integrity for all three comes from mTLS. Their exemption is unconditional: it
 * holds in `All` mode and is not something a deployment can turn off.
 *
 * This class replaces CriticalMessageRegistry. The `Critical` mode selected
 * nothing once everything was signed, and the 47-row per-message classification
 * table went with it (§5.1): "A rule requiring per-message judgement produced
 * three wrong answers out of forty-seven, each defensible when written, each
 * discovered later and separately."
 *
 * Keyed on (action, messageType), not on action alone: the BootNotification
 * REQUEST and its RESPONSE are exempt for two DIFFERENT structural reasons, and
 * ConnectionLost is exempt only as the broker's LWT EVENT.
 */
final class MessageSigningRegistry
{
    /** @var list<string> */
    private const STRUCTURAL_EXEMPTIONS = [
        'BootNotification:Request',
        'BootNotification:Response',
        'ConnectionLost:Event',
    ];

    public static function isStructurallyExempt(string $action, MessageType $messageType): bool
    {
        return in_array(self::key($action, $messageType), self::STRUCTURAL_EXEMPTIONS, true);
    }

    /**
     * @return list<string>
     */
    public static function allStructuralExemptions(): array
    {
        return self::STRUCTURAL_EXEMPTIONS;
    }

    public static function count(): int
    {
        return count(self::STRUCTURAL_EXEMPTIONS);
    }

    private static function key(string $action, MessageType $messageType): string
    {
        return $action.':'.$messageType->value;
    }
}
