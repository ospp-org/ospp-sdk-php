<?php

declare(strict_types=1);

/**
 * Gate: the SDK configuration registry vs the SPEC configuration registry.
 *
 * Compares every case of ConfigurationKey against the key tables in the spec's
 * `spec/08-configuration.md`, on the four properties the spec declares per key —
 * `Type`, `Default`, `Access`, `Mutability` — plus the key set itself, in both
 * directions.
 *
 * `Range` and `Description` are deliberately NOT checked. The enum models neither:
 * range lives downstream in whatever validates a ChangeConfiguration value, and
 * description is prose that §1.4's sibling rule in Chapter 07 would forbid emitting
 * verbatim anyway. Nothing here has an upstream to compare to, so nothing here is
 * claimed.
 *
 * ---
 *
 * **Why this gate exists.** Three registries in this package were already gated
 * against the spec — `schemas/` byte-for-byte, `OsppErrorCode` field-by-field, and
 * (since 0.14.0) the crypto conformance corpus — and Chapter 08 was not. Two
 * divergences survived four minor releases in that gap, and neither was subtle:
 *
 *   - `MessageSigningMode` was returned Dynamic where Chapter 08 sets it **Static**
 *     in bold and spends a paragraph on the outage that follows from getting it
 *     wrong. This package contradicted ITSELF — `Enums\SigningMode`'s docblock says
 *     "The mode is `Static`" — and `sdk-ts` had it right all along.
 *   - `ProtocolVersion` defaulted to `0.2.1` where Chapter 08 says `0.3.0`, which is
 *     what is actually on the wire.
 *
 * Neither was found by a test. `tests/Unit/Enums/ConfigurationKeyTest.php` asserts
 * `isMutable()` for six keys and Chapter 08 marks seven Static — the missing one is
 * `MESSAGE_SIGNING_MODE`. It was written from the enum rather than from the spec, so
 * it reproduced the omission instead of catching it. That is the general reason a
 * unit test cannot replace this file: a test written from the implementation shares
 * the implementation's blind spots, and only an upstream source does not.
 *
 * This file is the comparison only. `scripts/check-config-registry.sh` resolves the
 * spec checkout (clone at `.spec-ref`, or `SPEC_REPO`) and calls it.
 *
 * Usage: php scripts/check-config-registry.php <spec-root> [<ref-label>]
 */

require __DIR__.'/../vendor/autoload.php';

use Ospp\Protocol\Enums\ConfigurationKey;

$specRoot = $argv[1] ?? null;
$refLabel = $argv[2] ?? 'local checkout';

if ($specRoot === null || ! is_dir($specRoot)) {
    fwrite(STDERR, "Usage: php scripts/check-config-registry.php <spec-root> [<ref-label>]\n");
    exit(1);
}

$registryPath = $specRoot.'/spec/08-configuration.md';
$md = @file_get_contents($registryPath);

if ($md === false) {
    fwrite(STDERR, "ERROR: cannot read {$registryPath}\n");
    exit(1);
}

/**
 * A Chapter 08 key row.
 *
 * The discriminator is columns 4 and 5 together: `R|RW|W` followed by
 * `Static|Dynamic`. Chapter 08 carries other tables — the value-type table, the
 * access-mode table — and neither has a backticked key in column 1 AND that pair in
 * columns 4-5, so the narrower match is also what keeps them out. Mutability may be
 * bolded (`**Static**`), which is emphasis in the source and not part of the value.
 */
const ROW = '/^\|\s*`([A-Za-z]+)`\s*\|\s*\*{0,2}(\w+)\*{0,2}\s*\|([^|]*)\|\s*(R|RW|W)\s*\|\s*\*{0,2}(Static|Dynamic)\*{0,2}\s*\|/';

/**
 * The Default cell as a comparable scalar.
 *
 * `--` means the spec states no default (read-only keys the station fills in).
 * Everything else is backticked and may carry JSON quotes: `` `"All"` `` is the
 * string All, `` `""` `` is the empty string, `` `60` `` is 60. Both are stripped so
 * the comparison is against the VALUE, not against how the table renders it.
 */
function specDefault(string $cell): ?string
{
    $cell = trim($cell);

    if ($cell === '--' || $cell === '—' || $cell === '') {
        return null;
    }

    return trim(trim($cell, '` '), '"');
}

