# Changelog

All notable changes to the OSPP SDK for PHP will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

### Known mismatch (deferred)

`ProtocolVersion::default()` and `ConfigurationKey::PROTOCOL_VERSION` still return `'0.2.1'` — the v0.3.0 bump was never cascaded and remains un-cascaded in v0.4.0 by deliberate scope decision. SDK consumers calling `ProtocolVersion::default()` will receive `'0.2.1'` despite the SDK package version being `0.4.0`. Override via `ProtocolVersion::setDefaultResolver(fn () => '0.4.0')` if a wire-version match is required. Will be addressed in a follow-up brief.

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
