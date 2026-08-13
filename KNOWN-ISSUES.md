# Known Issues — `ospp/protocol`

Divergences between this SDK and the specification, where the SPEC IS UNAMBIGUOUS and
this package does not follow it. Issues where the *specification itself* is ambiguous,
silent, or contradictory belong in the spec repository's `KNOWN-ISSUES.md` instead — that
is where `OsppErrorCode::httpStatus()` and `category()` are already recorded, and this
file deliberately does not duplicate them.

Read before cutting a release. An entry stays until the release that closes it, and then
says which one did.

Found at **v0.14.0**, 2026-08-12, by `csms-server` while sweeping its own code for logic
that duplicates this package.

---

## Summary

| State | Where | One line |
|-------|-------|----------|
| CLOSED in **0.15.0** | `Enums\ConfigurationKey::isMutable()` | `MessageSigningMode` was returned Dynamic; Chapter 08 sets it **Static** in bold. Fixed, and `ConfigurationKeyTest` rewritten from the spec table |
| CLOSED in **0.15.0** | `Enums\ConfigurationKey::defaultValue()` + `ValueObjects\ProtocolVersion::default()` | both answered `0.2.1`; Chapter 08 has said `0.3.0` since spec v0.10.0. Both now answer `0.3.0`, in lockstep with `@ospp/protocol` 0.15.0 |
| CLOSED in **0.15.0** | `scripts/check-config-registry.{sh,php}` | Chapter 08 now has the parity gate the other three registries had — **and it is wired into CI**, which is the half that was missing |
| CLOSED in **0.15.0** | `.github/workflows/tests.yml` | the `config-registry` job now exists. It could not until the default was fixed: the gate was red on it, and a job that lands red is not a gate |
| CLOSED in **0.17.0** | `Enums\ConfigurationKey::profile()` | answered `Offline` where spec §1.5's new **Profile ID** column says `OfflineBLE`. The gate could not have caught it — it read only §§2--6, which carry no profile column at all |
| **OPEN** | `scripts/check-schemas.sh` | mode corrected to `100755` in 0.17.0, on a script **nothing invokes**. A repair no run can confirm — see below |

Measured across all 29 cases against `spec/08-configuration.md` at the ref in
`.spec-ref` (v0.13.0): before 0.15.0, **two keys disagreed, and no other field on any
other key did.** That was the useful shape of the finding — the table was otherwise
exact, so these were two transcription slips, not a stale port. After 0.15.0 the gate
reports `all 29 keys agree on type, default, access and mutability`, and reports it on
every push rather than in a session that went looking.

**Nothing in this file is closed by a claim; each row above is closed by a control.**
The `isMutable()` and `defaultValue()` rows are closed by the same `config-registry` job,
not by the edits that fixed them — the edits are what made the job green, and the job is
what stops the next one. That distinction is the entire lesson of the 0.14.0 exec-bit
finding recorded below.

---

## CLOSED (unreleased, on `main`) — `isMutable()` called `MessageSigningMode` Dynamic, and Chapter 08 sets it **Static** with the reasoning spelled out

**Fixed on `main`, not yet in a release.** `isMutable()` gained the seventh Static arm, and
`ConfigurationKeyTest` was rewritten to transcribe the Static set FROM THE SPEC and to assert
the complement rather than sample it. `sdk-ts` already had this right, so the fix CLOSES a
cross-SDK disagreement rather than opening one — no lockstep needed for this half.

The original entry, kept because the shape is the point:

`spec/08-configuration.md:114`:

> | `MessageSigningMode` | string | `"All"` | RW | **Static** | `"All"`, `"None"` | … **Static**, not Dynamic: the
> mode is bound to the session key, which is issued at boot, so a mid-session change would leave one peer
> signing and the other not — and verification fails closed while signing fails closed too, so the station
> goes silent in both directions. Taking effect at the next reboot means the change and the new key land on
> the same event.

`ConfigurationKey::isMutable()` has **no arm** for this key, so it falls to the `default`
and returns `true`.

**This package contradicts itself.** `Enums\SigningMode`'s own docblock states "The mode
is `Static`". So the two enums that describe the same wire concept disagree inside one
release, and the one that is right is the one with no `match` arm to defend it.

