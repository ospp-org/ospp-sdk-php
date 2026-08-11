<?php

declare(strict_types=1);

/**
 * Gate: the SDK error registry vs the SPEC error registry.
 *
 * Compares every case of OsppErrorCode against the registry table in the spec's
 * `spec/07-errors.md`. Checks the three properties the spec actually declares
 * per code — `errorText`, `severity`, `recoverable` — plus the code set itself,
 * in both directions.
 *
 * `httpStatus()` and `category()` are deliberately NOT checked. The spec
 * declines to give a code either one (07-errors.md §4.4 is explicit that HTTP
 * status is not a property of a code, and category exists only as section
 * headings), so both are SDK extensions with nothing upstream to compare to.
 * KNOWN-ISSUES tracks the fact that the two SDKs invented different answers.
 *
 * ---
 *
 * **Why this gate exists.** A parity check between the two SDKs already existed
 * and it reported `recoverable` as "identical — 0 diffs". It was: both SDKs said
 * `5004 ELECTRICAL_SYSTEM` was recoverable, and had said so since before the
 * spec changed its mind. The spec made it non-recoverable in v0.8.0 as a safety
 * fix — a welded relay or a lost phase persists while the measured voltage reads
 * nominal, so a bay could return to service still energised — and eight releases
 * passed without either SDK noticing, because the only thing checking them was
 * each other. Two implementations that agree are not evidence; they are one
 * opinion held twice.
 *
 * The spec's own tooling would not have caught it either: `verify-protocol.sh`
 * scrapes the same table with a regex that captures code, name and severity, and
 * stops before the `Recoverable` column.
 *
 * This file is the comparison only. `scripts/check-error-registry.sh` resolves
 * the spec checkout (clone at `.spec-ref`, or `SPEC_REPO`) and calls it.
 *
 * Usage: php scripts/check-error-registry.php <spec-root> [<ref-label>]
 */

require __DIR__.'/../vendor/autoload.php';

use Ospp\Protocol\Enums\OsppErrorCode;

$specRoot = $argv[1] ?? null;
$refLabel = $argv[2] ?? 'local checkout';

if ($specRoot === null || ! is_dir($specRoot)) {
    fwrite(STDERR, "Usage: php scripts/check-error-registry.php <spec-root> [<ref-label>]\n");
    exit(1);
}

$registryPath = $specRoot.'/spec/07-errors.md';
$md = @file_get_contents($registryPath);

if ($md === false) {
    fwrite(STDERR, "ERROR: cannot read {$registryPath}\n");
    exit(1);
}

/**
 * A §3 registry row. The `true|false` in column 4 is what distinguishes the
 * registry from Appendix A's quick-reference table, whose fourth column is a
 * category letter — so the narrower match is also the disambiguator.
 */
const ROW = '/^\|\s*(\d{4})\s*\|\s*`([A-Z_]+)`\s*\|\s*(\w+)\s*\|\s*(true|false)\s*\|/';

/** @var array<int, array{text: string, severity: string, recoverable: bool}> $spec */
$spec = [];
foreach (preg_split('/\r?\n/', $md) as $line) {
    if (preg_match(ROW, $line, $m) !== 1) {
        continue;
    }

    $code = (int) $m[1];
    if (isset($spec[$code])) {
        fwrite(STDERR, "ERROR: spec 07-errors.md lists code {$code} more than once\n");
        exit(1);
    }

    $spec[$code] = ['text' => $m[2], 'severity' => $m[3], 'recoverable' => $m[4] === 'true'];
}

// A regex that silently matches nothing would make this gate pass vacuously on
// any reformatting of the table. Refuse to be that gate.
if (count($spec) < 100) {
    fwrite(STDERR, sprintf(
        "ERROR: parsed only %d rows from spec/07-errors.md — the registry table format has probably\n"
        ."changed. Refusing to report a pass; fix the parser in scripts/check-error-registry.php.\n",
        count($spec),
    ));
    exit(1);
}

/** @var array<int, array{text: string, severity: string, recoverable: bool}> $sdk */
$sdk = [];
foreach (OsppErrorCode::cases() as $case) {
    $sdk[$case->value] = [
        'text' => $case->name,
        // The spec table spells severity PascalCase; the enum is UPPERCASE.
        'severity' => $case->severity()->toOspp(),
        'recoverable' => $case->isRecoverable(),
    ];
}

/** @var list<string> $problems */
$problems = [];

ksort($spec);
foreach ($spec as $code => $s) {
    if (! isset($sdk[$code])) {
        $problems[] = "{$code} {$s['text']}: in spec, MISSING from the SDK enum";

        continue;
    }

    $o = $sdk[$code];

    if ($o['text'] !== $s['text']) {
        $problems[] = "{$code}: errorText spec={$s['text']} sdk={$o['text']}";
    }
    if ($o['severity'] !== $s['severity']) {
        $problems[] = "{$code} {$s['text']}: severity spec={$s['severity']} sdk={$o['severity']}";
    }
    if ($o['recoverable'] !== $s['recoverable']) {
        $problems[] = sprintf(
            '%d %s: recoverable spec=%s sdk=%s',
            $code,
            $s['text'],
            $s['recoverable'] ? 'true' : 'false',
            $o['recoverable'] ? 'true' : 'false',
        );
    }
}

ksort($sdk);
foreach ($sdk as $code => $o) {
    if (! isset($spec[$code])) {
        $problems[] = "{$code} {$o['text']}: in the SDK enum, MISSING from the spec";
    }
}

printf("spec %s: %d codes    SDK enum: %d codes\n", $refLabel, count($spec), count($sdk));

if ($problems !== []) {
    fwrite(STDERR, sprintf(
        "\nDRIFT between the SDK error registry and spec %s — %d problem(s):\n\n",
        $refLabel,
        count($problems),
    ));
    foreach ($problems as $p) {
        fwrite(STDERR, "  {$p}\n");
    }
    fwrite(STDERR,
        "\nFix: change the SDK to match the spec. The spec registry is the source of\n"
        ."truth for errorText, severity and recoverable. If the SPEC is what is wrong,\n"
        ."fix it there first and re-pin .spec-ref — do not \"correct\" it here.\n",
    );
    exit(1);
}

printf("OK — all %d codes agree with spec %s on errorText, severity and recoverable\n", count($spec), $refLabel);
exit(0);
