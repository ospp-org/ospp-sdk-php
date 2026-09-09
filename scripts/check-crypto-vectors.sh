#!/usr/bin/env bash
# Verify that the vendored crypto conformance corpus is byte-identical to the
# spec source at the ref pinned in .spec-ref. The crypto-corpus twin of
# scripts/check-schemas.sh, and the mirror of sdk-ts's script of the same name.
#
# Vendored files (tests/Contract/Crypto/fixtures/) <- spec source:
#   ble-handshake-keyschedule.json  <-  conformance/test-vectors/crypto/
#   rfc-primitive-anchors.json      <-  conformance/test-vectors/crypto/
#   canonical-form.json             <-  conformance/test-vectors/crypto/
#   server-test-pub.pem             <-  conformance/test-keys/
#
# This SDK had no crypto-corpus gate at all before 0.14.0 — schemas were checked
# against the spec and the crypto vectors were not. canonical-form.json is why
# that stopped being acceptable: the canonical-form vectors lived as two
# hand-maintained copies, one per SDK, whose agreement was asserted in a comment
# and by nothing else, so both could be edited into agreeing with each other and
# disagreeing with the spec. That is the shape of every defect this release
# fixes. The vectors are now vendored from one upstream and this gate is what
# makes "vendored" mean something.
#
# NOT covered here: canonical-mac-strip.json. It is deliberately ours and has no
# spec source — §4.8 is defined over any JSON value and the spec's corpus
# therefore carries no message with a `mac`. It stays a byte-identical pair with
# sdk-ts, checked by eye at review time.
#
# Usage:
#   scripts/check-crypto-vectors.sh                       # clones spec at .spec-ref
#   SPEC_REPO=/local/path scripts/check-crypto-vectors.sh # diffs a local checkout
#
# Exit: 0 if byte-identical, 1 otherwise.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SPEC_REF="$(tr -d '[:space:]' < "${REPO_ROOT}/.spec-ref")"
FIXTURES="${REPO_ROOT}/tests/Contract/Crypto/fixtures"

# .spec-ref is PR-mutable and is interpolated into `git clone --branch`. Validate
# against the same SemVer-tag allowlist the CI schemas job uses before it reaches
# any argv — a value starting with `-` would be read by git as an option.
if [[ ! "${SPEC_REF}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9._-]+)?$ ]]; then
  echo "ERROR: .spec-ref value '${SPEC_REF}' does not match SemVer tag pattern (v<MAJOR>.<MINOR>.<PATCH>[-prerelease])" >&2
  exit 1
fi

if [[ -n "${SPEC_REPO:-}" ]]; then
  SPEC_SRC="${SPEC_REPO}"
  echo "Comparing against local spec checkout at ${SPEC_REPO} (.spec-ref=${SPEC_REF} — not enforced for local mode)"
else
  TMPDIR="$(mktemp -d)"
  trap 'rm -rf "${TMPDIR}"' EXIT
  echo "Cloning ospp-org/spec at ${SPEC_REF}..."
  git clone --quiet --depth 1 --branch "${SPEC_REF}" https://github.com/ospp-org/spec.git "${TMPDIR}/spec"
  SPEC_SRC="${TMPDIR}/spec"
fi

CRYPTO_SRC="${SPEC_SRC}/conformance/test-vectors/crypto"
KEYS_SRC="${SPEC_SRC}/conformance/test-keys"

if [[ ! -d "${CRYPTO_SRC}" ]]; then
  echo "ERROR: ${CRYPTO_SRC} not found — the crypto corpus exists only in spec >= v0.6.0." >&2
  exit 1
fi

status=0
check() {
  local src="$1" dst="$2" name="$3"
  if [[ ! -f "${src}" ]]; then echo "DRIFT: missing in spec source: ${name}" >&2; status=1; return; fi
  if [[ ! -f "${dst}" ]]; then echo "DRIFT: missing vendored copy: ${name}" >&2; status=1; return; fi
  if cmp -s "${src}" "${dst}"; then
    echo "OK identical: ${name}"
  else
    echo "DRIFT: ${name} differs from spec ${SPEC_REF}" >&2
    status=1
  fi
}

# DRIVEN BY WHAT IS VENDORED, NOT BY A HARDCODED LIST.
#
# This used to be four `check` lines naming four files. An INCLUSION list fails
# silently in exactly one direction: vendor a fifth file and nothing compares it,
# so it can drift from the spec forever and this gate stays green while saying
# "OK — vendored crypto corpus byte-identical". That is how the vendored README.md
# in tests/Fixtures/test-vectors/ came to sit at "OSPP Version: 0.15.0" against a
# spec at v0.27.0 with its own gate reporting identical every run: the loop there
# iterates `valid invalid` and README.md is in neither.
#
# Iterating the VENDORED directory inverts that. A newly vendored file is compared
# the moment it lands; a file that disappears from the spec is caught by check()'s
# "missing in spec source" arm. The floor below stops the degenerate case where the
# fixtures directory is empty and a loop over nothing reports success — the failure
# a `for` loop can always produce and the one this repo has already been bitten by.
# SDK-LOCAL fixtures: authored here, with no upstream counterpart. The value is the
# REASON, not the name — a later reader has to be able to disagree with it rather
# than take it on trust. Anything NOT listed here is expected to exist in the spec,
# which is what makes a newly vendored file compared by default instead of ignored
# by default.
declare -A SDK_LOCAL=(
  [canonical-mac-strip.json]='authored here — pins that MacSigner strips `mac` before canonicalising, a PHP-side property with no spec vector'
  [hmac-golden-vectors.json]='authored here — HMAC goldens for this SDK, generated by tests/Contract/Crypto/fixtures/generators'
  [signing-classification.json]='authored here — which actions this SDK signs, a library policy rather than a protocol fact'
)

vendored=0
while IFS= read -r dst; do
  name="$(basename "${dst}")"
  if [[ -v "SDK_LOCAL[${name}]" ]]; then
    echo "SKIP local:   ${name} — ${SDK_LOCAL[${name}]}"
    continue
  fi
  case "${name}" in
    *.json) check "${CRYPTO_SRC}/${name}" "${dst}" "${name}" ;;
    *.pem)  check "${KEYS_SRC}/${name}"   "${dst}" "${name}" ;;
    *)      echo "DRIFT: ${name} is vendored here but is neither a vector nor a key" >&2; status=1 ;;
  esac
  vendored=$((vendored + 1))
