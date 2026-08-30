#!/usr/bin/env bash
# Verify that the vendored CONFORMANCE VECTOR CORPUS is byte-identical to the
# spec source at the ref pinned in .spec-ref. The third byte-identity gate,
# after scripts/check-schemas.sh and scripts/check-crypto-vectors.sh.
#
# Vendored (tests/Fixtures/test-vectors/) <- spec conformance/test-vectors/:
#   the WHOLE directory, less crypto/ — see SCOPE below. That is valid/,
#   invalid/ and README.md, and anything the spec adds beside them later.
#
# WHY THIS EXISTS
#
# schemas/ has been byte-gated since 0.8.0 and the crypto corpus since 0.14.0.
# The conformance corpus — the 334 files that decide what this SDK accepts and
# refuses — had NOTHING. It was re-vendored by hand on every spec sync and its
# correctness rested on the maintainer having copied the right tree. A vector
# edited in place, a vector the spec added and nobody vendored, a vector the
# spec deleted and nobody removed: all three land SILENTLY. The suite stays
# green, because the suite validates whatever vectors are present against
# whatever schemas are present, and both halves being locally consistent is not
# the same as either being upstream-correct.
#
# The counts in ConformanceVectorTest::theVendoredCorpusIsComplete() were the
# only thing standing in for this, and they are a second copy of a fact about
# the corpus rather than a check on it — a literal a human bumps by hand, whose
# failure lands on whoever did the re-vendor correctly. This gate is what that
# test's own comment said should replace them.
#
# SCOPE — the WHOLE directory, deliberately, and that is wider than it was.
#
# This SDK vendors the COMPLETE valid/ and invalid/ sets (163 + 171 at spec
# v0.25.0 and unchanged at v0.27.0), not a subset, so "every vendored vector
# matches" and "the directory matches" are the same assertion. The directory
# form is the stronger one: it also catches a vector DELETED from the vendored
# copy and one ADDED to the spec and never vendored, which a per-file list
# cannot. Do NOT narrow this to a hand-maintained file list — a list is a second
# place to forget, and it fails silently by going green.
#
# WIDENED AT spec v0.27.0, because this script was itself that list. It looped
# `for bucket in valid invalid` — two names, hand-written, and everything else in
# the directory was outside the comparison. What was outside it was
# README.md, which the spec re-stamps on every release. The vendored copy read
# `OSPP Version: 0.15.0` against an upstream `0.27.0` — TWELVE minors of drift —
# and pointed at `schemas/mqtt/mqtt-envelope.schema.json`, a path that exists in
# neither this repo nor the spec (it is `schemas/common/`). This gate reported
# `OK — vendored conformance corpus byte-identical to spec` on every one of
# those runs, and the header three lines up already said not to do this.
#
# The consequence is not cosmetic. That README is the ONLY vendored artefact in
# this corpus that moves when the spec version moves — the vectors themselves did
# not change across v0.25.0 -> v0.26.0 -> v0.27.0. So while it sat outside the
# diff, NO gate in this repository could tell one .spec-ref value from another:
# a marker bumped without a re-vendor, or a re-vendor without a marker bump, both
# stayed green. Including it is what makes this gate discriminate the pin.
#
# NOT covered here: conformance/test-vectors/crypto/, excluded by name below.
# That subset is genuinely a subset — this repo vendors 2 of the spec's 4 — and
# scripts/check-crypto-vectors.sh already pins it against the spec by
# name, with its rationale in that script's header. It is the ONE exclusion, it
# is stated as an argument rather than by omission, and a whole-directory diff
# would otherwise report the two unvendored files as drift on every run. What IS
# covered below is the
# DUPLICATE of two of those files under tests/Fixtures/test-vectors/crypto/,
# which no test reads and no gate pinned — it is checked against the gated copy
# rather than against the spec, so the pin stays in one place and transitivity
# does the rest.
#
# Usage:
#   scripts/check-vector-corpus.sh                       # clones spec at .spec-ref
#   SPEC_REPO=/local/path scripts/check-vector-corpus.sh # diffs a local checkout
#
# Exit: 0 if byte-identical, 1 otherwise.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SPEC_REF="$(tr -d '[:space:]' < "${REPO_ROOT}/.spec-ref")"
VECTORS="${REPO_ROOT}/tests/Fixtures/test-vectors"

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

