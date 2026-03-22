# Changelog

All notable changes to the OSPP SDK for PHP will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
