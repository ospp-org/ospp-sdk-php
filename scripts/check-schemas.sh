#!/usr/bin/env bash
# Verify that vendored schemas/ are byte-identical to the spec source
# at the ref pinned in .spec-ref.
#
# THE ONLY DEFINITION OF THIS CHECK. It used to be two: this script, which
# nothing called, and the `schemas` job in .github/workflows/tests.yml, which
# inlined its diff. A script with no caller cannot rot loudly; it rots the way
# the inline copy would have, by disagreeing with a check nobody compared it to.
# The job now calls this file, so there is one place to change and one place
# that can be wrong. KNOWN-ISSUES.md carried that duplication as an OPEN entry.
#
# Usage:
#   scripts/check-schemas.sh                  # diffs against pinned ref
#   SPEC_REPO=/local/path scripts/check-schemas.sh   # diffs against a local checkout
#
# SCOPE — this diffs the WHOLE directory, and that is deliberate.
#
# ospp-sdk-php vendors the COMPLETE spec schema set at the pinned ref, not a
# subset, so "every vendored file matches the spec" and "the directory matches
# the spec" are the same assertion here. The full-directory form is the stronger
# one: it also catches a schema DELETED from the vendored copy, or one ADDED to
# the spec and never vendored — which is precisely how
# provisioning-request.schema.json was missed at v0.8.0.
#
# Do NOT narrow this to a hand-maintained file list. A list is a second place to
# forget to update, and the failure it produces is silent: the gate goes green
# while an unlisted schema drifts. If a genuine reason to vendor a subset ever
# arises, derive the list from the vendored tree at run time (comm -12 of both
# file sets), never from a literal.
#
# Excludes:
#   - README.md           — non-schema documentation in spec/schemas/
#
# Exit: 0 if byte-identical (modulo exclusions), 1 otherwise.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SPEC_REF="$(cat "${REPO_ROOT}/.spec-ref" | tr -d '[:space:]')"

# A UNIQUE FILE PER RUN, AND ONE TRAP THAT REMOVES EVERY TEMPORARY THIS SCRIPT
# MAKES. The diff went to a fixed `/tmp/schema-diff.txt`, so two runs sharing a
# machine — the local mirror and a self-hosted job, or two jobs in one container
# — wrote over each other and either could report the other's drift. The clone
# directory needs removing too, and a second `trap ... EXIT` REPLACES the first
# rather than adding to it, so both live in one handler.
DIFF_OUT="$(mktemp)"
CLONE_DIR=""
cleanup() {
  rm -f "${DIFF_OUT}"
  [[ -n "${CLONE_DIR}" ]] && rm -rf "${CLONE_DIR}"
  return 0
}
trap cleanup EXIT

if [[ -n "${SPEC_REPO:-}" ]]; then
  SOURCE_SCHEMAS="${SPEC_REPO}/schemas"
  if [[ ! -d "${SOURCE_SCHEMAS}" ]]; then
    echo "ERROR: SPEC_REPO=${SPEC_REPO} but ${SOURCE_SCHEMAS} not found" >&2
    exit 1
  fi
  echo "Comparing against local spec checkout at ${SPEC_REPO} (.spec-ref=${SPEC_REF} — not enforced for local mode)"
else
  CLONE_DIR="$(mktemp -d)"
  echo "Cloning ospp-org/spec at ${SPEC_REF}..."
  git clone --quiet --depth 1 --branch "${SPEC_REF}" https://github.com/ospp-org/spec.git "${CLONE_DIR}/spec"
  SOURCE_SCHEMAS="${CLONE_DIR}/spec/schemas"
fi

# Exclude non-schema files: README.md is documentation, not a schema.
if diff -rq --exclude=README.md "${SOURCE_SCHEMAS}" "${REPO_ROOT}/schemas" > "${DIFF_OUT}" 2>&1; then
  echo "OK — vendored schemas are byte-identical to spec ${SPEC_REF}"
  exit 0
fi

echo "DRIFT detected between vendored schemas/ and spec ${SPEC_REF}:" >&2
cat "${DIFF_OUT}" >&2
echo "" >&2
echo "Fix: copy spec/schemas/* → schemas/ (cp -r) and re-commit. Do not" >&2
echo "edit vendored schemas in-place; they are byte-mirror copies of the" >&2
echo "spec source pinned by .spec-ref." >&2
exit 1
