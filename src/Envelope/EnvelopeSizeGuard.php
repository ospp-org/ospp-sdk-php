<?php

declare(strict_types=1);

namespace Ospp\Protocol\Envelope;

use InvalidArgumentException;

/**
 * The envelope cap — `spec/02-transport.md` §10.2.1.
 *
 * WHY THIS IS A CLASS AND NOT A `maxLength` SOMEWHERE.
 * ---------------------------------------------------
 * The bound is on the SERIALISED ENVELOPE, and JSON Schema has no keyword for the
 * length of a serialisation. It could not be pushed down into field bounds either:
 * measured over the 86 schemas in the spec repository, 37 of the 47 MQTT message
 * schemas admit a bounded serialisation and the other 10 carry 12 unbounded members
 * — 7 arrays with no `maxItems` and 5 open objects, which no `maxItems` can close.
 * So the cap is a normative rule an implementation enforces, and this is where this
 * SDK enforces it.
 *
 * WHY 64 512 AND NOT 65 536.
 * --------------------------
 * 65 536 is the MQTT *packet* ceiling — the `maximumPacketSize` a broker declares in
 * CONNACK, and the value `02-transport.md` §1.2 pins. A PUBLISH packet is the payload
 * PLUS its header, so an envelope sized at the packet ceiling produces a packet above
 * it and the broker drops what the emitter was told was legal. The 1 024 bytes held
 * back are the header allowance: 4 fixed + at most 151 topic (`topicPrefix` <= 64,
 * `stationId` <= 64) + 2 packet identifier + at most 128 of MQTT 5 properties = 285
 * at every one of those fields' own maxima, so the allowance is 3.6x the worst case.
 *
 * WHAT THE TWO ENTRY POINTS ARE FOR.
 * ----------------------------------
 *   - `assertWithinCap()` is the EMITTER half. §10.2.1 makes it a MUST NOT: the
 *     publisher is the only party that can measure an envelope before it exists on
 *     the wire, so this throws rather than returning a flag.
 *   - `exceeds()` is the RECEIVER half, and it takes a raw string on purpose. A
 *     receiver MAY refuse on serialised length alone, BEFORE parsing and BEFORE
 *     verifying `mac` — the only refusal §10.2.1 permits to precede MAC
 *     verification, because verifying a MAC means re-canonicalising the whole
 *     envelope and therefore holding all of it. That is the one check that does not
 *     have to enter the buffer it protects, which is the entire point of it.
 */
final class EnvelopeSizeGuard
{
    /**
     * Maximum bytes of a serialised MQTT envelope. `spec/02-transport.md` §10.2.1.
     */
    public const MAX_ENVELOPE_BYTES = 64512;

    /**
     * MQTT packet ceiling this cap sits inside, for callers that size a receive
     * buffer or configure a broker. `spec/02-transport.md` §1.2.
     */
    public const MQTT_MAX_PACKET_BYTES = 65536;

    /**
     * Byte length of a serialised envelope. `strlen()` counts bytes, not characters,
     * which is the unit the cap is stated in — a multi-byte `serviceName` costs what
     * it costs on the wire, not what it costs in code points.
     */
    public static function byteLength(string $serialised): int
    {
        return strlen($serialised);
    }

    /**
     * The receiver-side check. Safe to call on bytes that have not been parsed and
     * whose `mac` has not been verified — it reads only the length.
     */
    public static function exceeds(string $serialised): bool
    {
        return strlen($serialised) > self::MAX_ENVELOPE_BYTES;
    }

    /**
     * Bytes still available under the cap; negative when it is already exceeded.
     */
    public static function remaining(string $serialised): int
    {
        return self::MAX_ENVELOPE_BYTES - strlen($serialised);
    }

    /**
     * The emitter-side check. Throws rather than returning, because §10.2.1 states
     * the emitter obligation as a MUST NOT and a returned flag is a MUST NOT nobody
     * has to read.
     *
     * `$action` is threaded through only so the message names the message: an
     * oversized envelope is almost always one action's payload growing without a
     * bound of its own, and "UpdateServiceCatalog" in the exception text is the
     * difference between a report an operator can act on and one they cannot.
     */
    public static function assertWithinCap(string $serialised, string $action = ''): void
    {
        $length = strlen($serialised);

        if ($length <= self::MAX_ENVELOPE_BYTES) {
            return;
        }

        $subject = $action === '' ? 'envelope' : $action.' envelope';

        throw new InvalidArgumentException(sprintf(
            'Refusing to publish: %s is %d bytes, over the %d-byte envelope cap '
            .'(spec/02-transport.md §10.2.1) by %d. The MQTT packet ceiling is %d; '
            .'the difference is the PUBLISH header allowance.',
            $subject,
            $length,
            self::MAX_ENVELOPE_BYTES,
            $length - self::MAX_ENVELOPE_BYTES,
            self::MQTT_MAX_PACKET_BYTES,
        ));
    }
}
