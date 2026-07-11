# Changelog

All notable changes to the OSPP SDK for PHP will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [0.7.0] — 2026-07-10

TLS 1.2 floor (lockstep, ADR-011). Re-vendors `schemas/provisioning-response.schema.json`
at spec **v0.7.0**: the MQTT `tlsVersion` field widens from `["1.3"]` to
`["1.2","1.3"]` (default `"1.3"` → `"1.2"`) and its semantics change from "the
TLS version" to a **minimum floor** — the station must support this version;
the broker accepts it or higher. This lowers the MQTT/mTLS transport floor from
TLS-1.3-only to TLS 1.2+ (TLS 1.3 recommended, negotiated when both peers
support it), admitting cellular modems capped at TLS 1.2 (e.g. SIMCom
A7608E-H). Sub-1.2 remains rejected, 0-RTT remains forbidden, mTLS unchanged.
`.spec-ref` → `v0.7.0`.

No PHP code change: the SDK carries the `tlsVersion` contract only through the
JSON schema (no hand-written type/enum), and the 0.7.0 provisioning-token §2
formalisation (single-use + TTL-bounded idempotent retry; 401 for
expired/superseded/revoked) reuses existing auth codes — `OsppErrorCode`
already maps 2009/2010/2011/2012 → 401. phpunit + phpstan (level 9) green.

## [0.5.7] — 2026-06-18

Left-pad the P-256 private scalar to 32 bytes at key-loading, killing the
recurring ~1/256 keygen flake. Coordinated with `sdk-ts v0.5.7` (lockstep,
ADR-011). `spec` is **NOT** bumped — the spec already mandates DER signatures
and 32-byte scalars; this is an internal key-loading robustness fix with no
wire change (signatures are byte-identical for all keys).

### Fixed

- `EcdsaService::sign()` rejected ~1/256 of valid P-256 keys with "Expected an
  EC P-256 (prime256v1) private key with a 32-byte scalar". OpenSSL's
  `openssl_pkey_get_details()` returns the private scalar `d` big-endian with
  leading zero bytes stripped, so a key whose `d` has a high zero byte comes
  back as 31 (or fewer) bytes and trips the exact-32-byte guard. `d` is now
  left-padded with `str_pad($d, 32, "\x00", STR_PAD_LEFT)` before the guard:
  `gmp_import` yields the identical big-endian integer, so the produced
  signature is byte-identical for normal keys; a >32-byte scalar is still
  rejected (str_pad never truncates). This was the recurring
  `SimulatorWireFormatGateScenariosTest` flake. `sdk-ts` is unaffected — Node's
  JWK export pads `d` to the fixed 32-byte field width (empirically confirmed
  on the same key), so its v0.5.7 is an empty version-alignment bump.

### Verification

- RED-first: a captured 31-byte-scalar key threw pre-fix, signs+verifies
  post-fix. Golden 32-byte-key signature byte-identical pre/post (zero output
  change). 600 keygens → 6 short scalars, 0 throws. Full suite 708 green.

## [0.5.6] — 2026-06-15

Removed the SDK-only orphan `CAPABILITY_NOT_SUPPORTED = 6008`. Coordinated
with `sdk-ts v0.5.6` (lockstep, ADR-011). `spec` is **NOT** bumped — 6008 was
never in the spec. No wire change: 6008 was an internal HTTP-mapping code,
never emitted on the MQTT wire.

### Removed

- `OsppErrorCode::CAPABILITY_NOT_SUPPORTED` (6008) — a PHP-SDK-only extension
  added at `v0.4.3`, never present in the `spec` or `sdk-ts`. It is now
  dead-code: csms-server migrated its firmware/diagnostics capability-unsupported
  pre-flight reject to the spec-canonical `COMMAND_NOT_SUPPORTED` (2007,
  blanket-implicit for all Server→Station per spec 07-errors.md §1). Removing it
  converges PHP with TS (which never had it): the enum drops 107 → 106 cases and
  the 6xxx range closes at 6007 (8 codes), fully spec-aligned. Its `isRecoverable()`
  (false) and `httpStatus()` (422) mappings are removed with it.

### Verification

- Full suite 689 green. RED-first: the enum count test was pinned to 106
  (107 → 106) before the case was deleted; category/severity/recoverable count
  contracts updated (server category 9 → 8). Zero residual references to 6008.

---

## [0.5.5] — 2026-06-13

