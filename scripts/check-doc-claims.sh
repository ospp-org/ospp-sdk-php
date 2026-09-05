#!/usr/bin/env bash
# Verify that every number README.md and the source headers assert about this
# package agrees with the package, and that every method the README's example
# demonstrates actually exists and is callable the way it is shown.
#
# Unlike the other gates here, this one needs NO spec checkout and no network:
# every value it compares against is derived from this repository. The chain to
# the spec is inherited — the enums it counts are themselves compared to the
# spec by check-error-registry, check-config-registry and check-action-registry.
#
# Usage:
#   scripts/check-doc-claims.sh
#
# Exit: 0 if every documented claim and example call site holds; 1 otherwise.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

exec php "${REPO_ROOT}/scripts/check-doc-claims.php"