CORPUS_SRC="${SPEC_SRC}/conformance/test-vectors"

if [[ ! -d "${CORPUS_SRC}" ]]; then
  echo "ERROR: ${CORPUS_SRC} not found — cannot compare. This is not a clean run." >&2
  exit 1
fi

status=0

# Both buckets must EXIST on both sides before the diff decides anything. A
# whole-directory diff of two trees that are both missing valid/ agrees, and
# would report success for zero work — the failure mode this repo has already
# had to repair once, where a run collecting zero cases exited green.
for bucket in valid invalid; do
  if [[ ! -d "${CORPUS_SRC}/${bucket}" ]]; then
    echo "DRIFT: spec source has no ${bucket}/ directory" >&2
    status=1
  fi
  if [[ ! -d "${VECTORS}/${bucket}" ]]; then
    echo "DRIFT: vendored copy has no ${bucket}/ directory" >&2
    status=1
  fi
done

# The comparison. One diff over the whole directory, not a loop over names.
# --exclude matches on basename, so `crypto` skips that subdirectory on both
# sides; no file elsewhere in either tree carries that name.
if [[ "${status}" -eq 0 ]]; then
  if diff -rq --exclude=crypto "${CORPUS_SRC}" "${VECTORS}"; then
    for bucket in valid invalid; do
      n="$(find "${VECTORS}/${bucket}" -name '*.json' | wc -l)"
      if [[ "${n}" -eq 0 ]]; then
        echo "DRIFT: ${bucket}/ holds no vectors — a matching pair of empty trees is not a pass" >&2
        status=1
      else
        echo "OK identical: ${bucket}/ (${n} vectors)"
      fi
    done
    # Named separately from the buckets because it is the only file here that
    # moves with the spec VERSION rather than with the vectors, and so the only
    # one whose drift proves the pin is stale rather than the corpus.
    if [[ -f "${VECTORS}/README.md" ]]; then
      echo "OK identical: README.md (carries the spec's own version banner)"
    else
      echo "DRIFT: vendored copy has no README.md — the spec ships one and it is the" >&2
      echo "       only artefact here that discriminates one .spec-ref from another" >&2
      status=1
    fi
  else
    echo "DRIFT: vendored corpus differs from spec ${SPEC_REF}" >&2
    status=1
  fi
fi

# The duplicate crypto pair. tests/Fixtures/test-vectors/crypto/ holds a second
# copy of two files the crypto gate already pins under tests/Contract/Crypto/
# fixtures/. Nothing reads this copy and nothing checked it; it is compared to
# the gated copy so that the spec pin lives in exactly one script.
GATED_CRYPTO="${REPO_ROOT}/tests/Contract/Crypto/fixtures"
for f in ble-handshake-keyschedule.json rfc-primitive-anchors.json; do
  dup="${VECTORS}/crypto/${f}"
  src="${GATED_CRYPTO}/${f}"
  if [[ ! -f "${dup}" ]]; then
    echo "DRIFT: missing duplicate crypto vector: crypto/${f}" >&2
    status=1
  elif [[ ! -f "${src}" ]]; then
    echo "DRIFT: missing gated crypto vector it mirrors: ${f}" >&2
    status=1
  elif cmp -s "${dup}" "${src}"; then
    echo "OK identical: crypto/${f} (mirrors the gated copy)"
  else
    echo "DRIFT: crypto/${f} differs from the spec-pinned copy in tests/Contract/Crypto/fixtures/" >&2
    status=1
  fi
done

if [[ "${status}" -eq 0 ]]; then
  echo "OK — vendored conformance corpus byte-identical to spec ${SPEC_REF}"
else
  echo "" >&2
  echo "Fix: re-vendor the whole of spec conformance/test-vectors/ into" >&2
  echo "tests/Fixtures/test-vectors/ (cp -r), crypto/ excepted, README.md INCLUDED." >&2
  echo "Do not edit vendored files in place; bump .spec-ref if vendoring against a" >&2
  echo "newer spec release. A README-only drift means exactly one of the two halves" >&2
  echo "of a sync was done: the marker moved without the copy, or the reverse." >&2
fi
exit "${status}"