BootNotification HMAC exemption + always-exempt registry. Coordinated
with `sdk-ts v0.5.5` (lockstep, ADR-011) and `spec` §5.6. `spec` is
**NOT** bumped (classification correction, no schema change). No wire
change — `mac` is already optional in the envelope schema.

### Changed

- `BootNotification` is now exempt from HMAC in **both directions, in
  every `MessageSigningMode`** (whole-action always-exempt). The REQUEST
  has no session key yet; the RESPONSE delivers the key that would verify
  it, so its MAC is cryptographically void (mTLS protects delivery, not
  HMAC). Critical actions drop 20 → 19.

### Added

- Always-exempt registry: `CriticalMessageRegistry::isAlwaysExempt()` +
  `allAlwaysExemptActions()` + `ALWAYS_EXEMPT_ACTIONS`, consulted by
  `SigningMode::shouldSign()` before the mode match. Always-exempt actions
  are never signed or verified in any mode, including `All`. This also
  closes a pre-existing gap where `SigningMode::ALL` signed `ConnectionLost`
  (the broker LWT), contradicting spec §5.6.

### Verification

- Full suite 689 green; phpstan clean. RED-first tests pin
  `isCritical('BootNotification') === false`,
  `isAlwaysExempt('BootNotification') === true`, and exemption across all
  three signing modes.

---

## [0.5.4] — 2026-06-11

ECDSA deterministic-nonce hardening. Coordinated with `sdk-ts v0.5.4`
(lockstep — matching RFC 6979 + low-s policy). `spec` is **NOT** bumped:
RFC 6979 is already mandated by §4.3/§6.2; this brings the implementation
into compliance. No wire change — the DER signature encoding is unchanged.

### Fixed

- ECDSA signing replaced `openssl_sign`'s random-nonce ECDSA (a spec-MUST
  violation, and non-reproducible across runs) with `paragonie/ecc`
  RFC 6979 HMAC-DRBG nonce derivation + low-s normalization
  (anti-malleability), matching `@noble/curves` p256 in `sdk-ts`.
  `openssl_pkey_get_details` extracts the 32-byte `d` scalar; raw `s` is
  normalised to the lower half of the curve order before DER serialization.
  Verify is unchanged (nonce-agnostic; backward-compatible with
  random-nonce signatures issued before 0.5.4).
- Declared `ext-gmp` explicitly (transitive `paragonie/ecc` requirement).

### Verification

- Cross-language byte-identity with `sdk-ts v0.5.4` proven empirically:
  PHP-sign/TS-verify and TS-sign/PHP-verify both interop, and the raw
  signature bytes are identical (`PHP sig === TS sig`). New unit + contract
  tests assert byte-identical signatures across repeated invocations.
- Full suite: 685/685 paratest passing.

---

## [0.5.3] — 2026-06-07

UserSub derivation lift. Coordinated with `sdk-ts v0.5.3`. `spec` is
**NOT** bumped — the derivation rule (`sub` = `sub_` + UUID with
hyphens stripped) is implicitly normative via the existing
`^sub_[a-zA-Z0-9]+$` regex on the OfflinePass `sub` field
(`schemas/common/offline-pass.schema.json`); the spec prose does not
call it out but the schema regex forces it. No wire change.

### Why

The derivation rule lived only in csms-server
(`App\Shared\ValueObjects\UserSub`) prior to v0.5.3 — a latent drift
risk if a firmware or alternative pass issuer ever derives a `sub_*`
independently. Lifting into the SDK makes it the cross-ecosystem
source of truth so PHP and TS implementations cannot drift.

### Added

- `Ospp\Protocol\ValueObjects\UserSubject` — final class with static
  `fromUserId(string $userId): string` returning
  `'sub_' . str_replace('-', '', $userId)`. Static-helper form (not a
  wrapped value object) because the spec, MQTT envelopes, and pass
  bodies all treat the value as a plain string; a wrapped VO would
  only add unwrapping noise at call sites. Byte-identical with the
  TS SDK counterpart (`@ospp/protocol` `UserSubject.fromUserId`).

### Verification

- 8 PHPUnit tests in `tests/Unit/ValueObjects/UserSubjectTest.php`
  covering canonical csms-server vectors plus cross-language
  byte-equality vectors (empty, single hyphen, multi-hyphen, UTF-8
  multibyte).
