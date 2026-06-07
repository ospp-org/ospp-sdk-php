<?php

declare(strict_types=1);

namespace Ospp\Protocol\ValueObjects;

/**
 * UserSubject — canonical derivation of the OSPP user subject identifier
 * (`sub_*`) from a user UUID.
 *
 * Single source of truth across the OSPP ecosystem:
 *
 *   - Server-side pass issuance (`sub` field in the signed OfflinePass body).
 *   - Server-side reconcile-time revalidation (check #5 in
 *     `spec/profiles/reconciliation.md §6.1`: envelope `userId` vs the
 *     `sub` derived from the resolved pass's stored `user_id` UUID).
 *   - Any firmware, simulator, or alternative pass issuer that derives a
 *     `sub_*` independently from a user UUID — using the same rule keeps
 *     the wire form byte-identical across implementations.
 *
 * The rule is implicitly normative via the spec's `^sub_[a-zA-Z0-9]+$`
 * pattern on the OfflinePass `sub` field (`schemas/common/offline-pass.
 * schema.json` and the TS SDK `UserId` type doc-comment in
 * `src/types/common.ts`): a UUID-shaped string CANNOT contain hyphens
 * and satisfy the regex, so deriving `sub` from a UUID requires stripping
 * the hyphens. The spec prose does not call this out, but the schema
 * regex forces it.
 *
 * This class deliberately exposes a static helper (returning `string`)
 * rather than a wrapped value object. Pass bodies, MQTT envelopes, and
 * spec schemas all treat the value as a plain string; a wrapped VO with
 * `value` accessors would only add unwrapping noise at the dozens of
 * call sites in csms-server.
 *
 * Lifted from csms-server `App\Shared\ValueObjects\UserSub` in SDK
 * v0.5.3 (was csms-server-private prior to v0.5.3).
 */
final class UserSubject
{
    /**
     * Derive the `sub_*` form from a user UUID.
     *
     * Algorithm: strip every ASCII hyphen (0x2D) from the input, then
     * prefix with the literal `sub_`. Deterministic, salt-free, and
     * byte-identical to the TS SDK counterpart
     * (`@ospp/protocol` `UserSubject.fromUserId`).
     *
     * @param  string  $userId  User UUID (or any UUID-shaped identifier).
     *                          Empty string is allowed and yields `'sub_'`.
     * @return string  `sub_` + input with all hyphens removed.
     */
    public static function fromUserId(string $userId): string
    {
        return 'sub_'.str_replace('-', '', $userId);
    }
}
