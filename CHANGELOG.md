# Changelog

All notable changes to the OSPP SDK for PHP will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

### Verification

- `paratest -p 28`: `OK (674 tests, 4261 assertions)`.
- `--filter OsppErrorCode`: `OK (65 tests, 1925 assertions)`.
- RED-first: prior to the enum addition, the six new test cases
  produced 4 undefined-constant errors + 7 count-failure assertions —
  see commit `5c5f71e` for the captured RED log.

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
  has no `sdk-ts` mirror. That's a separate SDK-asymmetry-Phase-B
  finding, not addressed in this release.

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