- Cross-language proof: identical UTF-8 hex output on all 8 vectors
  vs `sdk-ts v0.5.3` `UserSubject.fromUserId`. The unicode vector
  `user-é-moji🎉` → `sub_userémoji🎉` produces the same byte
  sequence `7375625f75736572c3a96d6f6a69f09f8e89` in both SDKs,
  pinning the byte-level UTF-8 invariant (PHP `str_replace` on bytes
  vs JS `replaceAll` on UTF-16 code units agree because `-` is ASCII
  and UTF-8 continuation bytes never contain 0x2D).
- Full suite: 683/683 paratest passing (no regressions).

### Migration

csms-server callers can delegate `App\Shared\ValueObjects\UserSub::
fromUserId` to `Ospp\Protocol\ValueObjects\UserSubject::fromUserId`
(byte-identical return). Wire `sub` field unchanged.

---

## [0.5.2] — 2026-06-07

Enum-drift sync release. Coordinated with `sdk-ts v0.5.2`. `spec` is
**NOT** bumped — codes 2014-2017 have been in `07-errors.md §3.2` since
the `v0.4.2` spec release; the SDK enums simply missed sync. Same
historical-drift pattern as the `v0.5.1` schema sync release.

### Added

- `OsppErrorCode::OFFLINE_PASS_REVOKED = 2014` (`Error`, non-recoverable).
  Individual pass revocation, distinct from `2004 OFFLINE_EPOCH_REVOKED`
  (batch revocation by epoch bump).
- `OsppErrorCode::OFFLINE_ORG_MISMATCH = 2015` (`Error`, non-recoverable).
  Pass `organization_id` ≠ reporting station's `organization_id`.
  Distinct from `2006 OFFLINE_STATION_MISMATCH` (which scopes to
  `allowed_station_ids` membership within the same organization).
- `OsppErrorCode::OFFLINE_USER_MISMATCH = 2016` (`Error`, non-recoverable).
  Pass `user_id` ≠ envelope `userId`.
- `OsppErrorCode::OFFLINE_RECEIPT_MISMATCH = 2017` (`Critical`,
  non-recoverable). Signed receipt field disagrees with cross-check
  target (envelope or pass record). The `details.field` discriminator
  identifies which of `offlineTxId / offlinePassId / userId / deviceId`
  mismatched. Severity elevated to `Critical` per spec — receipt-body
  tampering is a stronger integrity violation than the other gate
  failures (signature itself verified; the signed payload disagrees
  with the envelope's claim or the pass's device binding).

### Updated

- `severity()` match arms — 2014/2015/2016 added to the `Error` list,
  2017 added to the `Critical` list, matching the spec metadata column.
- `isRecoverable()` match arms — all 4 codes added to the `false` list.
- `category()` automatically resolves to `'auth'` via the existing
  `intdiv($value, 1000)` mapping; no change required.
- `httpStatus()` — explicit cases added for all 4 new codes,
  semantically aligned cross-SDK with `sdk-ts v0.5.2`. Spec §2.4 does
  not normatively specify httpStatus for these codes; both SDKs
  converge on values chosen by RFC 9110 semantics:
  - `2014 OFFLINE_PASS_REVOKED → 401` — revoked credential ≡ credential
    no longer valid; RFC 9110 401 "credential invalid".
  - `2015 OFFLINE_ORG_MISMATCH → 403` — pass valid but used cross-org;
    RFC 9110 403 "authenticated, not permitted for this resource".
  - `2016 OFFLINE_USER_MISMATCH → 403` — pass valid but bound to a
    different user than the envelope claims (same shape as
    `2006 OFFLINE_STATION_MISMATCH`).
  - `2017 OFFLINE_RECEIPT_MISMATCH → 422` — signature itself verified
    per spec §3.2; cross-check failure is "syntax correct, instructions
    inconsistent" ≡ RFC 9110 422 Unprocessable Entity (NOT 401 — auth
    itself succeeded).

### Verification

- `paratest -p 28`: `OK (675 tests, 4265 assertions)`.
- `--filter OsppErrorCode`: `OK (66 tests, 1953 assertions)`.
- RED-first on enum addition: prior to the enum addition, the six new
  test cases produced 4 undefined-constant errors + 7 count-failure
  assertions — see commit `5c5f71e` for the captured RED log.
- RED-first on httpStatus alignment: prior to the explicit cases, the
  cross-SDK parity test failed with `500 → 401` (default arm fell
  through to 500) — confirms the 4 new codes were diverging from
  sdk-ts before alignment.

### Migration

