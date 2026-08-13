<?php

declare(strict_types=1);

/**
 * Gate: the SDK configuration registry vs the SPEC configuration registry.
 *
 * Compares every case of ConfigurationKey against the key tables in the spec's
 * `spec/08-configuration.md`, on the five properties the spec declares per key —
 * `Type`, `Default`, `Access`, `Mutability` from §§2--6, and the **Profile ID**
 * from §1.5 — plus the key set itself, in both directions.
 *
 * `Range` and `Description` are deliberately NOT checked. The enum models neither:
 * range lives downstream in whatever validates a ChangeConfiguration value, and
 * description is prose that §1.4's sibling rule in Chapter 07 would forbid emitting
 * verbatim anyway. Nothing here has an upstream to compare to, so nothing here is
 * claimed.
 *
 * **The profile comes from a different table than the other four**, which is why it
 * was missed. §§2--6 have no profile column at all — a key's profile is expressed
 * there by which SECTION it sits in — so a gate that parses those rows sees every
 * other property and is structurally blind to this one. §1.5 is where the profile is
 * stated per key, and until spec v0.16.0 it stated only a display label: `Offline /
 * BLE` and `Device Management`, neither of which survives being made an identifier.
 * Each implementation that needed a program value invented a spelling — this package
 * chose `Offline`, `sdk-ts` chose `OfflineBLE` and `DeviceMgmt` — and nothing
 * compared them to anything, so three spellings of two profiles coexisted across two
 * SDKs for as long as the field existed. v0.16.0 adds the normative **Profile ID**
 * column; this is the gate §1.5 names when it says one exists.
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
 * A §1.5 profile row: `| **Display Label** | `ProfileID` | key, key, ... | Required |`.
 *
 * Parsed ONLY inside §1.5. §§1.2, 1.3 and 1.4 are also leading-emphasis tables in
 * this chapter and a looser pattern reaches them; the section bound is what keeps
 * them out, not the pattern. The header row survives neither — `Profile ID` carries
 * a space and cannot match column 2 — but it is skipped by name as well, because a
 * header that started matching would enter the map as a profile.
 */
const PROFILE_SECTION = '/^###\s+1\.5\b/';
const PROFILE_SECTION_END = '/^#{2,3}\s/';
const PROFILE_ROW = '/^\|\s*\*{0,2}([A-Za-z][A-Za-z \/-]*?)\*{0,2}\s*\|\s*`?([A-Za-z]+|--)`?\s*\|([^|]*)\|/';

/**
 * The Profile IDs this SDK expects §1.5 to carry.
 *
 * Not the comparison — the comparison is per key, below. This is the vocabulary
 * check: if the spec renames or adds a profile, every key in it drifts at once and
 * the per-key output would be 4 or 9 lines of the same fact. Naming the set makes
 * that one line, and makes a spec-side rename impossible to absorb silently.
 */
const EXPECTED_PROFILE_IDS = ['Core', 'DeviceManagement', 'OfflineBLE', 'Security', 'Transaction'];

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

// --- §1.5: the profile of each key, by normative Profile ID --------------------
//
// Scoped to the section. A row outside §1.5 that happened to match would enter the
// map as a profile and could only make this gate weaker, never louder.

/** @var array<string, string> $specProfile key => Profile ID */
$specProfile = [];
/** @var array<string, string> $profileIds Profile ID => display label */
$profileIds = [];

$inside = false;
foreach (preg_split('/\r?\n/', $md) as $line) {
    if (preg_match(PROFILE_SECTION, $line) === 1) {
        $inside = true;

        continue;
    }

    if ($inside && preg_match(PROFILE_SECTION_END, $line) === 1) {
        break;
    }

    if (! $inside || preg_match(PROFILE_ROW, $line, $m) !== 1) {
        continue;
    }

    $label = trim($m[1]);
    $id = trim($m[2]);

    // The header, and the Vendor-Specific row — which states `--` because a vendor
    // key has no standard profile and this SDK models no vendor key.
    if ($label === 'Profile' || $id === '--') {
        continue;
    }

    $profileIds[$id] = $label;

    foreach (preg_split('/\s*,\s*/', trim($m[3])) as $key) {
        $key = trim($key, '` ');
        if ($key !== '') {
            $specProfile[$key] = $id;
        }
    }
}

// Threshold on the spec side, before any comparison. The §1.5 table is a DIFFERENT
// table from the §§2--6 rows already parsed above, so the floor those rows cleared
// says nothing about this one: §1.5 could reformat, yield nothing, and leave the
// gate reporting a clean pass on four properties while silently checking zero keys
// for the fifth. That is the same empty-dataset-is-green trap, one table over.
if (count($profileIds) < 5) {
    fwrite(STDERR, sprintf(
        "ERROR: parsed only %d profile row(s) from §1.5 of spec/08-configuration.md — the\n"
        ."profile table format has probably changed. Refusing to report a pass; fix the\n"
        ."parser in scripts/check-config-registry.php.\n",
        count($profileIds),
    ));
    exit(1);
}