**Why it matters more than a metadata field usually would.** `Mutability` is what a server
consults before dispatching `ChangeConfiguration`. A server that trusts `isMutable()` here
will apply the change mid-session, and the failure mode is the one the spec paragraph
describes: signing fails closed in one direction, verification fails closed in the other,
and the station goes silent. It does not present as a configuration error — it presents as
a station that has died.

**The test transcribed the same omission.** `tests/Unit/Enums/ConfigurationKeyTest.php:205-210`
asserts `assertFalse(...->isMutable())` for `STATION_NAME`, `TIME_ZONE`, `PROTOCOL_VERSION`,
`FIRMWARE_VERSION`, `CERTIFICATE_SERIAL_NUMBER` and `DIAGNOSTICS_UPLOAD_URL` — six keys.
Chapter 08 marks **seven** Static, and the missing one is `MESSAGE_SIGNING_MODE`. The test
was written from the enum rather than from the spec, so it cannot see the gap it shares.
Fixing `isMutable()` without adding that seventh assertion leaves the blind spot in place.

**Downstream today.** `csms-server` maintains its own 27-key `ConfigRegistry` and hardcodes
`Mutability::Static` for this key with the spec citation beside it, so no deployed server
currently reads the SDK's answer — this enum is imported nowhere in that repository. The
divergence is inert in practice and will stop being inert the moment anyone deletes that
local table in favour of this one, which is the direction that repo is being pushed.

---

## CLOSED in 0.15.0 — `ProtocolVersion` defaulted to `0.2.1`, in two places, where Chapter 08 says `0.3.0`

Both now answer `'0.3.0'`, and `@ospp/protocol` (TypeScript) 0.15.0 shipped the identical
change in the same release pair, so no cross-SDK disagreement was opened.

The original entry:

- `ConfigurationKey::PROTOCOL_VERSION->defaultValue()` returns `'0.2.1'`
- `ValueObjects\ProtocolVersion::default()` returns `'0.2.1'` when no resolver is installed

Chapter 08 gives `0.3.0`, and `0.3.0` is what is actually on the wire in production.

The two are one fact stored twice, and both are wrong in the same direction, so a
consumer that reads either gets a version the fleet stopped using. `csms-server` masks it
by installing a config-driven resolver and setting `OSPP_PROTOCOL_VERSION=0.3.0`
explicitly; a consumer that does not know to do that will build envelopes announcing a
version two minors behind the one its peer speaks.

`sdk-ts` carries the same default and the same staleness, so fixing one without the other
re-opens a cross-SDK disagreement.

**What made this survive four minor releases is worth keeping.** Both consumers of this
package had independently routed around it — `csms-server` through the env var above,
`ts-station-simulator` through a local `WIRE_PROTOCOL_VERSION` constant whose docblock
named "when the SDK's own default is corrected" as its deletion condition. Every caller
overriding a default means the default is never exercised, so no test, no deployment and
no wire capture could report it wrong. **Blast radius zero is not the same as harmless:**
it meant the only thing this defect could still block was the gate that would have caught
it, and it blocked exactly that for four releases. The simulator's constant is deleted in
its 0.15.0 bump; a workaround that names its own exit condition and then actually reaches
it is the outcome to aim for, and is rarer than writing one.

---

## CLOSED in 0.15.0 — Chapter 08 had no spec-parity gate; it has one now, it is wired, and two siblings turned out to be inert

`scripts/check-config-registry.{sh,php}` is the Chapter 08 twin of
`check-error-registry`: it parses the key tables, compares `type`, `default`, `access` and
`mutability` per key IN BOTH DIRECTIONS, and refuses to report a pass if it parses fewer than
25 rows — the empty-dataset-is-green trap the other gates already guard against. Proved
against a reformatted table: 0 rows parsed, exit 1, no false pass.

**Wired in 0.15.0** as the `config-registry` job, once `ProtocolVersion` was fixed. It could
not be wired before that: the gate was red on that one key, and a job that lands red is not a
gate — it is a broken window that teaches the team to stop reading the CI column. The order
matters and is the reusable part: fix the divergence, watch the gate go green on its own, then
wire it. Never wire a red gate with the failing case suppressed, because the suppression
outlives everyone's memory of why it was added.

