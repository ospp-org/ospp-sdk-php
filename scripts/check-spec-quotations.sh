#!/usr/bin/env bash
# Verify that every quotation this repository attributes to the spec is IN the
# spec, at the ref pinned in .spec-ref.
#
# A comment that quotes the specification reads as evidence: quotation marks, a
# section number, the spec's own words. It is also the only form of comment that
# can rot without looking rotten — a stale paraphrase still reads as a summary,
# a stale quotation reads as the spec still saying it. The registry gates compare
# tables and the schema gates compare vendored JSON; nothing read the prose.
#
# Like the registry gates, the spec text is NOT vendored into this repo, so this
# gate always needs a spec checkout. Unlike them it reads the WHOLE corpus, not
# one chapter, because a comment may cite any document the spec carries.
#
# Needs php-intl (NFKC). Without it the gate refuses a verdict rather than
# comparing un-normalised text, which would report real quotations as missing.
#
# Usage:
#   scripts/check-spec-quotations.sh                        # clones the pinned ref
#   SPEC_REPO=/local/path scripts/check-spec-quotations.sh  # uses a local checkout
#   scripts/check-spec-quotations.sh --json                 # full record dump
#
# Exit: 0 if every spec-attributed quotation is in the spec; 1 if any is not;
#       2 if the scan examined too little to return a verdict, or the built-in
#       positive control did not fire.

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

exec php "${REPO_ROOT}/scripts/check-spec-quotations.php" "${SPEC_ROOT}" "${REF_LABEL}" "$@"
