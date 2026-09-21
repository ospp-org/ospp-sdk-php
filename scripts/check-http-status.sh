#!/usr/bin/env bash
# Verify that this SDK uses the HTTP status the spec gives a code, wherever
# spec/07-errors.md §2.4's status table names that code. At the pinned ref.
#
# `httpStatus` is an SDK extension — the spec Error Object has no status member
# and §3 has no status column — so §2.4's table is the only upstream there is,
# and it is not vendored. This gate therefore always needs a spec checkout.
#
# Usage:
#   scripts/check-http-status.sh                        # clones the pinned ref
#   SPEC_REPO=/local/path scripts/check-http-status.sh  # uses a local checkout
#
# Exit: 0 if every code §2.4 names carries the status §2.4 gives it; 1 otherwise;
#       2 if the table could not be parsed or the positive control did not fire.

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

exec php "${REPO_ROOT}/scripts/check-http-status.php" "${SPEC_ROOT}" "${REF_LABEL}"
