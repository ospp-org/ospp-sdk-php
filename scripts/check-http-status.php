<?php

declare(strict_types=1);

/**
 * Gate: where the spec names an HTTP status for a code, this SDK MUST use it.
 *
 * WHY THIS EXISTS, MEASURED RATHER THAN ARGUED
 * --------------------------------------------
 * `httpStatus()` is an SDK extension: the spec's Error Object (§1.3) has no
 * status member, and §3's registry table has no status column — 07-errors.md
 * says so in as many words, that "a code and a status answer different
 * questions". So the two SDKs were free to answer independently, and they did.
 *
 * Dumped from both at spec v0.42.0, 2026-09-21:
 *
 *   codes in both SDKs                  120
 *   named by the §2.4 status table       31
 *   not named                            89
 *   the two SDKs AGREE on                79
 *   the two SDKs DIVERGE on              41
 *
 *   divergence among the 31 NAMED         0
 *   divergence among the 89 UNNAMED      41
 *
 * The divergence is perfectly correlated with the spec's silence. Not one of
 * the 41 disagreements is a code the spec has spoken about; every code it has
 * spoken about, both SDKs already answer the same way, and both already answer
 * it the way §2.4 does — 0 of 31 disagree on each side.
 *
 * That is the shape of a gap rather than of a bug, and it decides what this
 * gate is. There is nothing here to repair: what is missing is anything that
 * would NOTICE if the agreement broke. `check-error-registry.php` compares
 * `errorText`, `severity` and `recoverable` and stops — deliberately, because
 * those are the columns §3 carries — so a status could be edited to anything
 * at all and every gate in this repository would stay green.
 *
 * WHAT THIS CHECKS, AND WHAT IT DELIBERATELY DOES NOT
 * ---------------------------------------------------
 * Only the 31 codes §2.4's table names. Where the spec is silent the SDK is
 * free, and this gate says nothing about those 89 — pinning them here would
 * invent a normative rule the specification declines to state, and would
 * freeze a choice whose only current justification is that someone made it.
 * The 41 divergences are therefore NOT findings and are not reported as such.
 *
 * It does NOT propose widening §2.4. Adding codes to that table is a normative
 * change to the specification and belongs in a spec PR, not in an SDK gate.
 *
 * Usage: php scripts/check-http-status.php <spec-root> [ref-label]
 */

require __DIR__.'/../vendor/autoload.php';

use Ospp\Protocol\Enums\OsppErrorCode;

$specRoot = $argv[1] ?? '';
$refLabel = $argv[2] ?? 'unknown ref';
if ($specRoot === '' || !is_file($specRoot.'/spec/07-errors.md')) {
    fwrite(STDERR, "usage: php scripts/check-http-status.php <spec-root> [ref-label]\n");
    exit(2);
}

/**
 * Parse the §2.4 "HTTP status code mapping" table.
 *
 * The table is keyed the other way round from the SDK: one row per STATUS,
 * whose second cell lists the codes typically answered with it. So the row is
 * exploded into one entry per code rather than read as a pair.
 *
 * @return array<int, int> code => status
 */
function statusTable(string $md): array
{
    if (preg_match('/\*\*HTTP status code mapping:\*\*\s*\n(.*?)(?=\n\n)/s', $md, $m) !== 1) {
        fwrite(STDERR, "ERROR: the '**HTTP status code mapping:**' table is not in spec/07-errors.md — \n");
        fwrite(STDERR, "the section has been renamed or restructured; fix this parser rather than the spec.\n");
        exit(2);
    }
    $out = [];
    foreach (explode("\n", $m[1]) as $row) {
        if (!str_starts_with($row, '|')) {
            continue;
        }
        $cells = array_map('trim', explode('|', trim($row, '|')));
        if (count($cells) < 2 || preg_match('/^\d{3}$/', $cells[0]) !== 1) {
            continue;
        }
        preg_match_all('/\b(\d{4})\b/', $cells[1], $codes);
        foreach ($codes[1] as $code) {
            $out[(int) $code] = (int) $cells[0];
        }
    }

    return $out;
}

$md = (string) file_get_contents($specRoot.'/spec/07-errors.md');
$table = statusTable($md);

// ── positive control ───────────────────────────────────────────────────────
//
// A comparator that finds nothing proves nothing. Before the real comparison
// runs, the same comparison is run over a table with one status deliberately
// wrong, and this script refuses to return a verdict unless that is caught.
$control = $table;
$controlCode = array_key_first($control);
if ($controlCode === null) {
    fwrite(STDERR, "ERROR: the §2.4 table parsed to ZERO codes — nothing could be compared.\n");
    exit(2);
}
$control[$controlCode] = $control[$controlCode] === 599 ? 598 : 599;
$controlCaught = false;
foreach (OsppErrorCode::cases() as $case) {
    $code = (int) $case->value;
    if (isset($control[$code]) && $case->httpStatus() !== $control[$code]) {
        $controlCaught = true;
    }
}
if (!$controlCaught) {
    fwrite(STDERR, "ERROR: positive control FAILED — a deliberately wrong status was not caught.\n");
    exit(2);
}
echo "positive control: a deliberately wrong status in the §2.4 table was caught — OK\n";

// ── non-vacuity ────────────────────────────────────────────────────────────
//
// The table named 31 codes at v0.42.0. A parse that suddenly yields a handful
// means the markup moved, and a gate comparing three codes while reporting
// success is the failure this repository has shipped before.
if (count($table) < 20) {
    fwrite(STDERR, 'ERROR: parsed only '.count($table)." code(s) from the §2.4 status table — \n");
    fwrite(STDERR, "the table markup has changed. Refusing a verdict rather than checking almost nothing.\n");
    exit(2);
}

// ── the comparison ─────────────────────────────────────────────────────────

$sdk = [];
foreach (OsppErrorCode::cases() as $case) {
    $sdk[(int) $case->value] = $case->httpStatus();
}
ksort($sdk);

/** @var list<string> $problems */
$problems = [];

// spec -> SDK: every code the table names must exist here and agree.
foreach ($table as $code => $status) {
    if (!isset($sdk[$code])) {
        $problems[] = "{$code}: named by the §2.4 status table, MISSING from OsppErrorCode";

        continue;
    }
    if ($sdk[$code] !== $status) {
        $problems[] = "{$code}: §2.4 says HTTP {$status}, this SDK answers {$sdk[$code]}";
    }
}

$named = count(array_intersect_key($sdk, $table));
$free = count($sdk) - $named;

printf("spec ref                     : %s\n", $refLabel);
printf("registry codes in this SDK   : %d\n", count($sdk));
printf("named by the §2.4 table      : %d  (compared)\n", $named);
printf("not named by §2.4            : %d  (the spec is silent; this gate says nothing about them)\n", $free);

if ($problems !== []) {
    echo "\nMISMATCH — ".count($problems)." code(s) disagree with the §2.4 status table:\n";
    foreach ($problems as $p) {
        echo "  x {$p}\n";
    }
    echo "\nThe spec's table is the authority wherever it speaks. If the SDK value is the one\n";
    echo "that is right, the change belongs in a spec PR against §2.4 — not here.\n";
    exit(1);
}

echo "\nOK — all {$named} codes named by §2.4 carry the status the spec gives them.\n";
exit(0);