/** @var array<string, array{type: string, default: ?string, access: string, mutability: string}> $spec */
$spec = [];
foreach (preg_split('/\r?\n/', $md) as $line) {
    if (preg_match(ROW, $line, $m) !== 1) {
        continue;
    }

    $key = $m[1];
    if (isset($spec[$key])) {
        fwrite(STDERR, "ERROR: spec 08-configuration.md lists key {$key} more than once\n");
        exit(1);
    }

    $spec[$key] = [
        'type' => strtolower($m[2]),
        'default' => specDefault($m[3]),
        'access' => $m[4],
        'mutability' => $m[5],
    ];
}

// A regex that silently matches nothing would make this gate pass vacuously on any
// reformatting of the table — the empty-dataset-is-green trap. Refuse to be that
// gate. The floor is a PARSER sanity check and not an assertion about how many keys
// the spec has: a key added or removed upstream is reported below, in both
// directions, and must not read as a broken parser.
if (count($spec) < 25) {
    fwrite(STDERR, sprintf(
        "ERROR: parsed only %d rows from spec/08-configuration.md — the registry table format has\n"
        ."probably changed. Refusing to report a pass; fix the parser in scripts/check-config-registry.php.\n",
        count($spec),
    ));
    exit(1);
}

/**
 * The enum's answer, normalised to the spec's vocabulary.
 *
 * `defaultValue()` is typed (`string|int|bool|null`) where the table is text, so
 * scalars are rendered the way Chapter 08 writes them — `true`/`false` lowercase,
 * integers bare — and null stays null, meaning "no default stated".
 */
function sdkDefault(string|int|bool|null $value): ?string
{
    if ($value === null) {
        return null;
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
}

/** @var array<string, array{type: string, default: ?string, access: string, mutability: string}> $sdk */
$sdk = [];
foreach (ConfigurationKey::cases() as $case) {
    $sdk[$case->value] = [
        'type' => strtolower($case->type()),
        'default' => sdkDefault($case->defaultValue()),
        'access' => $case->access(),
        // The enum answers a bool; the table names the two states.
        'mutability' => $case->isMutable() ? 'Dynamic' : 'Static',
    ];
}

/** @var list<string> $problems */
$problems = [];

ksort($spec);
foreach ($spec as $key => $s) {
    if (! isset($sdk[$key])) {
        $problems[] = "{$key}: in spec, MISSING from the SDK enum";

        continue;
    }

    $o = $sdk[$key];

    foreach (['type', 'access', 'mutability'] as $field) {
        if ($o[$field] !== $s[$field]) {
            $problems[] = "{$key}: {$field} spec={$s[$field]} sdk={$o[$field]}";
        }
    }

    if ($o['default'] !== $s['default']) {
        $problems[] = sprintf(
            '%s: default spec=%s sdk=%s',
            $key,
            $s['default'] === null ? '(none)' : "'{$s['default']}'",
            $o['default'] === null ? '(none)' : "'{$o['default']}'",
        );
    }
}

ksort($sdk);
foreach ($sdk as $key => $o) {
    if (! isset($spec[$key])) {
        $problems[] = "{$key}: in the SDK enum, MISSING from the spec";
    }
}

printf("spec %s: %d keys    SDK enum: %d keys\n", $refLabel, count($spec), count($sdk));

if ($problems !== []) {
    fwrite(STDERR, sprintf(
        "\nDRIFT between the SDK configuration registry and spec %s — %d problem(s):\n\n",
        $refLabel,
        count($problems),
    ));
    foreach ($problems as $p) {
        fwrite(STDERR, "  {$p}\n");
    }
    fwrite(STDERR,
        "\nFix: change the SDK to match the spec. Chapter 08 is the source of truth for\n"
        ."type, default, access and mutability. If the SPEC is what is wrong, fix it there\n"
        ."first and re-pin .spec-ref — do not \"correct\" it here.\n"
        ."\nA fix to isMutable() or defaultValue() must also update\n"
        ."tests/Unit/Enums/ConfigurationKeyTest.php, which enumerates those answers by hand\n"
        ."and will otherwise keep asserting the old one.\n",
    );
    exit(1);
}

printf("OK — all %d keys agree with spec %s on type, default, access and mutability\n", count($spec), $refLabel);
exit(0);