**Found while building it — two of the three existing gates had never run.**
`scripts/check-error-registry.sh` and `scripts/check-crypto-vectors.sh` were both committed
in 0.14.0 with mode `100644`, and `.github/workflows/tests.yml` invokes them as
`run: scripts/…`, which needs the exec bit. Every CI run since 0.14.0 failed both jobs with
`Permission denied` — including the 0.14.0 release itself, which shipped red. Both gates pass
on CONTENT (118/118 codes agree; the crypto corpus is byte-identical), so the fix is the mode
and nothing else; all three scripts are now `100755`. `check-schemas.sh` is unaffected because
its job inlines its steps rather than calling the script.

That is the same class this file exists for, one level up: a gate that cannot execute is a
document claiming a protection that does not exist.

The original entry, whose first line was itself wrong — CI *declared* all three and could
execute only one:

`scripts/` holds three checks and CI runs all three:

| Script | Guards |
|--------|--------|
| `check-schemas.sh` | vendored `schemas/` byte-identical to the spec source |
| `check-error-registry.sh` | `Enums\OsppErrorCode` against the §3 registry table |
| `check-crypto-vectors.sh` | vendored crypto corpus byte-identical to the spec conformance vectors |

There is no equivalent for `Enums\ConfigurationKey` against `spec/08-configuration.md`,
and `tests/Unit/Enums/ConfigurationKeyTest.php` does not fill the gap: it asserts the enum
against values retyped from the enum, which is why it reproduced the omission above rather
than catching it.

**This is the durable half of both findings.** `check-error-registry.sh` exists because
the error registry drifted once and someone decided a human re-reading a Markdown table
was not a control. Chapter 08 is the same kind of table, with the same failure mode, and
the same fix: parse the registry rows and compare `type`, `default`, `access` and
`mutability` per key. A `check-config-registry.sh` written to the shape of its sibling
would have failed on both entries above, and the comparison is already implemented as a
throwaway in the session that found this — it is a script, not a design problem.

The crypto corpus makes the precedent explicit; `check-crypto-vectors.sh`'s own header
says this package "had no crypto-corpus gate at all before 0.14.0 — schemas were checked
against the spec and the crypto vectors were not", and that canonical form is why that
stopped being acceptable. Chapter 08 is now the last table in that position.

---

## OPEN — a corrected file mode on a script nothing invokes is a repair no run can confirm

Found at **v0.17.0**, 2026-08-13, while verifying that the new `config-registry` gate was
actually executable in CI rather than merely green.

`scripts/check-schemas.sh` was `100644` in the git index. The 0.15.0 notes stated that
*"All four gate scripts are `100755`"*, and that sentence was false when it was written.
0.17.0 changed the mode to `100755`, which makes the sentence true.

**That fix is unfalsifiable in this repository, and that is the finding.** Nothing invokes
`check-schemas.sh` — not CI, not a composer script. The `schemas` job in
`.github/workflows/tests.yml` performs the same byte-identity comparison with its steps
written inline, so the check itself runs on every push; the *script* is a local convenience
mirror of it. Every other gate here proved its own mode in the 0.17.0 run by producing
output. This one produced none, because it was never called.

**Why it matters, stated as the trap rather than the defect.** The next person to wire this
script into CI as `run: scripts/check-schemas.sh` will find a `100755` in the index and
reasonably conclude the mode was verified. It was not. It was *asserted* — the same class of
claim as the 0.15.0 sentence it replaces, one level less wrong. The 0.13.0 incident recorded
above is precisely what happens when a mode is believed rather than exercised: two gates
shipped `100644`, were invoked as bare `run: scripts/…`, and died `Permission denied` on
every run with the CI column green.

The same shape exists in `@ospp/sdk-ts`: its `scripts/check-schemas.sh` is `100755`, is
referenced by no npm script, and is invoked by no CI job, for the same reason — that repo's
`schemas` job also inlines the diff.

**Options, none taken here:**

1. **Wire it and delete the inline steps.** One definition of the check instead of two, and
   the mode becomes load-bearing and therefore proven on every run. This is the option that
   closes the entry; it is not taken in 0.17.0 because rewriting a green CI job during a
   release is how a release breaks a gate.
2. **Delete the script.** The inline steps are the real gate; a mirror that drifts from them
   is worse than no mirror. Costs the `SPEC_REPO=` local-checkout path, which the inline
   steps do not offer and which is genuinely used when working offline against a local spec.
3. **Leave it and stop claiming.** Keep the script as a documented local-only tool and never
   again write a sentence about all gate scripts being executable. Cheapest, and the only one
   that requires this entry to stay open.

The mode is `100755` either way; nothing in 0.17.0 depends on it being right.
