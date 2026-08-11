<?php

declare(strict_types=1);

namespace Ospp\Protocol\Crypto;

/**
 * OSPP Canonical Form — spec/06-security.md §4.8.
 *
 * 1. Recursively sort object keys lexicographically by UTF-8 byte order.
 * 2. Serialize compactly (no insignificant whitespace).
 * 3. Canonical scalar forms.
 * 4. Encode as UTF-8 bytes.
 *
 * **An empty JSON object is `{}` and an empty JSON array is `[]`, and this
 * class must be able to emit both.** PHP's `[]` is ambiguous between the two —
 * `json_encode([])` is `[]` — so a payload that is an empty OBJECT cannot be
 * expressed as a PHP array at all. Pass a `\stdClass` for it.
 *
 * This is not a hypothetical. `heartbeat-request.schema.json` declares
 * `"properties": {}` with `additionalProperties: false`, so a Heartbeat REQUEST
 * payload is EXACTLY `{}` — and Heartbeat is one of the message types the
 * signing arc newly requires to be MAC'd. Emitting `"payload":[]` where the
 * other reference implementation emits `"payload":{}` produces a different
 * canonical byte sequence and therefore a different MAC, on the message a
 * station sends most often. The server rejects every heartbeat, CORE-007's
 * timeout fires on a healthy station, and CORE-008 marks its bays `Unknown`.
 *
 * §4.8.1 does not state this, because it does not need to: JSON already
 * distinguishes the two container types and the schema says which one the
 * payload is. What was missing was the ability of this class to say it.
 *
 * **`JSON_UNESCAPED_LINE_TERMINATORS` is load-bearing — do not drop it as
 * redundant next to `JSON_UNESCAPED_UNICODE`.** It is not redundant. §4.8.1
 * step 3 admits exactly three reasons to escape — control characters, `"` and
 * `\` — and requires every other character to be "emitted literally". U+2028
 * LINE SEPARATOR and U+2029 PARAGRAPH SEPARATOR are none of the three: they are
 * Unicode categories Zl and Zp, not control characters, and JSON itself only
 * mandates escaping below U+0020. PHP escapes them anyway, and keeps escaping
 * them under `JSON_UNESCAPED_UNICODE`, because the flag's author was protecting
 * a *JavaScript* consumer — before ES2019 those two bytes were illegal inside a
 * JS string literal, so `eval`-style parsers broke on them. That is a hazard of
 * one host language, and canonical form is not written for one host language.
 *
 * The divergence is real and it is one-directional: `sdk-ts` emits both
 * literally, because `JSON.stringify` never escaped them. So the same message
 * canonicalized either side of the wire produced different bytes, hence
 * different MACs, and the receiver rejected it. The 33 free-string sites on the
 * signed path (§5.6 — 44 of 47 message types are signed) all carry the
 * exposure; `messageId` and `action` carry it on *every* message, since the
 * envelope constrains both only by `maxLength`.
 */
final class CanonicalJsonSerializer
{
    /**
     * Serialize data to canonical JSON.
     *
     * @param  array<string, mixed>|\stdClass  $data
     *
     * @throws \JsonException
     */
    public function serialize(array|\stdClass $data): string
    {
        return json_encode(
            $this->recursiveKeySort($data),
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_LINE_TERMINATORS
            | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Sort keys at every level, preserving the array/object distinction.
     *
     * A `\stdClass` stays a `\stdClass` — including an empty one, which is the
     * whole point — and an array stays an array.
     */
    private function recursiveKeySort(mixed $data): mixed
    {
        if ($data instanceof \stdClass) {
            $properties = get_object_vars($data);
            ksort($properties, SORT_STRING);

            $sorted = new \stdClass();
            foreach ($properties as $key => $value) {
                $sorted->{$key} = $this->recursiveKeySort($value);
            }

            return $sorted;
        }

        if (! is_array($data)) {
            return $data;
        }

        // Only sort associative arrays (objects); preserve sequential order.
        if (! array_is_list($data)) {
            ksort($data, SORT_STRING);
        }

        foreach ($data as $key => $value) {
            $data[$key] = $this->recursiveKeySort($value);
        }

        return $data;
    }
}
