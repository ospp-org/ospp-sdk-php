# Changelog

All notable changes to the OSPP SDK for PHP will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## 0.23.0 — 2026-08-18

**Three-repository release against spec `v0.23.0`** ([ADR-001](https://github.com/ospp-org/spec/blob/main/adr/ADR-001-cross-repo-lockstep-versioning.md)).
`.spec-ref` moves **v0.22.0 → v0.23.0**.

> ### ⚠ This SDK's diagnostics state machine was wrong, and nothing here could have said so.

Until spec `0.23.0` there was no diagnostics section in `05-state-machines.md`. This SDK read the
four status words of `diagnostics-status.md` as a **server record** and got five states with six
edges, starting at `pending`, with `uploaded` and `failed` terminal. `sdk-ts` read the same words
as a **station** and got five states with seven edges, starting at `Idle`, refusing `Idle -> Failed`,
with both outcomes returning to `Idle`. The two disagreed on three edges. Both suites were green,
each pinning its own answer — this one asserted `PENDING -> FAILED` is permitted, the other asserts
`['Idle','Failed']` is refused — and neither could cite a table. No CHANGELOG on either side
mentioned it.

Spec §8 now derives the machine from what the station does between two obligations it already had,
and §8.5 states where this SDK's sixth edge belongs.

### Added — `DiagnosticsState`, the station's machine

`src/Enums/DiagnosticsState.php`: `idle`, `collecting`, `uploading`, `uploaded`, `failed`. **Nothing
is terminal** — §8.3, "a station that treats it as terminal can run one diagnostics upload and never
a second," which is the identical defect `FirmwareTransitions` closed at `0.20.0` and that was never
carried across to diagnostics.

It carries the **wire ↔ machine bridge**, which neither SDK had in either direction:
`fromNotificationStatus()` maps the four schema enum values onto states, `toNotificationStatus()`
goes back and returns `null` for `idle`, and `isReportable()` names the one state §8.4 says no
notification carries. There is deliberately **no** way to produce `idle` from a wire value: §8.4 —
"`Uploaded -> Idle` and `Failed -> Idle` have no wire trigger, and a server must not wait for one."

### Changed — breaking: `DiagnosticsTransitions` is retyped and its table replaced

It now operates on `DiagnosticsState`, not `DiagnosticsStatus`, and carries the seven edges of §8.3.
Three changes, each with its own consequence:

* `pending` is gone from the machine. It was never a station state.
* `pending -> failed` is gone with it. There is no `idle -> failed`, and §8.3 makes the absence
  load-bearing: a station that cannot start answers the command `Rejected` and never enters the
  machine, so a `Failed` that is the **first** notification of an accepted operation is
  non-conforming.
* `uploaded` and `failed` return to `idle`. The machine is no longer single-use.

Any consumer calling `canTransition(DiagnosticsStatus, DiagnosticsStatus)` will not compile.

### Changed — `DiagnosticsStatus` keeps its six edges, and is documented as what it is

Nothing about its behaviour moves. It is a **server's record of one requested upload** (§8.5), which
legitimately carries `pending`, whose `pending -> failed` is the station answering `Rejected`, and
whose outcomes **are** terminal because a row is closed by its outcome. The reference server stores
exactly these five values and is unaffected.

### Changed — the two tests that held the wrong table in place

`StateMachineEnumConsistencyTest` asserted that `DiagnosticsTransitions` and
`DiagnosticsStatus::allowedTransitions()` were the same table. Both assertions were true, and both
were the reason `pending` stayed inside the machine and the outcomes stayed terminal. §8.5 forbids
the comparison outright: "a conformance test MUST NOT assert a transition of the server's record
against §8.3." What replaces it is a guard against **re-merging** them — if the two enums ever agree
on membership again, that fails before a table can follow.

`DiagnosticsTransitionsTest` and `DiagnosticsTransitionsContractTest` lose their count-based forms:
`transitionCount() === 6`, a positive-only six-pair list, and a 6/19 tally over the 5×5 matrix. A
tally cannot say **which** pair moved, and every one of those numbers was internally consistent
while the table was wrong. This is the same deletion `FirmwareTransitions`' tests took at `0.20.0`.

### Added — `DiagnosticsCanonicalTableContractTest`

The single home of the table, on the model of the bay and firmware ones, and the third of the three.
It pins the seven `(from, to)` pairs as a named set, sweeps all 25 ordered pairs so a pair asserted
neither way cannot exist, checks map-against-function, asserts `isTerminal() ⟺ no outgoing edge`,
walks three consecutive uploads including one that fails, and reads the wire enum **out of the
vendored schema** rather than a literal, so a spec change to it fails here.

Verified non-vacuous before committing. Restoring the old terminal outcomes → **9 failures**;
re-adding `idle -> failed` → **5**; adding the `uploading -> uploading` self-edge §8.4 forbids →
**5**; breaking one arm of the bridge → **2**.

### Removed — breaking: `ConfigurationKey::DIAGNOSTICS_UPLOAD_URL`

Spec `0.23.0` withdrew the key. It had no reachable consumer: `uploadUrl` is REQUIRED on every
GetDiagnostics so nothing fell back to it, no processing rule read it, and no error code reported
the disabled state its documented `''` default claimed — measured across the reference server, this
SDK, `sdk-ts` and the station simulator. **29 cases → 28**, Device Management **4 → 3**, Static keys
**7 → 6**.

**This was missed on the first push and CI caught it.** `scripts/check-config-registry.sh` is a
separate CI job that clones the spec at `.spec-ref`; `composer test` does not run it, so a green
local suite said nothing about it. The gate failed with *"DiagnosticsUploadUrl: in the SDK enum,
MISSING from the spec"* — which is the gate doing exactly its job.

**The cost is operational and lands on servers, not on this package.** An unknown key is answered
`NotSupported`, and `change-configuration.md` §6 rule 2 makes the batch atomic — one `NotSupported`
entry discards *every other key in the same ChangeConfiguration*. A server still carrying this key
in a push set finds the whole batch ineffective against a `0.23.0` station while the identical batch
still applies on `0.22.0`.

### Changed — vendored corpus and schema, in one commit

`schemas/mqtt/diagnostics-notification.schema.json` gains conditionals: `progress` only on
`Uploading`, `errorText` REQUIRED on `Failed` and forbidden elsewhere. The vector corpus moves with
it — **160 → 163 valid, 158 → 166 invalid** — because a schema tightening whose corpus is not
updated in the same commit turns this SDK's own conformance suite red on payloads that are no longer
valid. Three of the eight new negatives enter the `if`/`then` branches of `get-diagnostics-response`
and `set-maintenance-mode-response`, which **no vector had ever entered**: both `allOf` blocks could
have been deleted with the whole vendored corpus still passing.

Suite: **1230 tests, 6290 assertions**, phpstan level 9 clean, and all four drift gates green —
`check-config-registry`, `check-error-registry`, `check-crypto-vectors`, `check-schemas`.

---

## 0.22.0 — 2026-08-18

**Three-repository release against spec `v0.22.0`** ([ADR-001](https://github.com/ospp-org/spec/blob/main/adr/ADR-001-cross-repo-lockstep-versioning.md)).
The spec's contract moved — a schema tightening and an error-registry change — so all three
repositories carry this number. `.spec-ref` moves **v0.20.2 → v0.22.0**, skipping `v0.21.0`,
which was a spec-only reversal neither SDK ever carried.

> ### ⚠ Two breaking changes, one of which reaches code that never mentions the error code.

### Changed — the pricing conditional is now enforced, and a price-less catalog item is refused

`schemas/common/service-item.schema.json` gained `if`/`then`: a `PerMinute` service requires
`priceCreditsPerMinute` and **MUST NOT** carry `priceCreditsFixed` or `priceLocalFixed`; `Fixed`
is the mirror. Before this, a service with a declared `pricingType` and **no price at all**
validated clean, and the spec's own "valid" conformance vector
`update-service-catalog-request-minimal.json` was exactly that payload.

Nothing in `src/` had to change for this: the rule arrives by vendoring. What *did* have to move
in the same commit is the vector — a schema tightening whose corpus is not updated with it turns
the SDK's own conformance suite red on a payload that is no longer valid. Measured here before
committing: schema without vector = **1 failed**, and the failure names that file.

`schemas/ble/available-services.schema.json` gained the same conditional.

### Changed — `5024 UNSUPPORTED_SERVICE` severity `Warning` → `Error`

The spec withdrew the partial application this code mandated: a station now refuses the **whole**
catalog rather than dropping the entry it cannot run. `OsppErrorCode` is updated to match.

**This is the change to look at if you branch on severity rather than on the code.** The code's
number, name and `recoverable` are all unchanged, so a consumer switching on `5024` sees nothing;
a consumer routing by `Severity` sees this move from an advisory to an error. Each SDK's
`check-error-registry` gate caught it against the spec — it was not found by reading.

### Unchanged, and verified so

This SDK validates the BLE `offline/` corpus; `sdk-ts` does not, because its `SchemaValidator`
maps MQTT keys only. So `schemas/ble/available-services.schema.json` reaches **this** SDK alone,
and the proof is that with the schemas copied and the vectors not yet moved, this suite failed on
**two** vectors — `device-management/update-service-catalog-request-minimal.json` and
`offline/available-services-minimal.json` — where `sdk-ts` failed on only the first.

---

## 0.20.0 — 2026-08-17

**SDK-pair release against spec `v0.20.2`** ([ADR-001](https://github.com/ospp-org/spec/blob/main/adr/ADR-001-cross-repo-lockstep-versioning.md),
*SDK-pair releases against a spec tag*). Released at the same version as `@ospp/protocol`
**0.20.0**, from the same spec pin. `.spec-ref` moves **v0.19.0 → v0.20.2**.

**This release changes behaviour.** The firmware update state machine in this package had
two transitions the specification does not list and was missing one it requires.

### Fixed — the firmware update FSM had 14 edges; §6.3 has 13

`spec/05-state-machines.md` §6.3 lists **fourteen rows and thirteen edges**. `Verifying ->
Failed` appears twice, once for a checksum mismatch and once for an invalid signature,
"because the two have different actions and different error codes, not because they are two
transitions."

This package had fourteen `(from, to)` pairs. The count matched; the set did not.

| Edge | Was | §6.3 | Effect |
|------|-----|------|--------|
| `downloaded -> failed` | permitted | **not listed** | removed |
| `installed -> failed` | permitted | **not listed** | removed |
| `failed -> idle` | **refused** | listed ("Rollback complete") | added |

**The missing edge is the one that mattered.** §6.3: *"`Failed` has exactly one outgoing edge,
`Failed -> Idle`; it is **not** terminal, and a machine that treats it as terminal can run one
firmware update and never a second."* With `failed => []`, this package's machine was
single-use: a station whose first update failed could never be offered another, because no
transition out of `failed` existed to offer.

`sdk-ts` had all thirteen and none of the two. Both SDKs were green, each against its own
transcription of the same table — see *Added* below.

### Fixed — `FirmwareUpdateStatus::isTerminal()` called `Failed` terminal

The same wrong belief lived in a second file, with its own tests pinning it. `isTerminal()`
now returns `true` for `ACTIVATED` only.

`isActive()` **answers exactly as before** — the seven in-flight states, `DOWNLOADING` through
`REBOOTING`. It was previously *derived* (`! isTerminal() && !== IDLE`), so correcting
`isTerminal()` alone would have silently made `FAILED` active. It is now enumerated, because
the two predicates do not partition the same way: `Failed` is not terminal, and it is not in
flight either — it is rolling back.

```diff
- if ($status->isTerminal()) { $this->closeUpdate($update); }   // fired on Failed too
+ if ($status->isTerminal()) { $this->closeUpdate($update); }   // fires on Activated only
```

**Callers that treated `isTerminal()` as "this update is over" must now test
`isTerminal() || $status === FirmwareUpdateStatus::FAILED`,** or better, test what they
actually mean. `isActive()` is unchanged and remains the right predicate for "in flight".

### Added — `FirmwareCanonicalTableContractTest`, mirrored in `sdk-ts`

The firmware machine had no shared vector list. Each SDK asserted its own transcription of
§6.3, so the two could disagree indefinitely and both stay green — which is what happened.
`BayCanonicalTableContractTest` already solved this for the bay machine; this follows that
pattern rather than inventing a second one. The pair list is transcribed from §6.3 and is the
same list `sdk-ts/tests/state-machines/FirmwareCanonicalTable.test.ts` asserts.

**It does not assert a cardinal, and that is deliberate.** The old tests asserted
`transitionCount() === 14` in two files, and 14 is the **row** count of §6.3's table. A count
is the one assertion that cannot say *which* edge moved: swapping a real edge for a phantom
leaves it unchanged. §6.3 now warns about this directly — "a conformance check that asserts a
transition *count* must assert 13; one that counts the rows of this table gets 14 and then has
to invent an edge to reach it."

The new test sweeps the full 10×10 matrix and compares each cell against the named set, so it
fails in **both** directions. Verified by construction: against the pre-fix table it fails on
exactly `downloaded>failed`, `installed>failed` and `failed>idle`; against a machine that
permits every pair it fails on the negative sweep while all thirteen positive cases still
pass — which is precisely the blindness a positive-only vector list has.

### Changed

- `.spec-ref`: **v0.19.0 → v0.20.2**. No schema changed between those tags. Two conformance
  vectors did, and both are re-vendored: `valid/device-management/firmware-status-notification-full.json`
  (`progress` 72 → 0) and the new `invalid/device-management/update-firmware-request-http-url.json`
  (a non-TLS `firmwareUrl`, rejected by the unchanged `^https://` pattern). The vendored
  corpus is now byte-identical to `v0.20.2`.
- `ConformanceVectorTest`: invalid-vector count **157 → 158**, for the vector above.

### Consumers

`csms-server` pins `^0.19.0`, which Composer resolves as `>=0.19.0 <0.20.0` — it will **not**
pick this up without an explicit constraint bump. It delegates its `FirmwareUpdateFSM` wholly
to `FirmwareTransitions`, so it inherits all three edge corrections, and
`tests/Unit/Modules/DeviceManagement/StateMachines/FirmwareUpdateFSMTest.php` asserts
`transitionCount() === 14` — that assertion is the defect's copy in the consumer and must
become a set assertion, not a bump to 13.

---

## 0.19.0 — 2026-08-14

**SDK-pair release against spec `v0.19.0`** ([ADR-001](https://github.com/ospp-org/spec/blob/main/adr/ADR-001-cross-repo-lockstep-versioning.md),
*SDK-pair releases against a spec tag*). Released at the same version as `@ospp/protocol`
**0.19.0**, from the same spec pin.

**This release changes code, and it is a breaking API change.** `.spec-ref` moves
**v0.17.0 → v0.19.0**, skipping `v0.18.0`, which changed nothing this package implements.

### BREAKING — `StationState::maySendUnsolicited()` is removed; use `mayOriginate(string $action)`

Spec `v0.19.0` restated §1.4: *"A restricted station may originate exactly those messages that
repair its own standing with the server."* BootNotification restores the station's
registration; **SignCertificate** restores the credential without which it cannot connect at
all. Nothing else qualifies.

That makes the §1.4 rule **message-dependent**, and `maySendUnsolicited()` took no message. It
returned `$this === self::OPERATIONAL`, so it answered `false` for a `Pending` station
originating SignCertificate — which the specification now permits, and which is the whole point
of the change.

```diff
- if ($state->maySendUnsolicited()) { $this->publish($msg); }
+ if ($state->mayOriginate($msg->action)) { $this->publish($msg); }
```

**Why it was removed rather than kept beside the new one.** A second predicate answering the
real question would have left the first one answering the old one — still public, still
returning `false` for a case that is now legal, still looking like the method to reach for. The
single boolean that can no longer answer *is* the defect; adding a second does not repair it.

**The API cost, stated plainly.** `maySendUnsolicited()` was a public method on a public enum.
Removing it breaks a caller with `Error: Call to undefined method` — at call time rather than
at compile time, so it is the louder of the two failures available but not the loudest possible.
Leaving it in place returning the same values would have been worse: a caller using it to gate
*"may I send this message?"* would have gone on getting a wrong answer for SignCertificate
**silently** — no error, no static-analysis failure, just a station that never renews while
`Pending` and eventually needs a site visit. PHPStan cannot catch the silent case and does catch
the removal, which is the argument for removing.

Measured before removing: **no consumer in `csms-server`, `ts-station-simulator`,
`station-simulator`, `csms-mqtt-bridge` or `csms-sandbox` calls it.** `csms-server` imports the
`StationState` enum itself, but not this method. The break is theoretical for every repository
in this project today.

### Added

- **`StationState::mayOriginate(string $action): bool`** — the message-aware predicate.
  `Operational` may originate anything; `Pending` may originate `BootNotification` and
  `SignCertificate`; `Booting` and `Rejected` may originate `BootNotification` only, because they
  hold no session key and SignCertificate is one of the 44 signed message types (a sender with no
  key **MUST** refuse to send rather than send unsigned). `NotProvisioned` and `Disconnected`
  answer `false`, exactly as the removed method did — that is the §1.4 answer, not a transport
  claim.
- **`StationState::STANDING_REPAIR_ACTIONS`** — the two wire `action` values, and the same array
  `mayOriginate()` tests against rather than a second copy that could drift from it.

### Changed

- `StationState::PENDING`'s doc comment restated the old rule in prose (*"sends nothing
  unsolicited"*). It now names both permitted messages and says why the session key is what makes
  SignCertificate possible in `Pending` and impossible in `Rejected`. Same class as the fourteen
  restatement sites the spec release moved: a restatement left holding the old rule is how the
  contradiction was born.

### Not changed

- **No schema, vector or total moves.** The vendored `schemas/` tree is byte-identical to spec
  `v0.19.0` — verified by diff, not assumed — because `v0.18.0` and `v0.19.0` changed no JSON
  artefact. The change is in hand-written enum code, which is why "the spec diff is all Markdown"
  was necessary and not sufficient for judging SDK impact this time.

## [0.18.0] — 2026-08-13

**SDK-pair release against spec `v0.17.0`** ([ADR-001](https://github.com/ospp-org/spec/blob/main/adr/ADR-001-cross-repo-lockstep-versioning.md)).
Released at the same version as `@ospp/protocol` **0.18.0**, from the same spec pin.

**This release changes no code.** `.spec-ref` moves **v0.16.0 → v0.17.0** and nothing else
in this package moves with it. No schema is re-vendored, no vector, no enum, no total, no
type. If you are reading this to find out what to change on upgrade, the answer is nothing:
`0.17.0` and `0.18.0` are the same library compiled from the same sources against a
different pin.

### Why a release exists for this at all

`csms-server` could not move to spec `v0.17.0` while this package pinned `v0.16.0`. Its
`VendoredSchemaSpecParityTest` reads `vendor/ospp/protocol/.spec-ref` and requires it to
equal the server's own `.spec-ref` **as a string** — it compares the marker, not the
schemas. So the server was held at `v0.16.0` by this package, and no amount of inspecting
the schema bytes would have released it.

That gate is right to refuse, and the reason is worth stating precisely, because the
tempting objection is the one it exists to reject. *"The bytes are identical, so let the
server pin `v0.17.0` against the `v0.16.0` SDK"* is exactly the reasoning that produced the
`^0.6.2 for months` drift the parity guard was written for. A vendored copy that happens to
match today, compared against a spec version nobody declared, is indistinguishable from a
stale one — that is the whole point of a marker. The marker is the claim; the byte-identity
gate is the proof of the claim. Weakening the claim to preserve the proof gets the
dependency backwards, and the fix is to move the marker, which is what this release does.

### What was measured before the pin moved

`v0.16.0..v0.17.0` is **27 files, every one of them Markdown, zero JSON.** No schema and no
conformance vector changes. The only two files under a directory this package vendors are
`schemas/README.md` and `conformance/test-vectors/README.md`, each a single version-banner
line `0.16.0 → 0.17.0` — and neither is present here at all, because the vendored
`schemas/` tree carries no README.

Two of the 27 are gate *sources*, which is why "all Markdown" is not on its own sufficient:

- `spec/07-errors.md` — version banner only. The 118-row error registry is untouched.
- `spec/08-configuration.md` — version banner, plus one Description cell on
  `OfflinePassPublicKey` narrowing the previous-key grace window to §6.7 step 4. Prose in a
  column no gate compares; the key's Type, Default, Access, Mutability and §1.5 Profile ID
  are unchanged.

The substantive change in `v0.17.0` is §6.7 gaining a second, compromise-driven rotation
posture. **Nothing follows from it here.** This package implements no previous-key
retention and no grace-period expiry — `OfflinePassPublicKey` appears only as a registry
row — because that requirement is station behaviour, and this is a protocol type, schema
and crypto-primitive library.

All four spec-facing gates were re-run against the `v0.17.0` tree **before** the pin moved,
and each reports what it compared rather than only that it passed:

- **error registry** — `118 codes` both sides, agreeing on errorText, severity, recoverable
- **config registry** — `29 keys, 5 profiles`, 29 profiles compared, agreeing on type,
  default, access, mutability and the §1.5 normative Profile ID
- **crypto corpus** — 4 named files byte-identical (`ble-handshake-keyschedule.json`,
  `rfc-primitive-anchors.json`, `canonical-form.json`, `server-test-pub.pem`)
- **schemas** — vendored `schemas/` byte-identical to the `v0.17.0` tree

`phpunit` 1192 tests / 6058 assertions and `phpstan --level=9 src/` are green, unchanged
from `0.17.0` as they must be, since no source file moved.

### On this shape of release

A spec release that touches no schema and no vector will keep producing exactly this: a
version number whose entire content is a four-byte edit to `.spec-ref`. It is worth naming
as its own category rather than treating each occurrence as an oddity. **A release that
exists only to move a pin is not a release that ships code**, and reading it as one — asking
what to test, what broke, what to migrate — wastes the reader's attention on an empty set.
The MINOR bump is not a claim that something was delivered; under ADR-001 it is how the
pair stays at one version, and the pin is the deliverable.

The cost is real and falls on the consumer, not here: `csms-server` must land the composer
constraint and its own `.spec-ref` in **one commit**, because either alone is the drift the
parity gate fails on.

---

## [0.17.0] — 2026-08-13

**SDK-pair release against spec `v0.16.0`** ([ADR-001](https://github.com/ospp-org/spec/blob/main/adr/ADR-001-cross-repo-lockstep-versioning.md)).
Released at the same version as `@ospp/protocol` **0.17.0**, from the same spec pin.

`.spec-ref` **v0.15.0 → v0.16.0**, and this is the cheap kind of bump: **nothing needs
re-vendoring.** `v0.16.0` changes no schema and no conformance vector — the only files that
moved under `schemas/` and `conformance/test-vectors/` are two README version banners, and
the byte-identity gate excludes `README.md` while the crypto gate names its four files
explicitly. Measured rather than assumed: all four spec-facing gates were re-run against the
`v0.16.0` tree before the pin moved, and all four are green. A release that does not force a
re-vendor is worth saying out loud, because the ordering that a re-vendor *does* force —
schemas, then vectors, then hardcoded totals, then the pin — is expensive and is not needed
here.

> **BREAKING, for a consumer that string-compares `ConfigurationKey::…->profile()`:
> the four Offline / BLE keys now answer `OfflineBLE` where they answered `Offline`.**
>
> `OfflineModeEnabled`, `MaxOfflineTransactions`, `OfflinePassMaxAge` and `RevocationEpoch`.
> The other four profiles are unchanged: `Core`, `Transaction`, `Security`,
> `DeviceManagement` were already spelled the way the spec now requires.
>
> **This is an API break and NOT a protocol break, and the distinction is load-bearing.**
> The profile is metadata that never reaches the wire. No schema in `schemas/` declares a
> `profile` property at all; `schemas/mqtt/get-configuration-response.schema.json` sets
> `additionalProperties: false` over exactly `key`, `value` and `readonly`, so a profile
> field could not be sent even deliberately. `ConfigurationKey` is referenced in three files
> in this package and none of them is under `src/Envelope/`, `src/Actions/` or `src/Crypto/`;
> `->profile()` had two call sites before this release and both were tests. **No byte on any
> MQTT or BLE payload changes, no canonical form changes, no MAC or signature input changes.**
> A station and a server on either side of this upgrade interoperate exactly as before.
>
> **`csms-server`, the only known consumer of this package, does not read it.**
> `Ospp\Protocol\Enums\ConfigurationKey` is imported nowhere in that repository — its
> `App\Modules\DeviceManagement\Config\ConfigRegistry` is a separate hand-maintained table
> that happens to spell the same profile `'Offline'`, and is unaffected by this change
> because it never consulted the enum. That local table is now the drifted one, against the
> spec rather than against this SDK.

### Changed

- **`ConfigurationKey::profile()` returns the spec's normative Profile ID.** Spec `v0.16.0`
  §1.5 gains a **Profile ID** column — `Core`, `Transaction`, `Security`, `OfflineBLE`,
  `DeviceManagement` — and states that an implementation exposing a key's profile as a
  program value MUST use it exactly. Until that column existed there was only a display
  label to copy, and `Offline / BLE` does not survive being made an identifier: this package
  chose `Offline`, `@ospp/protocol` chose `OfflineBLE`, and the same table's `Device
  Management` produced `DeviceManagement` here and `DeviceMgmt` there. Four spellings of two
  profiles across two SDKs, none of them wrong against anything, because there was nothing to
  be wrong against. Each SDK changes exactly one value in this release.

- **`scripts/check-schemas.sh` is `100755`.** It was `100644` in the git index while the
  0.15.0 notes claimed all four gate scripts were `100755`. Harmless today — the schemas CI
  job inlines its steps rather than calling the script — but it is the same latent defect
  that killed two gates at 0.13.0, armed and waiting for the day someone wires it as
  `run: scripts/check-schemas.sh`. The claim is now true.

- **docs:** `README.md` described `ConfigurationKey` as *"41 keys with metadata"*. It has 29,
  and has since the enum was cut down.

### Added

- **`scripts/check-config-registry.php` compares the profile.** The gate has compared this
  enum against Chapter 08 since 0.15.0 on `Type`, `Default`, `Access` and `Mutability`, and
  was structurally blind to the fifth property: **the profile is not in the same table.**
  §§2--6 carry no profile column — a key's profile is expressed there by which *section* the
  row sits in — so a gate built by parsing those rows sees four properties and cannot see
  this one. It ran green against spec `v0.16.0` with the `Offline` drift live. The profile is
  now read from §1.5 and compared per key, which is what §1.5 means when it says "an SDK gate
  compares its own registry to the Profile ID column".

  Three properties, the same as the sibling ratchets:

  - **Thresholds on each side before any comparison.** §1.5 is a *different table* from the
    §§2--6 rows, so the 25-row floor those already cleared says nothing about it: §1.5 could
    reformat, yield nothing, and leave the gate reporting a clean pass on four properties
    while checking zero keys for the fifth. It has its own floors — 5 profile rows, 25 keys
    named across them — plus one on the SDK side.
  - **Zero matched pairs is a failure, never a pass.** Every threshold above can be cleared
    by two tables that each parse fully and name *disjoint* key sets: both sides full, the
    intersection empty, nothing compared and nothing reported. The count of pairs actually
    compared is now printed on success, so the number the gate is asserting over is visible
    rather than inferred from silence.
  - **It refuses rather than reports success on too few rows**, and names the parser to fix.

  RED-tested four ways, each confirmed to exit 1: the real drift (reports exactly the four
  Offline keys); spec `v0.15.0`, where the column does not exist yet (0 profile rows —
  **this gate cannot run against a spec older than v0.16.0, which is why the pin and the gate
  move in one commit**); two tables naming disjoint keys (0 pairs compared); and a misspelled
  Profile ID upstream (reported once as a vocabulary problem, not only as four per-key lines).

### Not in this release

- **No range validation.** Spec `v0.16.0` declares the Chapter 08 Range column normative
  (§1.6) and widens `HeartbeatIntervalSeconds` from `30--3600` to `10--3600`. This enum
  models no range — it never has — so there is nothing here to correct and nothing for the
  gate to compare. The widened floor matters to whatever validates a ChangeConfiguration
  value, which in this architecture is downstream of this package.
- **Nothing for the Device Management profile becoming capability-conditional.** `v0.16.0`
  makes those four keys REQUIRED only when a station declares
  `capabilities.deviceManagementSupported`. This enum records which profile a key belongs to,
  not whether that profile is required, so the change has no surface here.

## [0.16.0] — 2026-08-13

**SDK-pair release against spec `v0.15.0`** ([ADR-001](https://github.com/ospp-org/spec/blob/main/adr/ADR-001-cross-repo-lockstep-versioning.md)).
Released at the same version as `@ospp/protocol` **0.16.0**, from the same spec pin.

`.spec-ref` **v0.13.0 → v0.15.0** — a **two-release** jump, and only the first of the two
carries anything. `v0.14.0` moved a schema and moved the corpus with it; `v0.15.0` touched
neither, so for a vendoring SDK it is the pin and nothing else. The whole of the work below
comes from a range this SDK passes *through* rather than lands on, which is the part that is
easy to get wrong: bumping straight to the newest tag does not mean skipping the middle one.

> **BREAKING for a consumer that validates against the vendored schemas: MeterValues with
> an empty `values` object is now REJECTED.**
>
> `schemas/common/meter-values.schema.json` gained `"minProperties": 1`. `{"values": {}}`
> validated for the whole life of this package and does not any more.
>
> **This SDK's own behaviour does not change.** `opis/json-schema` is `require-dev` here —
> the package ships the schemas as artefacts and validates nothing at run time — so the
> effect lands entirely in the consumer that compiles them. `csms-server` has
> `opis/json-schema` in `require` and validates inbound MQTT payloads against this tree; it
> will begin refusing a payload it used to accept **on upgrading this dependency, with no
> code change of its own**. That is the intended outcome: `meter-values.md` §5 has always
> said *"The `values` object **MUST** contain at least one field"*, and for the whole of that
> time nothing enforced it. A station emitting `"values": {}` was already non-conforming and
> was already being believed.
>
> The corresponding tightening in `@ospp/protocol` 0.16.0 is **not** deferred to a consumer —
> that SDK compiles Ajv over its own vendored tree behind a public export, so there it is a
> behaviour change on the public API the day it ships. Same schema, two blast radii.

### Vendored

- `schemas/` — re-vendored, byte-identical to spec `v0.15.0`. **86 files**, unchanged in
  count; two moved:
  - `common/meter-values.schema.json` — gained `"minProperties": 1`. The substantive change
    of this release, and the only one with a consumer consequence.
  - `mqtt/session-ended-event.schema.json` — `seqNo.description` only. The old text said the
    counter *"matches the running seqNo of the last MeterValues"*; it now says the sequence
    **continues** — the next value after the last, not a repeat of it. Wire-inert, but it
    reversed which of two readings the schema endorsed, and the minority one had a receiver
    seeing a repeat where it MUST verify an increment.
- `tests/Fixtures/test-vectors/` — re-vendored. **160 valid + 157 invalid = 317**, byte-identical
  to the tag's `conformance/test-vectors/`. Two moves, both consequences of `minProperties`:
  - `valid/transaction/meter-values-event-minimal.json` carried `"values": {}` — the shape the
    new schema forbids. **It was a valid vector encoding an invalid payload**, so a correct
    schema re-vendor on its own turns the parity suite red on it. It now carries one reading.
  - `invalid/transaction/meter-values-event-empty-values.json` — **new**, and it is the old
    content of the file above, moved across the boundary. The rule is now falsifiable rather
    than merely stated.
- **Nothing in this repository checks that second bullet.** `schemas/` has a byte-identity
  gate; the vendored corpus has none, in either SDK. See *The gap this release does not close*.

### Changed

- **`OsppErrorCode::COMMAND_PRE_EMPTED`'s docblock was narrower than the code it documents.**
  It was written against spec `v0.11.1` and said `details.wouldBe` **MUST** carry the code the
  station would have answered — unconditionally. `v0.15.0` widens `6008` to the two kinds of
  pre-empt it always had, and on the second one `details.wouldBe` **MUST be absent**: a
  *server-protective* refusal (the open command circuit breaker is the defined case) is not a
  prediction about the station, and naming a code the station never gave is precisely the
  borrowing the entry exists to forbid. `details.reason` is promoted SHOULD → MUST, because it
  is the one member present on both. The docblock now carries both kinds and the fail-safe
  default — **absent `wouldBe` means the command did not run, and no outcome may be assumed.**

  No accessor and no gated column changed, so nothing in `src/` moved but the comment. Which is
  the uncomfortable part: a docblock stating a MUST the specification has since relaxed is
  wrong in the one direction that matters, and no gate in this repository can see it. The
  registry parity gate parses columns 1–4 only — `code | errorText | Severity | Recoverable` —
  and stops before Description and Recommended Action. That is deliberate and correct (§1.4
  forbids emitting a registry Description verbatim), but it means the prose this SDK *does*
  carry, in docblocks, is compared against nothing.

### Fixed

- **`tests/Fixtures/test-vectors/README.md` was stale by ten minor spec releases** — it
  announced `**OSPP Version:** 0.5.0` and documented the vector naming convention as
  `{action}.{variant}.json`, which the spec corrected to `{action}-{variant}.json` and which
  no vector in the corpus has ever used. Re-vendored with the rest of the tree.

  It is worth more than a housekeeping line, because it is the **measurement** of the gap: this
  file drifted for ten releases inside a directory nothing diffs, and the only reason anybody
  looked is that a different file in the same tree finally broke a test.

### Not in this release

- **No `src/` behaviour change, and none was needed.** This SDK models no per-message
  error-code set, so `3012`/`3013` becoming permitted ReserveBay responses at `v0.14.0` is a
  nil code change here. `5107` and `6008` — the two codes `v0.15.0` touches — changed only in
  their **Description** and **Recommended Action** cells; `code | errorText | Severity |
  Recoverable` are byte-identical at both tags, `recommendedAction()` transcribes neither code,
  and there is deliberately no `errorDescription()` accessor at all. Verified, not assumed:
  `scripts/check-error-registry.sh` reports **118/118 agreeing** against `v0.15.0`.
- **The vendored crypto corpus needed no work.** `conformance/test-vectors/crypto/` is
  byte-identical between `v0.13.0` and `v0.15.0`; all four gated files still match.

### The gap this release does not close

Both SDKs vendor two artefacts from the spec — the schema tree **and** the conformance
corpus — and both CIs byte-diff only the first. So the schemas cannot drift and the vectors
drift freely, which is exactly what happened, and the failure mode is inverted: a maintainer
who does the *right* thing (`cp -r spec/schemas`, bump `.spec-ref`) gets a red suite pointing
at a hardcoded number, with nothing anywhere saying the corpus was the other half of the job.
This release walked that path deliberately and confirms it: re-vendoring `schemas/` alone puts
`ConformanceVectorTest::theVendoredCorpusIsComplete` at *"Failed asserting that 157 is
identical to 156"* — a count, naming no file, in a test whose name says *complete*.

The two literals it asserts are still literals. They are updated here (`156` → `157`; `160`
unchanged) and both now carry a comment saying what they are: a **second copy of a fact about
the corpus, not a check on it**. Specified in the spec's `KNOWN-ISSUES.md`, and scoped but
deliberately **not built** here — a `diff -rq` of the whole vendored tree against the spec
clone, never a hand-maintained file list, plus a parsed count asserted `> 0`, because a gate
that reads zero vectors must not report a pass.

A third instance turned up while scoping it, and it is already live: `scripts/check-crypto-vectors.sh`
gates its four files **from a hand-written list**, and the spec's crypto corpus has had a
fifth, `mqtt-mac.json`, which is vendored in **neither** SDK. Both gates report OK. Not fixed
here — nothing consumes that vector yet, and adding it is a change to the corpus rather than
to this bump — but it is the same defect the list form always produces, and it is the reason
the replacement must diff a tree.

### Verification

- `vendor/bin/phpunit` — **1192 tests, 6058 assertions**, green (from 1191/6056; the new
  invalid vector is the one added test).
- `vendor/bin/phpstan analyse --level=9 src/` — no errors.
- `scripts/check-schemas.sh` — `schemas/` byte-identical to spec `v0.15.0`.
- `scripts/check-error-registry.sh` — 118/118 against `v0.15.0`.
- `scripts/check-config-registry.sh` — 29/29 against `v0.15.0`.
- `scripts/check-crypto-vectors.sh` — 4/4 byte-identical against `v0.15.0`.
- `diff -rq` of `tests/Fixtures/test-vectors/{valid,invalid}` against the tag — clean, by hand,
  because no gate does it.

---

## [0.15.0] — 2026-08-12

**SDK-pair release against spec `v0.13.0`** ([ADR-001](https://github.com/ospp-org/spec/blob/main/adr/ADR-001-cross-repo-lockstep-versioning.md)).
Released at the same version as `@ospp/protocol` **0.15.0**, from the same spec pin.

`.spec-ref` **unchanged at v0.13.0** — no schema moves in this release. `schemas/` is
byte-identical to the same spec tag it was byte-identical to at 0.14.0.

> **Behaviour change, narrow: the default wire `protocolVersion` moves `0.2.1` → `0.3.0`.**
>
> This affects only a consumer that takes the default — one that neither installs a
> `ProtocolVersion::setDefaultResolver()` nor otherwise sets the version explicitly. Such a
> consumer previously announced `0.2.1`, which spec Chapter 08 stopped sanctioning at spec
> v0.10.0 and which the production fleet does not speak; it now announces the value Chapter 08
> mandates. If you are pinned to a peer whose supported set is `{0.2.1}`, set the version
> explicitly before upgrading — the SDK will no longer pick it for you.
>
> Both known consumers already override, so the practical blast radius of this release is
> **zero**, and that is the uncomfortable part rather than the reassuring one: see below.

### Fixed

- **`ProtocolVersion` defaulted to `0.2.1` where Chapter 08 says `0.3.0`, in two places.**
  `ValueObjects\ProtocolVersion::default()` and
  `Enums\ConfigurationKey::PROTOCOL_VERSION->defaultValue()` are one fact stored twice, and
  both were stale in the same direction since spec v0.10.0 — four minor releases. `sdk-ts`
  carried the identical pair of defaults, so both are corrected in the same release pair;
  fixing either alone would have opened a cross-SDK disagreement where there had been none.

  **Why it survived four releases, which is the durable part.** Both consumers had already
  routed around it, independently and correctly: `csms-server` installs a config-driven
  resolver and sets `OSPP_PROTOCOL_VERSION=0.3.0`; `ts-station-simulator` carried a local
  `WIRE_PROTOCOL_VERSION = '0.3.0'` whose docblock named "when the SDK's own default is
  corrected, delete this" as its exit condition. A default every caller overrides is a
  default nothing exercises, so no test, no deployment, and no wire capture could report it
  wrong. The one thing it could still block was the gate that would have caught it — and it
  blocked exactly that, for four releases, which is how a zero-impact defect ends up being
  the most expensive one in the file.

  The simulator's constant is deleted in its matching bump. A workaround that names its own
  deletion condition *and then reaches it* is the intended shape; reaching it is the rare half.

### Added

- **`config-registry` CI job — Chapter 08 is now gated like the other three registries.**
  `scripts/check-config-registry.sh` shipped in 0.14.0 but was deliberately left unwired,
  because it was **red on `ProtocolVersion`**. It compares all 29 `ConfigurationKey` cases
  against `spec/08-configuration.md` on `type`, `default`, `access` and `mutability`, in both
  directions, and refuses to report a pass on fewer than 25 parsed rows — the
  empty-dataset-is-green trap.

  With the default fixed, the gate reports `all 29 keys agree` and the job is wired. **That
  ordering is the point and is worth copying: fix the divergence, watch the gate go green on
  its own, then wire it.** Wiring a red gate with the failing case suppressed leaves behind a
  suppression that outlives everyone's memory of why it was added.

  `schemas/`, `OsppErrorCode` and the crypto corpus were already gated against the spec.
  Chapter 08 was the last registry in this package compared only against itself — and
  `tests/Unit/Enums/ConfigurationKeyTest.php` did not fill that gap, because it was written
  from the enum and so inherited the enum's blind spots. That is the general reason this is a
  CI job against an upstream source rather than another assertion in `tests/`.

- **A note on the exec bit, in the workflow, next to the thing it protects.** 0.14.0 committed
  `check-error-registry.sh` and `check-crypto-vectors.sh` as `100644` while invoking them as
  `run: scripts/…`, so both died `Permission denied` on every push and **0.14.0 shipped red**
  with two gates that had never once executed. All four gate scripts are `100755`; the check
  when adding a fifth is the MODE, not the file list.

### Changed

- `tests/Unit/ValueObjects/ProtocolVersionTest.php::defaultReturnsZeroOneZero` →
  `defaultReturnsTheChapter08Default`, and
  `tests/Contract/Envelope/MessageBuilderContractTest.php::default_protocolVersion_is_0_1_0` →
  `default_protocolVersion_is_the_chapter_08_default`. Both names said `0.1.0` while both
  bodies asserted `0.2.1`: the assertions were updated at the last bump and the names were
  not. Renamed to the source of truth rather than to `0_3_0`, so the next move cannot leave
  them lying either.

---

## [0.14.0] — 2026-08-12

**SDK-pair release against spec `v0.13.0`** ([ADR-001](https://github.com/ospp-org/spec/blob/main/adr/ADR-001-cross-repo-lockstep-versioning.md)).
Released at the same version as `@ospp/protocol` **0.14.0**, from the same spec pin.

`.spec-ref` **v0.11.1 → v0.13.0**.

> **BREAKING — this changes MAC bytes, and both ends of a link must move together.**
>
> This release corrects how a message is reduced to the bytes that get signed. A peer on
> 0.13.0 and a peer on 0.14.0 therefore compute **different MACs for the same message**, and
> the receiver rejects what it cannot verify. This is not an optional upgrade: it requires
> **both sides to move**, and — because a station and a server are different deployments on
> different schedules — it requires them to move in a **coordinated window**.
>
> **The dangerous part is how narrow the break is.** MACs are unchanged for any message whose
> strings are ASCII and whose object keys are ordinary identifiers — which is nearly every
> message on a real fleet. The golden HMAC vectors in this release carry the **same
> `expectedMac` values as 0.13.0**, unchanged. So a mixed fleet does not fail on upgrade. It
> works, for days, until one message of an affected shape crosses the wire:
>
> - **this SDK's break:** any signed message carrying **U+2028 or U+2029** in any string —
>   33 free-string sites on the signed path, including `messageId` and `action`, which are on
>   *every* message;
> - **the sdk-ts break, which reaches you through the peer:** any message whose open objects
>   — `DataTransfer.data` (both directions), `SecurityEvent.details`, `StartService.params` —
>   carry keys that are **integer-like** (`"2"`, `"10"`) or **non-BMP**.
>
> That one message fails verification and is rejected, nothing else is affected, and nothing
> in the failure points at a version skew. Plan the window; do not let the quiet period
> convince you the fleet is homogeneous.

### Fixed

- **`U+2028` and `U+2029` were escaped, and `06-security.md` §4.8.1 step 3 requires them
  literally.** The rule admits exactly three reasons to escape — control characters, `"`,
  `\` — and requires every other character to be emitted literally. U+2028 LINE SEPARATOR
  and U+2029 PARAGRAPH SEPARATOR are Unicode categories Zl and Zp, not control characters,
  and JSON itself mandates escaping only below U+0020.

  PHP escapes them anyway, and keeps escaping them under `JSON_UNESCAPED_UNICODE`, because
  the flag was written to protect a **pre-ES2019 JavaScript** consumer that could not parse
  them inside a string literal. That is one host language's hazard, and canonical form is not
  written for one host language. `JSON_UNESCAPED_LINE_TERMINATORS` is now set, and the
  docblock says why it must not be "cleaned up" as redundant.

  `sdk-ts` emitted both literally all along — `JSON.stringify` never escaped them — so this
  SDK was the side that diverged, on any of the 33 free-string sites.

- **`5004 ELECTRICAL_SYSTEM` was `recoverable: true`; the spec has said `false` since
  v0.8.0** — eight spec releases. It is a §7.2 Level 3 (Faulted) entry trigger whose exit is
  physical intervention + operator verification + station reboot. A welded relay or a lost
  phase persists while the measured voltage reads nominal, so "power came back" does not mean
  the fault cleared, and a welded relay may leave the bay energised after the station believes
  it cut power. A consumer treating the fault as self-clearing could return that bay to
  service.

  `isRecoverable()` had no arm for it and fell through to `default => true`, which is how a
  value nobody chose became a value the SDK asserted. The hand-written unit test then pinned
  that value, so the test defended the defect. It survived because the only thing checking the
  registry was the *other* SDK, which was wrong in exactly the same way. An audit recorded
  `recoverable` as "identical — 0 diffs" and that was true. Two implementations that agree are
  not evidence; they are one opinion held twice. See *Added* for the gate.

### Added

- **`scripts/check-error-registry.sh`** (CI job *Error registry vs spec registry*) — compares
  every `OsppErrorCode` case against the registry table in the spec's `07-errors.md` at
  `.spec-ref`, on `errorText`, `severity`, `recoverable`, and the code set in both directions.
  This is the gate whose absence let 5004 drift for eight releases: the schema gate could not
  see the registry, because it is a Markdown table and not a schema, and the spec's own
  `verify-protocol.sh` scrapes that table with a regex that stops before the `Recoverable`
  column. `httpStatus()` and `category()` are deliberately not checked — the spec declines to
  give a code either one.

  It refuses to report a pass if it parses fewer than 100 rows, so a reformatted table fails
  loudly instead of vacuously.

- **`scripts/check-crypto-vectors.sh`** (CI job *Crypto corpus byte-identity gate*) — this SDK
  had no crypto-corpus gate at all: `schemas/` was checked against the spec and the crypto
  vectors were not.

- **Canonical-form vectors are now VENDORED FROM THE SPEC**, byte-identical, at
  `tests/Contract/Crypto/fixtures/canonical-form.json` ←
  `conformance/test-vectors/crypto/canonical-form.json`. Previously the canonical-form vectors
  were two hand-maintained copies, one per SDK, whose agreement was asserted in a comment and
  by nothing else — so both could be edited into agreeing with each other and disagreeing with
  the spec, which is the shape of every defect in this release. The spec **recomputed** the
  oracle values from the §4.8.1 rule text in a third implementation rather than adopting either
  SDK's output; both SDKs reproduce all 17 byte for byte.

- **A falsifiability check** (the spec's Category 20, run on the vendored copy). A corpus that
  no longer separates right from wrong passes silently, so the suite runs the defect this SDK
  actually shipped — `json_encode` without `JSON_UNESCAPED_LINE_TERMINATORS` — over the same
  vectors and requires the corpus to **reject** it. If no vector discriminates, the test says
  so instead of reporting green. `sdk-ts` runs the same check against its own defect (UTF-16
  key ordering); PHP arrays never had that one, because they do not reorder integer-like keys
  and `SORT_STRING` is already a byte comparison.

- **`canonical-mac-strip.json`** — pins the boundary between §4.8 and §5.3 step 1 for the same
  message: `CanonicalJsonSerializer::serialize()` keeps a top-level `mac`,
  `MacSigner::canonicalize()` removes it, and neither touches a nested one. This SDK was
  already correct here; `sdk-ts` stripped inside its `canonicalize()`, so the two disagreed on
  every message carrying a `mac` and no vector in either repo had one to notice with. It is
  deliberately *not* vendored: the spec's corpus carries no message with a `mac`, because §4.8
  says nothing about the field, and that silence is exactly what hid the defect.

### Spec pin

`.spec-ref` **v0.11.1 → v0.13.0**, re-vendored and byte-identity verified. Schema changes
across that range are `description`-only, plus `trigger-message-request.bayId`, which was an
unconstrained string where every other `bayId` is a `$ref` to `bay-id.schema.json`.

---

## [0.13.0] — 2026-08-07

**SDK-pair release against spec `v0.11.1`** ([ADR-001](https://github.com/ospp-org/spec/blob/main/adr/ADR-001-cross-repo-lockstep-versioning.md)).
Released at the same version as `@ospp/protocol` **0.13.0**, from the same spec pin.

MINOR, not PATCH: three enum members are added, and a consumer pinned to `^0.12.0`
must opt in to receive them.

### Added

- `SessionEndReason::OPERATOR_STOPPED` — an operator ended the session deliberately
  (a Reset carrying `force: true`, or a station disable). The **only** member that
  bills a non-zero amount for a session the station did not run to completion.
  `Deauthorized` reads as the nearest alternative and carries "Session MUST be
  billed at zero", so reusing it delivers a wash and charges nothing for it.
- `OsppErrorCode::SERVICE_NOT_BOUND = 3019` — the server holds no service→program
  binding and cannot form a conforming StartService. Error / recoverable / **409**.
- `OsppErrorCode::COMMAND_PRE_EMPTED = 6008` — the server stopped a command locally
  that it could see the station would refuse, carrying `details.wouldBe`. Warning /
  recoverable / **409**.

Severity and HTTP status are asserted for both new codes rather than left to the
`match` default arms, which would have given 3019 `Warning` and both `500`. A code
carrying the wrong status is worse than one carrying none: a client retries a 500
and does not retry a 409.

### Fixed

- **`4020`'s recommendedAction named a field the request no longer has.** It said
  "correct the declared `bayCount`"; the spec has said `bays` since v0.11.0, and the
  text was never regenerated because it is hand-written here rather than generated.
  It also told the reader to "carry both counts", the two numbers that are equal
  whenever a bay is swapped — the exact case the set comparison exists to catch.

  The replacement is **shortened relative to the spec's registry text**, to fit
  Appendix C's 500-char wire bound. `07-errors.md` §1.4 expressly permits this
  "provided the corrective action itself survives". That divergence is deliberate:
  syncing this string byte-for-byte with the registry would make every naive
  emitter produce a non-conforming payload. Do not "fix" it by lengthening it.

### Changed

- vendored `schemas/` refreshed from spec `v0.11.1`; `.spec-ref` → `v0.11.1`.
- registry counts: 116 → **118** cases, 3xxx dense to **3019**, server family 8 → **9**.

---

## [0.12.0] — 2026-08-05

**SDK-pair release against spec `v0.11.0`** ([ADR-001](https://github.com/ospp-org/spec/blob/main/adr/ADR-001-cross-repo-lockstep-versioning.md),
*SDK-pair releases against a spec tag*). Released at the same version as
`@ospp/protocol` **0.12.0**, from the same spec pin.

`.spec-ref` **v0.10.0 → v0.11.0**.

> **BREAKING — this release implements a contract that breaks every consumer built against
> the previous one.** Spec `v0.11.0` breaks the wire in five places at once and folds them
> into a single `protocolVersion` move to `0.3.0`. Symbols are deleted rather than
> deprecated, because in each case there is no correct narrower thing for the old one to
> mean. There is no compatibility window and no shim.
>
> **Deploy order is not a preference.** A receiver must accept a new form before any sender
> emits it. Two items are a total fleet outage if enforced early: exact-match version
> negotiation, and MAC enforcement. Read *Breaking changes, and the order they must ship in*
> in the spec's CHANGELOG before deploying any of this.

### Added

- **`StationState` + `StationTransitions`** — the station's own machine, six states, and the
  **outermost** one: every other machine on a station is scoped inside it. Neither SDK had
  it. This SDK had `BootNotificationStatus` as three bare enum cases with no behaviour and
  nothing consuming them, so `3018 TOPOLOGY_MISMATCH` depended on a state that existed
  nowhere structurally.

  `Pending` and `Rejected` are **restricted** states and differ in exactly one respect —
  whether the station answers commands. That resolves a three-way contradiction in
  `boot-notification.md`: rule 5 said the station MAY operate normally, rule 3 eleven lines
  above defines "normal operation" as the post-`Accepted` state, and rule 2 forbade it from
  sending anything at all. Under one reading a `Pending` station activates hardware on a
  StartService and delivers an unpaid wash. Resolved: a restricted station answers commands
  (`Pending` only), sends nothing unsolicited, and serves no customer — but a session
  already running continues and settles, so a customer who has paid is served.

  The §1.4 rows are methods rather than prose. The load-bearing one: `Pending` **holds** a
  session key and `Rejected` does not. A test pins the two against each other — no state may
  answer a command in a state where it holds no key.

- **`BayTopology`** — this SDK carried no topology model of any kind; `bayCount` appeared
  only inside the text of error 4020's recommended action. `bay-topology.schema.json` is
  `$ref`'d by both the request's `bays[]` and the 3018 response's `details.expected` /
  `details.declared`, so there is one definition instead of two copies.

  Comparison is by **set**, in both directions. Order carries no meaning, so a station that
  re-orders its declaration between boots has not changed its hardware and must not be held
  out of service for it; and the mismatch is symmetric, so a bay present on one side only is
  a mismatch whichever side it is on. Labels are never compared — a corrected typo in a
  firmware constant MUST NOT put a station into `Pending`. Bounds (64 bays, 32 programs) are
  asserted against the vendored schema rather than transcribed from it.

- **`ProtocolVersion::isSupportedBy(iterable $set)`** — takes an iterable so a server can
  pass whatever it holds its configured set in. An empty set accepts nothing, rather than
  silently accepting every station.

- **`3017 PROGRAM_NOT_DECLARED`** — the `programNumber` was never declared for that bay. A
  *reference* failure, not a value failure: the ordinal is well-formed and in range, it
  simply names nothing. That is why it is not 3015, and not 3003, which presupposes the
  program IS declared and is merely unavailable right now. Recoverable: `false`.

- **`3018 TOPOLOGY_MISMATCH`** — the boot declaration disagrees with the provisioned
  topology, in either direction. In 3xxx and not the transport range: it is a disagreement
  about hardware, not a transport failure. Recoverable: `true` — the station is out of
  service but reachable.

  Registry total 114 → 116 in every place this SDK states one; the stale class docblock
  claiming 107 goes with it. `httpStatus` for both is this SDK's extension and has no clause
  (§2.4's table lists neither, because both are MQTT-only). 3017 takes 404 following the
  registry's own "one code per identifier KIND" analogy, where 3005/3006/3012 are all 404;
  3018 takes 409, the shape of a disagreement between two declarations. Both match
  `@ospp/protocol` exactly.

- **`EffectedBy`** — the canonical bay table's party column.

- **`canonical-json-vectors.json`** — eleven shared cross-language vectors, byte-identical
  with the sibling copy in `@ospp/protocol`.

### Changed

- **`BayTransitions::canTransition()` now requires the party** rather than defaulting to
  one. The default *was* the merge that caused the divergence: the table merged the bay a
  station operates with the bay a server believes in, and each SDK read the merge the other
  way. This SDK had the profile's eighteen station rows; it gains the two the arc added —
  `Unknown -> Occupied` and `Unknown -> Finishing`, for a station that rebooted mid-session
  and owes a truthful post-boot report — and the six `Server` rows behind an explicit party
  argument. Twenty `Station` pairs, six `Server`, twenty-six in all.

- **Signing default is `All`**, and the config key moves Dynamic → Static: the mode is bound
  to the session key, which is issued at boot.

- **`MessageSigningRegistry` replaces `CriticalMessageRegistry`**, holding only the three
  structural exemptions and keying on `(action, messageType)` rather than on `action` alone.
  The old axis could not tell the BootNotification REQUEST from its RESPONSE, and the two
  are exempt for different reasons — one precedes the key, one carries it.

- **`opis/json-schema` moves from `suggest` to `require-dev`.** It is a test dependency; the
  shipped package gains no runtime requirement.

### Removed

- **`ResetType`** — deleted, not narrowed. `Hard`/`Soft` are gone and there is no remaining
  value for the enum to hold: one reboot operation, carrying an optional `force`. No value
  of the message clears credentials, because OSPP keeps no bootstrap credential and a remote
  wipe would leave the station unreachable by every channel it has.

- **`ProtocolVersion::isCompatibleWith()`** — deleted rather than narrowed. A consumer still
  calling it would read a wrong answer as a right one, and the relation it expressed does
  not exist any more. A shared MAJOR implied nothing: MAJOR is 0 for every version OSPP has
  shipped, so the rule classified 0.1.0 and 0.10.0 as compatible while the pre-1.0 policy
  directly above it licences breaking changes between 0.x minors. That contradiction cost
  money — a 0.4.0 station accepted by a 0.3.0 server delivers a full session and emits
  SessionEnded with a reason the older schema rejects, and SessionEnded is the sole billing
  source when no StopService was issued. Session delivered, never billed, on a pairing the
  rule told the server to accept.

- **`SigningMode::Critical`** — with everything signed it selected nothing, and the 47-row
  per-message classification table goes with it.

- **`bayCount` / `bayIds`** from the wire, with no compatibility window; **`services[]` on
  StatusNotification becomes `programs[]`**.

### Fixed

- **PHP could not express an empty JSON object, and Heartbeat is one.** The two reference
  implementations disagreed on `{}`, and the spec names both as authoritative for
  canonicalization — so this was not a bug in one of them, it was a disagreement about what
  the protocol IS. Measured on a Heartbeat envelope, before the fix:

  ```
  ospp/protocol    {"action":"Heartbeat",...,"payload":[],...}
                   mac yGfzxYzyIdpJTF2GtDB4qRwmQxBT1FasV0NzGTdmXoo=
  @ospp/protocol   {"action":"Heartbeat",...,"payload":{},...}
                   mac rXmjv9B7uauqFPHM00XjsPKiynyk6PdeRwoEiAoLOfU=
  ```

  `heartbeat-request.schema.json` declares `"properties": {}` with `additionalProperties:
  false`, so a Heartbeat REQUEST payload is exactly an empty object — and Heartbeat is one of
  the thirteen message types this release newly requires to be MAC'd. A PHP peer and a TS
  peer would have rejected every heartbeat between them: CORE-007's 3.5× timeout fires on a
  healthy station, CORE-008 marks its bays `Unknown`, and the server stops selling on
  hardware that is there.

  The cause: `serialize(array $data)` could not express `{}` at all — PHP's `[]` is ambiguous
  between an empty array and an empty object, and `json_encode` resolves it to `[]`. The
  serializer now accepts `\stdClass` alongside `array` and preserves the distinction at every
  nesting level; an empty **array** still emits `[]`, which is the other half of the same
  rule. This needed no spec clause: §4.8.1 does not state how an empty object renders because
  JSON already distinguishes the two container types and the schema says which one the payload
  is. What was missing was the ability of this class to say it.

- **`MacSigner` failed OPEN and now fails closed.** `base64_decode(..., true)` returning
  `false` degraded to the **empty HMAC key** and still returned a well-formed MAC, so two
  peers both holding garbage verified each other successfully, and anyone who knew the key
  was invalid could forge with the empty one. Now `sign()` raises — §5.7 forbids both of its
  alternatives, publishing unsigned and dropping silently — and `verify()` returns `false`,
  because a receiver holding no key cannot verify and cannot therefore accept.

  Key length is deliberately **not** checked: §5.2 requires the *server* to generate 32
  bytes, but `boot-notification-response.schema.json` accepts a base64 string of 1–1024
  characters and no clause makes a station reject a conforming response carrying another
  length.

- **This SDK ran none of the spec's conformance corpus.** It vendored the complete schema set
  and asserted only that the files existed and that one of them parsed as an array. That is
  the asymmetry that let the two SDKs disagree about the wire while both suites were green:
  the byte-identity gate catches a vendored schema that has *drifted*, but nothing caught a
  schema whose content this SDK *misunderstood*, because nothing ever validated a payload
  against one.

  **316 vectors now run here** — 160 that must pass and 156 that must be rejected, the same
  counts the spec's own `verify-schemas.py` reports. Two details are the difference between
  coverage and the appearance of it: an unmapped vector **fails** rather than being skipped,
  and the filename-prefix search goes down to **one** part, because `hello`, `receipt` and
  `challenge` are single-word schema names and a loop that stops at two never reaches them —
  fifteen BLE vectors.

- **Stale spec cross-references.** Spec `v0.11.0` inserted the station machine as
  `05-state-machines.md` §1 and renumbered every machine under it. Two docblocks in `src/`
  still cited §1.2 — now the **station's** states — while meaning the bay's, which is now
  §2.2.

### Vendored

- `schemas/` — byte-identical to spec `v0.11.0`, 86 files, verified through
  `scripts/check-schemas.sh`'s own clone path.
- `tests/Fixtures/test-vectors/` — 316 vectors, byte-identical to the tag's
  `conformance/test-vectors/`.
- `tests/Contract/Crypto/fixtures/signing-classification.json` — `specRef` was a sentence
  explaining that the tag did not yet exist; it now reads `v0.11.0`. Byte-identity with the
  `@ospp/protocol` copy is preserved.

### What breaks

| Caller | Breaks | What to do |
|---|:---:|---|
| Uses `ResetType` | **yes** | Deleted. One reboot operation, optional `force`. |
| Calls `ProtocolVersion::isCompatibleWith()` | **yes** | Use `isSupportedBy($set)`. There is no compatibility relation any more. |
| Uses `SigningMode::Critical` or `CriticalMessageRegistry` | **yes** | Use `MessageSigningRegistry`; the default is now `All`. |
| Calls `canTransition($from, $to)` on a bay | **yes** | Pass the `EffectedBy` party. There is deliberately no default. |
| Builds a BootNotification with `bayCount` / `bayIds` | **yes** | Declare `bays[]`, each with `bayNumber` + `programNumbers`. |
| Reads `services[]` off a StatusNotification | **yes** | Now `programs[]`, and the set MUST EQUAL the bay's declaration. |
| Relies on a MAC being produced from an invalid key | **yes** | `sign()` now raises; `verify()` returns `false`. This was a forgery path. |
| Canonicalizes a payload with an empty object | **yes, silently, before now** | `{}` no longer serializes as `[]`. Any MAC computed by 0.11.0 over an empty-object payload was wrong. |
| Passes `array` to `CanonicalJsonSerializer::serialize()` | no | Still accepted; `\stdClass` is now accepted alongside it. |

---

## [0.11.0] — 2026-07-30

**SDK-pair release against spec `v0.10.0`** ([ADR-001](https://github.com/ospp-org/spec/blob/main/adr/ADR-001-cross-repo-lockstep-versioning.md),
*SDK-pair releases against a spec tag*). Released at the same version as
`@ospp/protocol` **0.11.0**, from the same spec pin.

`.spec-ref` **v0.9.0 → v0.10.0**.

> **Breaking, for callers that cross the wire boundary — and NOT in the way the headline
> suggests.** Spec `v0.10.0` removed `Unknown` from `bay-status.schema.json`. **`BayStatus`
> does not lose the case.** Read the next paragraph before changing any code.

### Changed

- **`BayStatus::fromOspp()` now rejects `Unknown`**, in any casing, with a `\ValueError`
  naming the six reportable states. This is the whole of the behavioural break.

  **The enum keeps all seven cases**, and `it_has_exactly_seven_cases` still asserts seven.
  Deleting `UNKNOWN` is the obvious reading of the spec change and it is wrong for this SDK,
  for a reason worth stating so nobody "completes" the job later: **this enum is not the wire
  vocabulary.** Its backing values are lowercase (`'unknown'`), the wire's are PascalCase, and
  `fromOspp()`/`toOspp()` have always been the adapter between the two. `UNKNOWN` is a state
  the enum must be able to express — a server holds a bay there before its first report, and
  in the reference server it is a *persisted* value: the `bays.status` column is a Postgres
  enum type that **defaults** to `'unknown'`, and live rows hold it. Removing the case would
  raise `ValueError` on hydrating every one of them.

  So the wire narrowed and the vocabulary did not. Seven states, six reportable.

- **`BayStatus::isReportable()`** — new. `false` for `UNKNOWN`, `true` for the other six.
  Derived from the case rather than a list, so it stays correct if a case is ever added. Gate
  on it before putting a status on the wire.

- **`BayStatus::toOspp()` is unchanged and remains total**, `UNKNOWN` included. It is used for
  logging and display as well as serialisation, and making it partial would throw inside a log
  line. The consequence is deliberate: `fromOspp(toOspp())` round-trips for the six reportable
  states and **not** for `UNKNOWN` — which is exactly the spec's model of a state held by both
  parties and transmitted by neither. The asymmetry is pinned by a test rather than left as a
  gap.

### Vendored

- `schemas/common/bay-status.schema.json` — enum 7 → 6 values; `"Unavailable", "Unknown"` →
  `"Unavailable"`. One file of 85 changed. `scripts/check-schemas.sh` passes against
  `v0.10.0`.

### What breaks

| Caller | Breaks | What to do |
|---|:---:|---|
| Parses a bay status off the wire via `fromOspp()` | **yes** | A payload carrying `Unknown` was already non-conforming and is now refused. Handle the `\ValueError`, or reject the message — do not fall back to `BayStatus::UNKNOWN`, which would re-admit exactly what the spec removed. |
| Holds, stores or compares `BayStatus::UNKNOWN` | no | The case is still there. Server-side `Unknown` is still required by CORE-008. |
| Calls `toOspp()` | no | Still total. |
| Exhausts `BayStatus::cases()` | no | Still seven arms. |
| Relies on `fromOspp(toOspp())` round-tripping every case | **yes** | It now holds for the six reportable states only. Filter on `isReportable()`. |

### Tests

742 → **748** tests, 4655 → **4665** assertions; phpstan level 9 clean. Six sites iterated
every case and broke — three round-trip tests plus
`from_ospp_handles_pascal_case_wire_values`, `from_ospp_also_handles_lowercase_input` and
`fromOspp_converts_PascalCase_for_all_7_cases`. All six are re-scoped through a derived
`reportable()` helper, and each removed assertion gained its inverse rather than being
deleted — the convention this repo set when 0.10.0 retired `Deferred`, so that nothing
silently readmits the value.

---

## [0.10.0] — 2026-07-29

**SDK-pair release against spec `v0.9.0`** ([ADR-001](https://github.com/ospp-org/spec/blob/main/adr/ADR-001-cross-repo-lockstep-versioning.md),
*SDK-pair releases against a spec tag*). Released at the same version as
`@ospp/protocol` **0.10.0**, from the same spec pin. The spec is **not** re-tagged:
it already carries `v0.9.0`, and re-tagging it to chase an SDK number would make the
tag mean the SDK's cadence rather than the contract's.

`.spec-ref` **v0.8.1 → v0.9.0**.

**Breaking, and the audience differs per change — read the three separately.** Spec
`v0.9.0` carried three independent bodies of work that shared a tag because none of
them cut one of their own. Two of them are breaking here, for **different** groups of
callers, and conflating them will send the wrong people looking for the wrong
problem:

| Body | Breaks | Who has to act |
|---|---|---|
| `Deferred` retired from the status enum | **consumers** | code that reads a `TransactionEventStatus`, or exhausts `cases()` without a default arm |
| `errorText` constrained to UPPER_SNAKE_CASE | **producers** | code that *emits* `errorText` as prose — a previously-valid payload is now schema-invalid |
| `provisioning-response` description | **nobody** | description text only, no validation behaviour change |

### Removed (BREAKING — consumers)

- **`TransactionEventStatus::DEFERRED`.** The enum drops to four cases:
  `ACCEPTED`, `DUPLICATE`, `REJECTED`, `RETRY_LATER`. A `match` or `switch` over
  `TransactionEventStatus::cases()` **without a default arm** loses an arm, and any
  reference to `::DEFERRED` no longer compiles.

  Spec 0.9.0 retired the value together with the `txCounter` gap-blocking rule it was
  invented to express — it had no design rationale of its own, having been added to
  the schema in spec 0.5.0 two days *after* the reference server began emitting it.
  Its stated exit, *operator-manual unblock*, was referenced normatively in five spec
  documents and implemented in **none**, so a transaction answered `Deferred` could
  not be settled by any code path in any repository. `RETRY_LATER` is now the only
  non-terminal status.

  **What is not removed:** the `TransactionEventStatus` enum itself and its remaining
  four cases. A station needs them to decide whether to delete its local copy.

### Changed (BREAKING — producers)

- **Vendored schemas re-vendored from spec `v0.9.0`.** 17 files changed. Only **one**
  belongs to the `Deferred` retirement; the other 16 are the two bodies that rode
  along in the tag:

  | Origin | Files | Change |
  |---|--:|---|
  | `Deferred` retirement | 1 | `mqtt/transaction-event-response.schema.json` — `status` enum 5 → 4, **and** the fourth `allOf` branch that required `reason` on `Deferred` |
  | `errorText` enforcement | 15 | `pattern: ^[A-Z][A-Z0-9_]+$` added wherever `errorText` pairs with `errorCode` at the same object level (16 declarations); **8** of the 15 also gained corrected descriptions |
  | provisioning trust anchor | 1 | `provisioning-response.schema.json` — `stationCaChain` description no longer names `brokerRootCa` as the universal anchor. **Description only** |

- **`errorText` is a machine-readable name and is now enforced as one.** Spec §1.3
  has always defined it as *"Machine-readable error name in UPPER_SNAKE_CASE (e.g.
  `BAY_BUSY`)"*, but only one of the sixteen schemas declaring it enforced that shape.
  The rest constrained length only, so any string passed — which is how a raw
  validator diagnostic reached firmware in the field the spec reserves for
  programmatic matching.

  **This is the change a *producer* could notice**: a payload that put prose in
  `errorText` and validated before will now fail validation. Emit the registry name
  (`BAY_BUSY`), and put prose in `errorDescription`, which exists for it.

### Verification

- **The schema-identity gate RAN**, and that is the claim — not its exit code.
  `scripts/check-schemas.sh` cloned `ospp-org/spec` at `v0.9.0`, checked out
  `7a448ed`, and reported *"OK — vendored schemas are byte-identical to spec
  v0.9.0"*. **Falsified before being trusted:** mutating one vendored schema makes it
  report `DRIFT detected` and name the file. Restored, re-run green. A gate that
  silently skips has happened in this repository's history, which is why the run is
  reported rather than the result.

- **The enum test is inverted, not deleted.** `deferred_is_distinct_from_retry_later`
  pinned the v0.5.0 semantics; it becomes `deferred_is_retired_and_no_longer_a_case`,
  asserting `tryFrom('Deferred')` is `null` and that `cases()` is exactly the four.
  Deleting it would have left nothing to notice the case coming back. Proven
  non-hollow: re-adding the case fails **two** tests by name —
  *"Failed asserting that actual size 5 matches expected size 4"* and *"Failed
  asserting that ...Enum (DEFERRED, 'Deferred') is null"* — then reverted.

- **No deferred test vector existed here to delete.** Unlike `sdk-ts`, this SDK
  vendors `schemas/` but **not** the conformance vector corpus, so the enum test is
  the whole of the coverage. Stated because the symmetry cannot be assumed.

- **Suite:** `paratest -p 28` → **OK (742 tests, 4655 assertions)**. Baseline measured
  on the clean tree before any edit: 742 tests / 4656 assertions. Test count unchanged
  (inversion, not deletion); the single assertion delta is exact — two removed from
  the value and `from()` tests, three replacing two in the inversion. `phpstan`: no
  errors.

- **One artifact was excluded rather than committed.** `cp -r` from the spec brought
  `schemas/README.md`, a file this repository has never carried and which
  `check-schemas.sh` explicitly `--exclude`s from comparison. It was caught in
  `git status`, not by the gate — the gate is configured to ignore it, so it would
  have passed review invisibly.

### Migration

```diff
- match ($status) {
+ match ($status) {
      TransactionEventStatus::ACCEPTED     => ...,
      TransactionEventStatus::DUPLICATE    => ...,
      TransactionEventStatus::REJECTED     => ...,
      TransactionEventStatus::RETRY_LATER  => ...,
-     TransactionEventStatus::DEFERRED     => ...,   // remove; unreachable
  };
```

A server still emitting `Deferred` is emitting a value the wire schema no longer
admits and no station can act on. There is no replacement status: the condition that
produced it — a `txCounter` discontinuity — is now settled normally and raised as an
operator alert on the **station**.

---

## [0.9.0] — 2026-07-29

Released in lockstep with `@ospp/protocol` **0.9.0**, from the same spec pin.
The two SDKs implement one contract and a consumer must be able to tell which
pair is coherent; `sdk-ts` jumps `0.7.0` → `0.9.0` to meet this one.

`.spec-ref` → **v0.8.1**. Vendored schemas unchanged and still byte-identical to
that tag — 0.8.1 corrected §4.4's endpoint rows and §3.4 registry text, not the
schemas. Verified byte-identical to `sdk-ts`'s vendored tree as well.

### Fixed

- **Four codes reachable over REST fell through to the `500` default**, each at
  an endpoint whose statuses in §4.4 do not include 500 — so a client-visible
  rejection was reported as a server fault.

  | Code | Was | Now | Endpoint |
  |------|----:|----:|----------|
  | `4008 WEBHOOK_SIGNATURE_INVALID` | 500 | **401** | `POST /webhooks/payment-gateway/notification` — lists 401 and nothing else |
  | `3002 BAY_NOT_READY` | 500 | **409** | `POST /sessions/start` |
  | `3007 SESSION_MISMATCH` | 500 | **409** | `POST /sessions/{id}/stop` |
  | `6007 SERVICE_DEGRADED` | 500 | **503** | §4.4 states this as a **MUST**, and forbids substituting 500 |

  `sdk-ts` already answered all four this way; this converges PHP onto it.
  **This is the change a consumer could notice**: a caller that mapped these to
  500, or that asserted the SDK returns 500 for them, sees different values.
  `503` is new to the set of statuses this enum can return.

### Documented

- **`httpStatus()` is an SDK extension, not the contract.** The method carried a
  comment citing "the note above this method"; no such note existed. It does
  now, and it records that the spec *declines* to make status a property of a
  code — `07-errors.md` §4.4, "The status is not a property of the code": §2.4's
  table "assigns no code a fixed status", and one code can honestly appear with
  more than one status (§2.4 lists `2008` under both `401` and `403`).

  This SDK and `sdk-ts` **disagree on 51 of the 114** codes here. Everything else
  in the two registries is identical: numbers, names, `severity`, `recoverable`,
  the category partition, the vendored schemas. Recorded in the spec's
  `KNOWN-ISSUES.md` as one finding together with `category()`, which has the same
  cause. Treat the result as a default for a server with no better answer, never
  as the status a code "has".

  The `default => 500` arm is **retained**. An earlier instruction to return
  `null` for unmapped codes is withdrawn: it predates the §4.4 language, and
  returning null still asserts a total function from code to status, merely with
  a hole in it. **`httpStatus()` remains `int`** — no return-type change.

## [0.8.4] — 2026-07-28

Transcribes **4020** `BAY_COUNT_MISMATCH` from the new `4.02x — Provisioning
Errors` sub-range at spec HEAD. A declared `bayCount` that does not match the
station's registered count is reachable on `POST /api/v1/stations/provision`
today (`CertificateManager.php:145-147` in csms-server) and was the last error
path on that endpoint with no registry code, while §2.4 makes `errorCode`
REQUIRED on every error of a specification-defined endpoint.

- `4020` → `422`, recoverable **true**, **no branch and no discriminator**: the
  check runs before the token is consumed, so the station resubmits the
  corrected count on the same token, and it is reachable only on a first
  provision — on a replay the token is the key and body drift is ignored.

`recommendedAction()` is the registry cell **verbatim**, asserted byte-identical
against the spec source. 374 characters against Appendix C's 500 bound.

Counts re-derived from `OsppErrorCode::cases()` rather than incremented: 114
total, `4xxx` = 20. Note the derived `category()` still reports `payment` for
`4020` as for every other provisioning code — recorded as an open defect in the
spec's KNOWN-ISSUES (the arithmetic range derivation at
`src/Enums/OsppErrorCode.php:143-155`), not fixed here, because making
`category` per-code is a cross-SDK change.

`.spec-ref` stays **v0.7.0**. phpunit 742 tests / 4656 assertions; phpstan
level 9 clean.

## [0.8.3] — 2026-07-28

Transcribes **4018** `PROVISIONING_TOKEN_CONSUMED` and **4019** `PUBLIC_KEY_INVALID`
from the registry at spec HEAD (`07-errors.md:362`, `:363`). Both are listed in
§4.4's row for `POST /api/v1/stations/provision` (`:509`), and §2.4 makes
`errorCode` REQUIRED on every error of an endpoint the specification defines — so
their absence left two reachable paths unable to carry a conforming envelope.

Both are reachable in csms-server today, which is why they are added rather than
deferred: `ProvisioningTokenConsumer.php:93` and `:195` and
`CertificateManager.php:279` throw the already-consumed / consumed-without-
certificate conditions 4018 covers, and a malformed bare receipt-signing key
raises 4019's. Both currently answer `422` with no `errorCode`.

- `4018` → `409`, recoverable **true**, branches on `details.reason`
  (`already_consumed` | `consumed_without_certificate`; absent ⇒ `already_consumed`).
- `4019` → `400`, recoverable **true**, branches on `details.phase`
  (`first-provision` | `retry`; absent ⇒ `retry`). Answers `400` like `4010`
  deliberately: the same defect must not vary by how the key was packaged.

`recommendedAction()` for both is the registry cell **verbatim**, not a
paraphrase — §1.4 binds the emitted value to the registry text and forbids a
generic severity-derived string. Asserted byte-identical against the spec source.
457 and 454 characters against Appendix C's 500 bound.

**Count comments were already stale and are swept to measured values**: the
`2xxx` header said 19 (actual 20, since 2019 landed in 0.8.0) and the `4xxx`
header said 14 (actual 17 before this change). README said 102 codes; actual was
111. Now 113 total, `4xxx` = 19, `2xxx` = 20 — every figure re-derived from
`OsppErrorCode::cases()` rather than incremented. Contract-test counts updated to
match.

`.spec-ref` stays **v0.7.0**. phpunit 742 tests / 4627 assertions; phpstan
level 9 clean.

## [0.8.2] — 2026-07-27

**Fix:** `4010 CSR_INVALID` had no `httpStatus()` arm and fell through to the
default `500`. The spec lists it under **400** in the §2.4 status table
(`07-errors.md:241`) and §3.4 states "At the provisioning endpoint: HTTP `400 Bad
Request`". A server routing this code through the SDK therefore turned a client
error into a server error on the wire.

Pre-existing — 4010 predates this cycle; it surfaced only when a caller began
resolving its status through the enum rather than hard-coding one.

Adds a test pinning the status of every code the provisioning endpoint can emit
against the registry table, so the next code with a missing arm fails loudly
instead of silently becoming a 500. No new cases; count stays 111. `.spec-ref`
stays **v0.7.0**. phpunit 742 tests / 4569 assertions; phpstan level 9 clean.

## [0.8.1] — 2026-07-27

Completes `recommendedAction()` for the codes `POST /api/v1/stations/provision`
can emit. 0.8.0 transcribed only the four codes it added, leaving **4010**
`CSR_INVALID`, **6001** `SERVER_INTERNAL_ERROR`, **6006** `RATE_LIMIT_EXCEEDED`
and **6007** `SERVICE_DEGRADED` returning `null` — and `07-errors.md` §2.4 makes
the field REQUIRED on every REST error of an endpoint the specification defines,
so a `null` would have produced a non-conforming envelope on the wire.

Now pinned by a test over the **whole reachable set**, not just the codes a given
version adds, so the next code that becomes reachable fails loudly rather than
emitting `null`. Scope stays the provisioning surface: the remaining codes return
`null` until a surface that emits them needs them.

No new cases; count stays 111. `.spec-ref` stays **v0.7.0**. phpunit 741 tests /
4563 assertions green; phpstan level 9 clean.

## [0.8.0] — 2026-07-27

Registers the four provisioning-identity error codes the spec's §2 bound-set rule
needs, so a server can express them on the wire: **2019** `PROVISIONING_TOKEN_INVALID`
(401), **4015** `PROVISIONING_KEY_MISMATCH` (409), **4016** `PROVISIONING_KEY_REUSE`
(422), **4017** `PROVISIONING_REQUEST_INVALID` (400). Severity, `recoverable` and
HTTP status are transcribed from the spec registry rows, not chosen here. Case count
107 → 111 (auth 19 → 20, payment 14 → 17).

Adds `OsppErrorCode::recommendedAction(): ?string`, carrying the registry's per-code
corrective action verbatim for those four. Spec `07-errors.md` §1.4 makes this field
a property of the **code** and forbids substituting a generic severity-derived
string, so it cannot be synthesised at the emitter. Returns `null` for codes not yet
transcribed; a caller emitting the REST Error Object (§2.4), where the field is
REQUIRED, must treat `null` as a defect rather than emitting an empty string.

There is deliberately **no** `errorDescription()` accessor: that field is
per-occurrence, and §1.4 states an implementation MUST NOT emit a registry
*Description* cell verbatim and that "a generator MUST NOT be built to do so".

`.spec-ref` stays at **v0.7.0**. These are PHP source, not schemas, so vendored
schema byte-identity against spec v0.7.0 is unaffected; the ref moves when the spec
is published (ADR-001 as amended — lockstep binds at publication, not during
development). phpunit 740 tests / 4531 assertions green; phpstan level 9 clean.

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