done < <(find "${FIXTURES}" -maxdepth 1 -type f \( -name '*.json' -o -name '*.pem' \) | sort)

if [[ "${vendored}" -lt 4 ]]; then
  echo "DRIFT: only ${vendored} vendored crypto artefact(s) found — a loop over an empty or" >&2
  echo "       truncated directory reports success for zero work. Expected at least 4." >&2
  status=1
fi

# The tamper corpus must be here specifically, and named, because it is the only
# vector in this directory whose absence would be invisible: the others are consumed
# by tests that fail loudly without them, while a missing tamper corpus would simply
# mean the negative-direction tests skip.
if [[ ! -f "${FIXTURES}/tamper-rejection.json" ]]; then
  echo "DRIFT: tamper-rejection.json is not vendored — the negative-direction proof" >&2
  echo "       (TC-SEC-001/004, TC-OFF-002/005) has nothing to run against" >&2
  status=1
fi

# COMPLETENESS — the direction the loop above cannot see. ADDED 2026-09-09.
#
# Iterating the VENDORED directory fixed one failure and left its mirror image
# open. The loop proves "everything we vendored matches the spec"; it cannot
# prove "we vendored everything the spec has", because a spec vector that was
# never copied is never walked and so is never missed.
#
# MEASURED, not argued. On 2026-09-09 the spec carried FIVE crypto vectors and
# this directory held FOUR of them: `mqtt-mac.json` had never been vendored, no
# SDK_LOCAL entry named it, no comment excluded it, and this script printed
# "OK — vendored crypto corpus byte-identical" on every run since 0.14.0. It is
# the vector that pins §5.4 — the one an integrator needs to verify envelope
# signing, and the only place the "key is the DECODED 32 bytes, not the 44-char
# Base64 text" mistake is recorded with the wrong value beside the right one.
# Two files already in this directory quote values out of it BY HAND
# (`tamper-rejection.json`'s `mqtt-mac-bitflip` names it as its `base`;
# `canonical-mac-strip.json` says in prose that its mac came from it), so the
# corpus already depended on a file the corpus did not carry.
#
# Reproduce the blindness: delete any vendored vector and re-run. Before this
# arm the script reported success.
#
# SPEC_ONLY is the counterpart of SDK_LOCAL and carries the same obligation —
# a REASON a later reader can disagree with, not a bare name. Empty today: every
# crypto vector the spec publishes belongs in an SDK that claims to implement it.
declare -A SPEC_ONLY=()

spec_side=0
while IFS= read -r src; do
  name="$(basename "${src}")"
  spec_side=$((spec_side + 1))
  if [[ -v "SPEC_ONLY[${name}]" ]]; then
    echo "SKIP spec-only: ${name} — ${SPEC_ONLY[${name}]}"
    continue
  fi
  if [[ ! -f "${FIXTURES}/${name}" ]]; then
    echo "DRIFT: ${name} exists in spec ${SPEC_REF} but was NEVER vendored here." >&2
    echo "       Not drift in a copy — an absent copy. Add it to ${FIXTURES}/, or" >&2
    echo "       add it to SPEC_ONLY with the reason it does not belong in this SDK." >&2
    status=1
  fi
done < <(find "${CRYPTO_SRC}" -maxdepth 1 -type f -name '*.json' | sort)

# Zero matches = failure, the same floor the vendored loop carries: a walk over a
# moved or renamed spec directory would otherwise report completeness for no work.
if [[ "${spec_side}" -lt 4 ]]; then
  echo "DRIFT: the spec crypto directory yielded only ${spec_side} vector(s) — the" >&2
  echo "       completeness walk found nothing to check. Expected at least 4." >&2
  status=1
fi

if [[ "${status}" -eq 0 ]]; then
  echo "OK — vendored crypto corpus byte-identical to spec ${SPEC_REF}"
else
  echo "" >&2
  echo "Fix: re-vendor from spec conformance/test-vectors/crypto/ + test-keys/server-test-pub.pem" >&2
  echo "into tests/Contract/Crypto/fixtures/ (cp) and re-commit. Do not edit vendored vectors in place." >&2
fi
exit "${status}"
