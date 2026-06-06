#!/usr/bin/env bash
# Verify that vendored schemas/ are byte-identical to the spec source
# at the ref pinned in .spec-ref. Local mirror of the CI gate.
#
# Usage:
#   scripts/check-schemas.sh                  # diffs against pinned ref
#   SPEC_REPO=/local/path scripts/check-schemas.sh   # diffs against a local checkout
#
# Exit: 0 if byte-identical (excluding non-schema README.md), 1 otherwise.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SPEC_REF="$(cat "${REPO_ROOT}/.spec-ref" | tr -d '[:space:]')"

if [[ -n "${SPEC_REPO:-}" ]]; then
  SOURCE_SCHEMAS="${SPEC_REPO}/schemas"
  if [[ ! -d "${SOURCE_SCHEMAS}" ]]; then
    echo "ERROR: SPEC_REPO=${SPEC_REPO} but ${SOURCE_SCHEMAS} not found" >&2
    exit 1
  fi
  echo "Comparing against local spec checkout at ${SPEC_REPO} (.spec-ref=${SPEC_REF} — not enforced for local mode)"
else
  TMPDIR="$(mktemp -d)"
  trap 'rm -rf "${TMPDIR}"' EXIT
  echo "Cloning ospp-org/spec at ${SPEC_REF}..."
  git clone --quiet --depth 1 --branch "${SPEC_REF}" https://github.com/ospp-org/spec.git "${TMPDIR}/spec"
  SOURCE_SCHEMAS="${TMPDIR}/spec/schemas"
fi

# Exclude non-schema files: README.md is documentation, not a schema.
if diff -rq --exclude=README.md "${SOURCE_SCHEMAS}" "${REPO_ROOT}/schemas" > /tmp/schema-diff.txt 2>&1; then
  echo "OK — vendored schemas are byte-identical to spec ${SPEC_REF}"
  exit 0
fi

echo "DRIFT detected between vendored schemas/ and spec ${SPEC_REF}:" >&2
cat /tmp/schema-diff.txt >&2
echo "" >&2
echo "Fix: copy spec/schemas/* → schemas/ (cp -r) and re-commit. Do not" >&2
echo "edit vendored schemas in-place; they are byte-mirror copies of the" >&2
echo "spec source pinned by .spec-ref." >&2
exit 1
