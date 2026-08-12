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

check "${CRYPTO_SRC}/ble-handshake-keyschedule.json" "${FIXTURES}/ble-handshake-keyschedule.json" "ble-handshake-keyschedule.json"
check "${CRYPTO_SRC}/rfc-primitive-anchors.json"      "${FIXTURES}/rfc-primitive-anchors.json"      "rfc-primitive-anchors.json"
check "${CRYPTO_SRC}/canonical-form.json"             "${FIXTURES}/canonical-form.json"             "canonical-form.json"
check "${KEYS_SRC}/server-test-pub.pem"               "${FIXTURES}/server-test-pub.pem"             "server-test-pub.pem"

if [[ "${status}" -eq 0 ]]; then
  echo "OK — vendored crypto corpus byte-identical to spec ${SPEC_REF}"
else
  echo "" >&2
  echo "Fix: re-vendor from spec conformance/test-vectors/crypto/ + test-keys/server-test-pub.pem" >&2
  echo "into tests/Contract/Crypto/fixtures/ (cp) and re-commit. Do not edit vendored vectors in place." >&2
fi
exit "${status}"