if (count($specProfile) < 25) {
    fwrite(STDERR, sprintf(
        "ERROR: §1.5 named %d key(s) across its profiles, against %d rows in §§2--6 — the\n"
        ."Keys column has probably changed shape. Refusing to report a pass.\n",
        count($specProfile),
        count($spec),
    ));
    exit(1);
}

$unknownIds = array_diff(array_keys($profileIds), EXPECTED_PROFILE_IDS);
$missingIds = array_diff(EXPECTED_PROFILE_IDS, array_keys($profileIds));

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

/** @var array<string, array{type: string, default: ?string, access: string, mutability: string, profile: string}> $sdk */
$sdk = [];
foreach (ConfigurationKey::cases() as $case) {
    $sdk[$case->value] = [
        'type' => strtolower($case->type()),
        'default' => sdkDefault($case->defaultValue()),
        'access' => $case->access(),
        // The enum answers a bool; the table names the two states.
        'mutability' => $case->isMutable() ? 'Dynamic' : 'Static',
        'profile' => $case->profile(),
    ];
}

// Threshold on the SDK side. `cases()` cannot return an empty array for a non-empty
// enum, so this cannot fire today — it is here because the assertion the gate makes
// is "N keys were compared", and a future refactor that filters this list has to
// break the gate rather than shrink its scope quietly.
if (count($sdk) < 25) {
    fwrite(STDERR, sprintf(
        "ERROR: the SDK enum yielded only %d key(s). Refusing to report a pass on a\n"
        ."registry that small.\n",
        count($sdk),
    ));
    exit(1);
}

/** @var list<string> $problems */
$problems = [];

foreach ($unknownIds as $id) {
    $problems[] = "§1.5 carries Profile ID '{$id}', which this SDK does not know";
}
foreach ($missingIds as $id) {
    $problems[] = "this SDK expects Profile ID '{$id}', which §1.5 no longer carries";
}

// §1.5's own guarantee, checked rather than assumed: an ID that is not a bare
// alphanumeric word cannot be used as the program value §1.5 requires it to be, and
// this gate would then be comparing against something no implementation can adopt.
foreach (array_keys($profileIds) as $id) {
    if (ctype_alnum($id) !== true) {
        $problems[] = "Profile ID '{$id}' is not usable as a program identifier";
    }
}

$profilesCompared = 0;

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

    // A key in §§2--6 that §1.5 does not place in a profile is a spec defect, not a
    // key to skip: skipping it is how a key drops out of this comparison without
    // changing the count of problems.
    if (! isset($specProfile[$key])) {
        $problems[] = "{$key}: in §§2--6, but §1.5 places it in no profile";
    } elseif ($o['profile'] !== $specProfile[$key]) {
        $problems[] = "{$key}: profile spec={$specProfile[$key]} sdk={$o['profile']}";
        $profilesCompared++;
    } else {
        $profilesCompared++;
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

// Zero compared pairs is a failure, never a pass. Every threshold above can be
// cleared by a §1.5 that parses cleanly and a §§2--6 that parses cleanly while the
// two name DISJOINT key sets — each side full, the intersection empty, no key
// compared and no problem raised. This is the assertion the gate actually makes, so
// it is the one that has to be stated rather than inferred from the absence of
// output.
if ($profilesCompared === 0) {
    fwrite(STDERR,
        "ERROR: zero key/profile pairs were compared. §1.5 and §§2--6 parsed but name\n"
        ."disjoint key sets, so the profile check ran against nothing. Refusing to report\n"
        ."a pass; fix the parser in scripts/check-config-registry.php.\n",
    );
    exit(1);
}

printf(
    "spec %s: %d keys, %d profiles    SDK enum: %d keys    profiles compared: %d\n",
    $refLabel,
    count($spec),
    count($profileIds),
    count($sdk),
    $profilesCompared,
);

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
        ."type, default, access and mutability (§§2--6) and for the profile (§1.5, the\n"
        ."Profile ID column — NOT the display label beside it). If the SPEC is what is\n"
        ."wrong, fix it there first and re-pin .spec-ref — do not \"correct\" it here.\n"
        ."\nA fix to isMutable(), defaultValue() or profile() must also update\n"
        ."tests/Unit/Enums/ConfigurationKeyTest.php, which enumerates those answers by hand\n"
        ."and will otherwise keep asserting the old one.\n",
    );
    exit(1);
}

printf(
    "OK — all %d keys agree with spec %s on type, default, access and mutability,\n"
    ."and all %d agree with §1.5 on the normative Profile ID\n",
    count($spec),
    $refLabel,
    $profilesCompared,
);
exit(0);
