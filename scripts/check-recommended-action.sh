#!/usr/bin/env bash
# Verify that every code in the spec's §3 error registry carries a corrective
# action in this SDK, at the ref pinned in .spec-ref.
#
# Like check-error-registry.sh, and unlike schemas/, the registry is NOT vendored
# into this repo — it lives only as a Markdown table upstream — so this gate
# always needs a spec checkout.
#
# It checks COVERAGE and STRUCTURE, never content. spec 07-errors.md §1.4 states
# that a conformance test MUST NOT assert byte-identity between an emitted
# recommendedAction and its registry cell, because translation and shortening are
# both permitted. See the header of check-recommended-action.php for the eight
# properties this asserts instead and why each survives a translation.
#
# Usage:
#   scripts/check-recommended-action.sh                        # clones the pinned ref
#   SPEC_REPO=/local/path scripts/check-recommended-action.sh  # uses a local checkout
#
# Exit: 0 if every registry code has a distinct, bounded, structure-preserving
# action; 1 otherwise.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SPEC_REF="$(tr -d '[:space:]' < "${REPO_ROOT}/.spec-ref")"

# .spec-ref is PR-mutable and is interpolated into `git clone --branch`. Validate
# against the same SemVer-tag allowlist the CI schemas job uses before it reaches
# any argv — a value starting with `-` would be read by git as an option.
if [[ ! "${SPEC_REF}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9._-]+)?$ ]]; then
  echo "ERROR: .spec-ref value '${SPEC_REF}' does not match SemVer tag pattern (v<MAJOR>.<MINOR>.<PATCH>[-prerelease])" >&2
  exit 1
fi

if [[ -n "${SPEC_REPO:-}" ]]; then
  SPEC_ROOT="${SPEC_REPO}"
  if [[ ! -f "${SPEC_ROOT}/spec/07-errors.md" ]]; then
    echo "ERROR: SPEC_REPO=${SPEC_REPO} but ${SPEC_ROOT}/spec/07-errors.md not found" >&2
    exit 1
  fi
  echo "Comparing against local spec checkout at ${SPEC_ROOT} (.spec-ref=${SPEC_REF} — not enforced for local mode)"
  REF_LABEL="local checkout"
else
  TMPDIR="$(mktemp -d)"
  trap 'rm -rf "${TMPDIR}"' EXIT
  echo "Cloning ospp-org/spec at ${SPEC_REF}..."
  git clone --quiet --depth 1 --branch "${SPEC_REF}" https://github.com/ospp-org/spec.git "${TMPDIR}/spec"
  SPEC_ROOT="${TMPDIR}/spec"
  REF_LABEL="${SPEC_REF}"
fi

exec php "${REPO_ROOT}/scripts/check-recommended-action.php" "${SPEC_ROOT}" "${REF_LABEL}"