- Consumers reading explicit error code constants can replace local
  `const ERR_OFFLINE_PASS_REVOKED = 2014` declarations and `TEXT_*`
  string-name duplicates with `OsppErrorCode::OFFLINE_PASS_REVOKED`
  (case access) and `->errorText()` (PHP enum `$this->name` reflection).
  csms-server's `RevalidationGate` consumes this in its v0.5.2 follow-
  up commit.

### Coordinated with

- `sdk-ts v0.5.2` — parallel addition of the same 4 codes + metadata
  in `OSPP_ERROR_REGISTRY`. Counts: 102 → 106 on the standard surface;
  auth category 14 → 18.

### Known follow-up

- `CAPABILITY_NOT_SUPPORTED = 6008` (SDK PHP-only since `v0.4.3`)
  has no `sdk-ts` mirror. That's a separate SDK-asymmetry Phase B
  finding, not addressed in this release.
- **`httpStatus` cross-SDK drift on pre-existing 2xxx auth codes.**
  10 of 14 existing 2xxx codes diverge between this SDK and
  `sdk-ts` v0.5.x on `httpStatus`:
  - `2000 AUTH_GENERIC`, `2002 OFFLINE_PASS_INVALID`,
    `2003 OFFLINE_PASS_EXPIRED`, `2004 OFFLINE_EPOCH_REVOKED`,
    `2005 OFFLINE_COUNTER_REPLAY`, `2006 OFFLINE_STATION_MISMATCH`,
    `2007 COMMAND_NOT_SUPPORTED`, `2013 BLE_AUTH_FAILED` — this SDK
    falls through to `500` via the `match` default arm; `sdk-ts`
    explicitly maps these to `401` / `403` / `501`.
  - `2001 STATION_NOT_REGISTERED` — this SDK maps to `422`; `sdk-ts`
    maps to `401`.
  - `2008 ACTION_NOT_PERMITTED` — this SDK maps to `401`; `sdk-ts`
    maps to `403`. (Spec §2.4 lists 2008 under both 401 and 403,
    so this divergence has a spec-level ambiguity behind it.)
  Only 4 of 14 agree (`2009 JWT_EXPIRED`, `2010 JWT_INVALID`,
  `2011 SESSION_TOKEN_EXPIRED`, `2012 SESSION_TOKEN_INVALID` — all
  401). Scope of this drift extends beyond 2xxx (cross-SDK
  `httpStatus` parity has not been audited for 3xxx/4xxx/5xxx/6xxx
  ranges). Closing this drift requires a dedicated SDK-metadata
  parity sprint that: (i) audits cross-SDK on the entire enum;
  (ii) chooses the canonical value per code (spec doesn't specify
  for most); (iii) potentially upgrades `07-errors.md §2.4` from
  an indicative "Typical Error Codes" table to a normative
  exhaustive mapping. Tracked separately; NOT in scope for v0.5.2.

---

## [0.5.1] — 2026-06-07

Schema-vendoring sync release. Coordinated with `sdk-ts v0.5.1`. No
protocol change. `spec` is **NOT** bumped — its schemas were already
correct as of `v0.5.0`; the drift was in the SDKs' vendored copies.

### Fixed

- `schemas/ble/receipt.schema.json` — re-vendored byte-identically from
  spec `v0.5.0` source. Adds the v0.4.2-era outer wrapper fields
  `offlinePassId`, `userId`, `deviceId` per `06-security.md §6.2`
  receipt_fields expansion. Prior SDK shape (since `v0.4.2`) was the
  pre-v0.4.2 9-field shape — the SDK simply missed re-vendoring at the
  spec `v0.4.2` release.
- `schemas/common/receipt.schema.json` — re-vendored byte-identically.
  Description-level update aligning with the spec `v0.4.2` `§4.8`
  canonical-form / `§6.2` v0.4.2 anchors. No wire shape change.
- `schemas/common/receipt-data.schema.json` (NEW) — re-vendored
  byte-identically. The canonical `ReceiptData` body that gets
  serialized via OSPP Canonical Form (`spec/06-security.md §4.8`) and
  base64-encoded into `receipt.data` for ECDSA P-256 signing. Was
  introduced by spec `v0.4.2` but had been missing from the SDK
  entirely.

### Why this is a v0.5.1 and not v0.5.0 amendment

The `v0.5.0` tag (commit `95b1452`) stays valid — it correctly added the
`TransactionEventStatus::DEFERRED` enum case (the actual protocol
change of the lockstep release). The drift on receipt-related schemas
was a separate, pre-existing carry-over from the `v0.4.2` spec release
that was caught by `csms-server`'s post-`composer update` byte-identity
check on `2026-06-06`. v0.5.1 closes the drift additively — no force-push
or tag rewrite.

