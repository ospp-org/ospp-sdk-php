#!/usr/bin/env bash
# Census + gate: no assertion under tests/ may be inert.
#
# An assertion whose every argument folds to a compile-time constant reads
# nothing, describes nothing, and is green over an empty registry, a renamed
# enum and a deleted one alike. Being green is not evidence of anything: an
# assertion that cannot fail is not a weak test, it is a claim made with no
# check attached.
#
# Like check-doc-claims, this gate needs NO spec checkout and no network — the
# whole judgement is about this repository's own test corpus.
#
# Usage:
#   scripts/check-inert-assertions.sh
#
# Exit: 0 if every inert assertion is declared in .inert-assertions.json and
#       every declaration still matches one; 1 otherwise; 2 if the walk
#       inspected too little to return a verdict at all.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

exec php "${REPO_ROOT}/scripts/check-inert-assertions.php" "$@"