### Verification

- `diff -rq --exclude=README.md /spec/schemas /ospp-sdk-php/schemas` =
  clean (byte-identical).
- `paratest -p 28`: `OK (669 tests, 4181 assertions)`.

### Coordinated with

- `sdk-ts v0.5.1` — parallel schema-sync release on the TS SDK (where
  the drift was broader: missing the `ble/` directory entirely, missing
  `provisioning-response.schema.json`, plus the same `common/receipt`
  divergence).

### Phase B audit pointer

This release closes Phase B audit finding `(a) drift clear` #7 +
inherited drift in `csms-server` vendor. The companion mechanism — a
CI byte-identity gate that prevents recurrence — is tracked separately;
see Phase B audit recommendation #1.

---

## [0.5.0] — 2026-06-06

Lockstep re-synchronization release with `spec` and `sdk-ts`. See
[`spec/adr/ADR-001`](https://github.com/ospp-org/spec/blob/main/adr/ADR-001-cross-repo-lockstep-versioning.md)
for the convention going forward.

The SDK change in this release is small: `TransactionEventStatus` gains
its 5th case (`DEFERRED`) and the vendored MQTT response schema admits
the new wire value. csms-server already emitted `Deferred` on the wire
on the §4.2:52 gap-defer path; spec 0.5.0 closes the corresponding
schema gap and this release brings the SDK enum to parity.

### Added

- `TransactionEventStatus::DEFERRED = 'Deferred'`. Mirrors the spec
  0.5.0 `transaction-event-response.schema.json` enum addition. Distinct
  from `RETRY_LATER` in station-side semantics: `RetryLater` directs the
  station to back off and resend; `Deferred` directs the station that
  the transaction is held server-side pending operator-manual unblock
  OR arrival of the missing in-sequence transactions, and the station
  MUST NOT auto-resend. Distinct enum cases prevent a consumer from
  conflating the two.
- `schemas/mqtt/transaction-event-response.schema.json` synced
  byte-identically with the spec 0.5.0 source — `Deferred` is now an
  admitted `status` value with the same conditional-`reason`-required
  rule the other three non-`Accepted` values carry.

### Changed

- No changes to existing public APIs. This is a pure-additive enum
  extension.

### Migration

- Consumers that exhaustively `match` on `TransactionEventStatus`
  cases (without a default arm) MUST add a `DEFERRED` arm or rely on a
  `default` branch. The SDK itself does not `match`-exhaustively on
  this enum; csms-server's wire handler reads the wire string directly
  and is unaffected.

### Carry-over from orphaned v0.4.3

This SDK shipped a `v0.4.3` (2026-05-14) for an unrelated change —
`CAPABILITY_NOT_SUPPORTED = 6008` + four `httpStatus()` mappings
(`STATION_NOT_REGISTERED → 422`, `STATION_OFFLINE → 503`,
`AMBIGUOUS_REQUEST → 409`, `CAPABILITY_NOT_SUPPORTED → 422`). That
release was never represented in `spec` or `sdk-ts` and would have
collided with `0.4.3` on spec for the present Deferred-enum change.
The `v0.4.3` changes remain in this release — they are not reverted,
only re-anchored under the `0.5.0` lockstep version per ADR-001. See
the [v0.4.3 entry](#043--2026-05-14) below for the full content of
that change.

### Verification

- `paratest -p 28`: OK (669 tests, 4181 assertions).
- `paratest --filter TransactionEventStatusTest`: OK (6 tests,
  17 assertions). RED-first: the new test expectations
  (assertCount(5), `from('Deferred')`, `DEFERRED` constant references,
  `deferred_is_distinct_from_retry_later`) were run against the 4-case
  enum first and produced 1 failure + 3 undefined-constant errors
  before the `DEFERRED` case was added.

### Coordinated with

- `spec v0.5.0` — `TransactionEventResponse` schema `status` enum gains
  `Deferred` + `reconciliation.md §4.1`/`§4.2` document the wire shape
  + `§6.3`/`§6.5` gate-emit-before-INSERT ordering fix.
- `sdk-ts v0.5.0` — `TransactionEventResponse` discriminated union
  gains a `Deferred` variant.

---

## [0.4.3] — 2026-05-14

HTTP status mapping coherence for the four station/server error codes that
were silently falling through to `default => 500`, defeating proper REST
error semantics for callers that surface OsppException via the HTTP layer.
Surfaced via csms-server Brief K1.5 Drift 7-A — `RequestDiagnosticsAction`'s
six pre-flight throws still produced HTTP 500 even after migrating from
`\RuntimeException` to `OsppException`, because four of the chosen error
codes were not enumerated in `OsppErrorCode::httpStatus()`.

### Added

- `OsppErrorCode::CAPABILITY_NOT_SUPPORTED = 6008`. Server-class code for
  station-capability gaps surfaced at admin-action pre-flight time (e.g.,
  diagnostic upload requested against a station whose BootNotification
  capabilities did not advertise `deviceManagementSupported`). Semantically
  distinct from `SERVER_INTERNAL_ERROR` (which remains for genuine server
  faults). Severity: `WARNING`. `isRecoverable`: `false` (the gap cannot
  be retried; it requires station firmware/hardware change).

### Changed

- `OsppErrorCode::httpStatus()` now maps four previously-defaulting codes
  to their proper REST status:
  - `STATION_NOT_REGISTERED` → 422 (was 500). The caller supplied an
    identifier that does not resolve to a registered station —
    Unprocessable Entity matches the cause better than Internal Server
    Error.
  - `CAPABILITY_NOT_SUPPORTED` → 422 (new code; see Added).
  - `INVALID_TIME_WINDOW` → 422 (was 500). Aligns with the other
    validation-class codes (`DURATION_INVALID`, `MAX_DURATION_EXCEEDED`,
    `INVALID_SERVICE`) already mapped to 422.
  - `OPERATION_IN_PROGRESS` → 409 (was 500). Conflict with existing
    in-flight operation matches HTTP 409 Conflict semantics, alongside
    `BAY_BUSY`, `BAY_RESERVED`, `SESSION_ALREADY_ACTIVE`.

  No code's mapping was *removed* or *changed* away from a non-500 value;
  the four codes above only had `default => 500` before this release.
  `SERVER_INTERNAL_ERROR` deliberately still maps to 500 (semantic match).

### Contract test pin updates

- Total code count contract: 102 → 103.
- Server category count contract: 8 → 9.
- httpStatus 422 and 409 cohorts expanded with the four newly-mapped codes.

No breaking changes. Additive only.

---

## [0.4.2] — 2026-05-10

Spec-alignment correctness release. Two value objects had drifted from the
canonical mqtt-envelope schema; both now enforce exactly the schema's
constraints (no more, no less). Surfaced via DLQ inspection of the csms-server
UAT environment, where every inbound message from non-PHP clients was being
rejected with `INVALID_MESSAGE_FORMAT (1005)` at SDK construction time.

### Fixed

- `MessageId` constructor no longer enforces `msg_`/`cmd_`/`err_` prefix
  whitelist. Spec defines `messageId` as `{type: string, minLength: 1,
  maxLength: 64}` with no pattern, and `spec/spec/03-messages.md:2957-2972`
  normatively states prefixes are a SHOULD convention that implementations
  MUST NOT rely on for routing. The previous whitelist was both over-strict
  and divergent from the spec's own prefix table (`boot_`/`hb_`/`evt_`/`sec_`/
  `tx_`/`auth_`/`cmd_`/`lwt-`): two of the three enforced prefixes don't
  appear in the recommendation. Spec-compliant raw-UUID inbound (e.g., from
  the TS station-simulator emitting via `crypto.randomUUID()`) was previously
  rejected at construction; now accepted.
- `ProtocolVersion::fromString` validates input against the schema regex
  `^\d+\.\d+\.\d+$` and enforces `maxLength: 32` before parsing. Previously
  silently coerced non-numeric components via `(int)` cast (`"abc.def.ghi"`
  became `0.0.0`; `"1.2.3-rc1"` became `1.2.3`). Now rejects with a clear
  format-error message at the boundary.

### Migration

None required for emit-side or correctly-formed inputs. If a consumer
deliberately fed `ProtocolVersion::fromString` a string that was being
silently coerced, it now throws — but such inputs were never spec-valid.

`MessageId::generate()` emit-side behavior unchanged — still produces
`msg_<uuid>` (or `cmd_<uuid>` for REQUEST messages built via `MessageBuilder`).
Existing prefixed values continue to construct successfully.

### Spec source-of-truth

- `spec/schemas/common/mqtt-envelope.schema.json` (`messageId`, `protocolVersion`)
- `spec/spec/03-messages.md:2957-2972` (prefix SHOULD-only language)

### Tests

- 668 tests, 4157 assertions, all green (was 661 in v0.4.1; +7 new boundary
  and spec-compliance tests).

---

## [0.4.1] — 2026-05-09

Documentation correction. The v0.4.0 CHANGELOG framed `ProtocolVersion::default()` returning `'0.2.1'` as a "deferred cascade" that needed bumping to `'0.4.0'`. That framing was incorrect.

### Verified (no code changes)

Spec v0.4.0 wire `protocolVersion` field deliberately remains `'0.2.1'` (verified empirically via `spec/02-transport.md`, `schemas/common/mqtt-envelope.schema.json` regex `^\d+\.\d+\.\d+$`, `spec/08-configuration.md` `ProtocolVersion` config-key default, and 174+ JSON examples across `spec/profiles/`). Spec v0.4.0 introduced feature additions (Item 3 `seqNo`/`finalSeqNo`, Item 8 `reason` vocabulary, Item 4 canonical-form consolidation) but did NOT bump the wire version field; the v0.4.0 spec bump commit (`d2d6c0c`) modified only chapter status headers.

`ProtocolVersion::default()` returning `'0.2.1'` is therefore CORRECT — aligned with spec wire mandate, aligned with TS SDK (`@ospp/protocol@0.4.0` `OSPP_PROTOCOL_VERSION = '0.2.1'`), aligned with csms-server `VersionNegotiator` validation expectations.

SDK package version `0.4.0` reflects spec FEATURE TARGETING (matching v0.4.0 features added in this SDK release), NOT wire VERSION. Package version and wire version are independently scoped per spec convention; future spec minor cycles will revisit wire-version discrimination strategy (per spec CHANGELOG [0.4.0] migration note: "per-message envelope `protocolVersion` discrimination, BootNotification capability negotiation").

### Changed

- `CHANGELOG.md` [0.4.0] section "Known mismatch (deferred)" subsection removed; framing was misleading.
- `src/ValueObjects/ProtocolVersion.php` — doc-comment on `default()` clarifies that the returned value is the spec-mandated wire version, NOT the SDK package version.

### Migration

None. No code changes; `ProtocolVersion::default()` still returns `'0.2.1'` (now correctly framed as spec-aligned, not deferred).

---

## [0.4.0] — 2026-05-09

Aligns SDK with OSPP spec v0.4.0. Includes catch-up backport of the v0.2.5 provisioning schema and v0.3.0 `stationCaChain`/`brokerRootCa` additions that the v0.3.x SDK line skipped, plus the v0.4.0 Item 3 (`seqNo` / `finalSeqNo`) and Item 8 (`SessionEndReason` vocabulary) wire deltas. **Coordinated v0.3.x → v0.4.0 station/server upgrade required** — see Migration.

### Added
- `schemas/provisioning-response.schema.json` — HTTP `POST /api/v1/stations/provision` response schema (spec v0.2.5 backport; ships at v0.3.0 + v0.4.0 state with `stationCaChain`, optional `brokerRootCa`, and 12-field `mqttConfig` including `brokerHost`/`brokerPort`/`brokerUri`). Top-level placement, not under `ble/`/`common/`/`mqtt/` (mirrors spec layout — provisioning is HTTP-bound). Defaults align with spec `02-transport.md §1.2` normative connection parameters.
- `schemas/mqtt/meter-values-event.schema.json` — optional `seqNo` (integer ≥ 0); per-session monotonic counter starting at 0 (spec v0.4.0 Item 3).
- `schemas/mqtt/session-ended-event.schema.json` — optional `seqNo` + optional `finalSeqNo` (integer ≥ 0); `reason` enum extended to 5 values (spec v0.4.0 Items 3 + 8).
- `schemas/mqtt/stop-service-response.schema.json` — optional `finalSeqNo` (integer ≥ 0); existing Accepted/Rejected `allOf` conditional unaffected (spec v0.4.0 Item 3).
- `Ospp\Protocol\Enums\SessionEndReason` — three new cases: `LOCAL` (`'Local'`), `LOCAL_OUT_OF_CREDIT` (`'LocalOutOfCredit'`), `DEAUTHORIZED` (`'Deauthorized'`) per spec v0.4.0 Item 8.
- `tests/Unit/Enums/SessionEndReasonTest.php` (NEW) — 5-case enum coverage; closes pre-existing test gap.
- `tests/Contract/Enums/SessionEndReasonContractTest.php` (NEW) — pins cardinality, PascalCase wire format, legacy-values-first ordering, and explicit absence of deferred values (`Remote`, `EnergyLimitReached`).
- `tests/Unit/SchemaPathTest.php` — extended with `provisioning_schema_exists_at_top_level()`.
- `brianium/paratest` to require-dev — enables `paratest -p 28` parallel test execution.

### Changed
- `README.md` shipped-schema count `77 → 78`; total test count `646 → 656`; per-suite Unit `457 → 466` and Contract `148 → 153`; CriticalMessageRegistry blurb `19 → 20 actions` (catches up v0.3.2's SessionEnded addition).

### Verified (no changes required)
- `Ospp\Protocol\Crypto\CanonicalJsonSerializer` — already implements OSPP Canonical Form per spec v0.4.0 §4.8 (recursive `ksort($data, SORT_STRING)` + `array_is_list()` preservation + `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`). Spec Item 4 is consolidation of pre-existing informal text — no behavior change required.
- `Ospp\Protocol\Crypto\CriticalMessageRegistry` — `SessionEnded` already registered (added in v0.3.2 from spec v0.2.4); new reason values do not introduce new registry entries; `finalSeqNo` discard rule is server-side behavior.
- `Ospp\Protocol\StateMachines\SessionTransitions` — spec Item 8 reuses existing terminal states (`Active → Completed` for `Local`/`LocalOutOfCredit`, `Active → Failed` for `Deauthorized`) with `reason` as discriminator. No new FSM states.

### Migration

This release requires **coordinated v0.3.x → v0.4.0 stack upgrade**:

- **SessionEndReason vocabulary (Item 8):** v0.3.x servers will reject SessionEnded payloads carrying `Local`, `LocalOutOfCredit`, or `Deauthorized` via JSON-schema validation. Stations using SDK v0.4.0 in v0.3.x server fleets MUST be configured to emit only legacy reasons (`TimerExpired`, `Fault`) until the server fleet is upgraded.

Additive changes (Item 3 `seqNo`/`finalSeqNo`, provisioning schema backport) are backwards-compatible — all new schema fields are OPTIONAL; v0.3.x servers ignore unknown fields per spec `02-transport.md §10.1` forward-compatibility rule.

---

## [0.3.2] — 2026-03-22

### Fixed
- Add `SessionEnded` to `CriticalMessageRegistry::CRITICAL_ACTIONS` — sync with spec v0.2.4: SessionEnded contains `creditsCharged` used directly for online billing at timer expiry, requires HMAC signing in `Critical` mode

---

## [0.3.1] — 2026-03-21

### Fixed
- Sync `schemas/mqtt/boot-notification-response.schema.json` with spec v0.2.1 — add `supportedVersions` array property (required when Rejected with `1007 PROTOCOL_VERSION_MISMATCH`)
- Update default `protocolVersion` from `0.1.0` to `0.2.1` in `ProtocolVersion` value object and `ConfigurationKey` enum
- Update all test assertions from `0.1.0` to `0.2.1`

---

## [0.3.0] — 2026-03-21

### Added
- **SessionEnded** (MSG-040) — action constant, `SessionEndReason` enum (`TimerExpired`, `Fault`), schema
- Updated README for v0.3.0 (30 actions, 646 tests)

---

## [0.2.1] — 2026-03-04

### Added
- Include protocol JSON schemas (`schemas/mqtt/`, `schemas/common/`, `schemas/ble/`) in SDK package

---

## [0.2.0] — 2026-03-02

### Changed
- Align SDK with OSPP spec v0.1.0-draft.1
- Rename namespace to `Ospp\Protocol`, package to `ospp/protocol`
- Use `array_is_list()` and add config-driven `ProtocolVersion` default

---

## [0.1.0] — 2026-02-24

### Added
- Initial release: OSPP SDK for PHP
- Message envelope builder, serializer, deserializer
- HMAC-SHA256 message signing (`MacSigner`, `CriticalMessageRegistry`)
- Value objects: `MessageId`, `ProtocolVersion`, `Timestamp`
- Enums: `BayStatus`, `BootReason`, `OsppErrorCode`, `SecurityEventType`, and 20+ more
- State machines: `BayTransitions`, `SessionTransitions`, `ReservationTransitions`, `FirmwareTransitions`, `DiagnosticsTransitions`
- Schema validation infrastructure
- 646 tests with 4093 assertions
