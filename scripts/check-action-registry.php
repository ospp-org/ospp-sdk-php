<?php

declare(strict_types=1);

/**
 * Gate: the SDK action registry vs the SPEC message catalog.
 *
 * Compares `OsppAction` against the MQTT Quick Reference table in the spec's
 * `spec/03-messages.md` — the action names in both directions, the count three
 * ways, and the 27/3 partition this SDK draws and the spec does not.
 *
 * ---
 *
 * **Why this gate exists.** `OsppAction` is what the whole SDK is keyed on —
 * signing classification, envelope construction, the station/server direction
 * split — and it was the only registry here with NOTHING comparing it to the
 * spec. `07-errors.md` has `check-error-registry`, `08-configuration.md` has
 * `check-config-registry`, `schemas/` and both vector corpora have byte gates.
 * Chapter 03 had a doc comment and five `assertCount()` calls that compare this
 * file to itself.
 *
 * Self-comparison is the shape of the `5004 ELECTRICAL_SYSTEM` finding recorded
 * in `check-error-registry.php`: two things that agree are not evidence when
 * neither is the source. `OsppActionTest` asserts 30/27/3 against the constants
 * it is counting, so it goes green for any spec, including one that renamed an
 * action last week.
 *
 * **The 27/3 partition is the part with no upstream.** The spec catalogue has no
 * notion of an "API-only" action: `IssueOfflinePass`, `RevokeOfflinePass` and
 * `WebPaymentAuthorization` appear nowhere in `03-messages.md` (0 occurrences at
 * `v0.31.0`, measured, not assumed). That absence is the whole content of the
 * claim `apiOnlyActions()` makes, so it is checked as an absence — and checked
 * in the direction that matters: if the spec ever adopts one of the three as an
 * MQTT action, the bucket must move, and the "missing from OsppAction::mqtt"
 * arm below is what says so rather than the enum quietly disagreeing with the
 * wire.
 *
 * This file is the comparison only. `scripts/check-action-registry.sh` resolves
 * the spec checkout (clone at `.spec-ref`, or `SPEC_REPO`) and calls it.
 *
 * Usage: php scripts/check-action-registry.php <spec-root> [<ref-label>]
 */

require __DIR__.'/../vendor/autoload.php';

use Ospp\Protocol\Actions\OsppAction;

$specRoot = $argv[1] ?? null;
$refLabel = $argv[2] ?? 'local checkout';

if ($specRoot === null || ! is_dir($specRoot)) {
    fwrite(STDERR, "Usage: php scripts/check-action-registry.php <spec-root> [<ref-label>]\n");
    exit(1);
}

/** The `### MQTT Messages (N actions)` heading that opens the Quick Reference table. */
const HEADING = '/^###\s+MQTT Messages\s+\((\d+)\s+actions?\)\s*$/';

/**
 * One Quick Reference row. Only the Action cell is read.
 *
 * The first column is an `MSG-0NN` reference and does NOT ascend — `SessionEnded`
 * carries 40 and sits between rows 10 and 11, grouped by category rather than by
 * number (`03-messages.md`, the note under the table). A parser anchored on that
 * column would stop there and silently check 10 of 27.
 *
 * The cell is a Markdown link in every row at `v0.31.0`; the bare form is accepted
 * too, because a table that stops linking its own anchors is a formatting change
 * and this gate must not report one as a registry change.
 */
const ROW = '/^\|[^|]*\|\s*(?:\[\s*([A-Za-z][A-Za-z0-9]*)\s*\]\([^)]*\)|([A-Za-z][A-Za-z0-9]*))\s*\|/';

/**
 * Extract the MQTT action names from the Quick Reference section.
 *
 * Returns the heading's declared count alongside the rows so the caller can
 * compare the spec against itself. Scanning stops at the next `###` — the very
 * next section is `### BLE Messages (13 message types)`, whose thirteen rows are
 * not MQTT actions and whose inclusion would put this gate at 40 and make every
 * later comparison meaningless.
 *
 * @return array{declared: int|null, actions: list<string>}
 */
function parseQuickReference(string $md): array
{
    $declared = null;
    $actions = [];
    $inSection = false;

    foreach (preg_split('/\r?\n/', $md) ?: [] as $line) {
        if (preg_match(HEADING, $line, $h) === 1) {
            if ($inSection) {
                break; // a second MQTT heading: ambiguous, let the count check fail
            }
            $declared = (int) $h[1];
            $inSection = true;

            continue;
        }
        if (! $inSection) {
            continue;
        }
        if (preg_match('/^#{1,3}\s/', $line) === 1) {
            break; // next section — BLE messages start here
        }
        if (preg_match(ROW, $line, $m) !== 1) {
            continue;
        }
        $name = ($m[1] !== '') ? $m[1] : $m[2];
        if ($name === 'Action') {
            continue; // the header row
        }
        $actions[] = $name;
    }

    return ['declared' => $declared, 'actions' => $actions];
}

// ── POSITIVE CONTROL, before any negative result is believed ────────────────
//
// Every check below reports an ABSENCE — "no name is missing", "no count
// disagrees" — and a parser that matches nothing reports exactly that, in the
// same words, with exit 0. The row-count floor further down catches a parser
// that dies completely; it does not catch one that still matches but has
// stopped seeing the shape that matters. So the parser is first run over a
// synthetic table whose answer is known, and then over the same table with a
// one-character drift planted in it. If the instrument cannot fail on demand,
// nothing it says about the real spec is worth reading.

$control = implode("\n", [
    '### MQTT Messages (3 actions)',
    '',
    '| MSG | Action | Direction |',
    '|--:|--------|-----------|',
    '| 1 | [Alpha](#1-alpha) | Station → Server |',
    '| 40 | [Bravo](#2-bravo) | Station → Server |',
    '| 2 | Charlie | Server → Station |',
    '',
    '### BLE Messages (2 message types)',
    '',
    '| MSG | Message | Direction |',
    '|--:|---------|-----------|',
    '| 27 | [Delta](#3-delta) | Station → App |',
]);

$c = parseQuickReference($control);
if ($c['declared'] !== 3
    || implode(',', $c['actions']) !== 'Alpha,Bravo,Charlie'   // linked and bare forms both read
    || in_array('Delta', $c['actions'], true)) {               // the BLE section did not leak in
    fwrite(STDERR, "ERROR: positive control FAILED — the Quick Reference parser did not read a table\n"
        ."handed to it with the answer known.\n"
        .'  expected declared=3 actions=Alpha,Bravo,Charlie'."\n"
        .'  got      declared='.var_export($c['declared'], true).' actions='.(implode(',', $c['actions']) ?: '(none)')."\n"
        ."Refusing to report anything about the real spec.\n");
    exit(1);
}

$planted = parseQuickReference(str_replace('[Bravo](#2-bravo)', '[Bravni](#2-bravo)', $control));
if (in_array('Bravo', $planted['actions'], true) || ! in_array('Bravni', $planted['actions'], true)) {
    fwrite(STDERR, "ERROR: positive control FAILED — a planted one-character drift in an action name\n"
        ."was not observed by the parser. The comparison below would be vacuous.\n");
    exit(1);
}
echo "positive control: parser read a known table and observed a planted drift — OK\n";

// ── the comparison ─────────────────────────────────────────────────────────

$catalogPath = $specRoot.'/spec/03-messages.md';
$md = @file_get_contents($catalogPath);

if ($md === false) {
    fwrite(STDERR, "ERROR: cannot read {$catalogPath}\n");
    exit(1);
}

['declared' => $declared, 'actions' => $actions] = parseQuickReference($md);

if ($declared === null) {
    fwrite(STDERR, "ERROR: no '### MQTT Messages (N actions)' heading found in spec/03-messages.md at {$refLabel}.\n"
        ."The Quick Reference section has been renamed or restructured — fix the parser in\n"
        ."scripts/check-action-registry.php. Refusing to report a pass.\n");
    exit(1);
}

// A regex that silently matches nothing would make this gate pass vacuously on
// any reformatting of the table. Refuse to be that gate.
if (count($actions) < 20) {
    fwrite(STDERR, 'ERROR: parsed only '.count($actions)." rows from the Quick Reference table — the table\n"
        ."format has probably changed. Refusing to report a pass; fix the parser.\n");
    exit(1);
}

$dupes = array_unique(array_diff_assoc($actions, array_unique($actions)));
if ($dupes !== []) {
    fwrite(STDERR, 'ERROR: spec 03-messages.md lists these actions more than once: '.implode(', ', $dupes)."\n");
    exit(1);
}

$spec = array_values(array_unique($actions));
$mqtt = OsppAction::mqttActions();
$apiOnly = OsppAction::apiOnlyActions();
$all = OsppAction::all();

/** @var list<string> $problems */
$problems = [];

foreach ($spec as $name) {
    if (! in_array($name, $mqtt, true)) {
        $problems[] = "{$name}: in the spec Quick Reference, MISSING from OsppAction::mqttActions()";
    }
}
foreach ($mqtt as $name) {
    if (! in_array($name, $spec, true)) {
        $problems[] = "{$name}: in OsppAction::mqttActions(), MISSING from the spec Quick Reference";
    }
}

// The partition. `apiOnlyActions()` claims these are not protocol messages, and
// the only upstream evidence for that claim is their absence from the catalogue.
foreach ($apiOnly as $name) {
    if (in_array($name, $spec, true)) {
        $problems[] = "{$name}: OsppAction calls it API-only, but spec {$refLabel} lists it as an MQTT action "
            .'— it must move to the MQTT bucket';
    }
}

// The two buckets must exhaust `all()` and not overlap: a constant added to the
// class and to neither list is invisible to every check above.
$partition = array_merge($mqtt, $apiOnly);
sort($partition);
$allSorted = $all;
sort($allSorted);
if ($partition !== $allSorted) {
    $problems[] = 'partition: mqttActions() + apiOnlyActions() ('.count($partition).') does not equal all() ('
        .count($all).') — a constant belongs to neither bucket, or to both';
}

// The three-way count check, reported separately from the names: a name
// mismatch is a rename or an addition, while a heading/rows mismatch is the
// spec disagreeing with itself and belongs upstream, not here.
if ($declared !== count($spec)) {
    $problems[] = "count: the spec heading declares {$declared} actions but ".count($spec)
        .' rows follow it — the spec disagrees with itself; fix it in ospp-org/spec, not here';
}
if (count($spec) !== count($mqtt)) {
    $problems[] = 'count: spec table has '.count($spec).' actions, OsppAction::mqttActions() has '.count($mqtt);
}

// ── the prose in the class the gate is about ────────────────────────────────
//
// The header states all three numbers, and the constant groups each state their
// own subtotal. None of it was compared to anything. The numbers stay — a reader
// opening the file deserves them — but they are now derived, in the only sense
// available to a sentence: they are checked on every run.

$src = (string) file_get_contents(__DIR__.'/../src/Actions/OsppAction.php');

if (preg_match_all('/All (\d+) OSPP action constants \((\d+) MQTT \+ (\d+) API-only\)/', $src, $h) !== 1) {
    $problems[] = 'doc comment: expected exactly one "All N OSPP action constants (N MQTT + N API-only)" claim in '
        .'src/Actions/OsppAction.php, found '.count($h[0]).'. A claim that has been deleted is not a claim that '
        .'passes — this gate reads that line and cannot check what is no longer there.';
} else {
    $claims = [
        ['all', (int) $h[1][0], count($all)],
        ['MQTT', (int) $h[2][0], count($mqtt)],
        ['API-only', (int) $h[3][0], count($apiOnly)],
    ];
    foreach ($claims as [$what, $said, $is]) {
        if ($said !== $is) {
            $problems[] = "doc comment: header says {$said} {$what} actions, there are {$is}";
        }
    }
}

// The per-group subtotals: `// ... — MQTT (6)` and `// API-Only Actions (3)`.
// They are the numbers most likely to rot, because adding a constant to a group
// touches the line above it and not the comment.
preg_match_all('/^\s*\/\/ .*MQTT \((\d+)\)$/m', $src, $g);
preg_match_all('/^\s*\/\/ API-Only Actions \((\d+)\)/m', $src, $a);

if ($g[1] === [] || $a[1] === []) {
    $problems[] = 'group subtotals: found '.count($g[1]).' MQTT group comments and '.count($a[1])
        .' API-only group comments — the comment format changed and these subtotals are no longer checked';
} else {
    $mqttSum = array_sum(array_map('intval', $g[1]));
    $apiSum = array_sum(array_map('intval', $a[1]));
    if ($mqttSum !== count($mqtt)) {
        $problems[] = 'group subtotals: the MQTT group comments sum to '.$mqttSum.' ('
            .implode('+', $g[1]).'), there are '.count($mqtt).' MQTT actions';
    }
    if ($apiSum !== count($apiOnly)) {
        $problems[] = 'group subtotals: the API-only group comment says '.$apiSum.', there are '
            .count($apiOnly).' API-only actions';
    }
}

echo "spec {$refLabel}: heading declares {$declared}, table has ".count($spec).' rows    '
    .'OsppAction: '.count($all).' constants = '.count($mqtt).' MQTT + '.count($apiOnly)." API-only\n";

if ($problems !== []) {
    fwrite(STDERR, "\nDRIFT between OsppAction and spec {$refLabel} — ".count($problems)." problem(s):\n\n");
    foreach ($problems as $p) {
        fwrite(STDERR, "  {$p}\n");
    }
    fwrite(STDERR, "\nFix: change the SDK to match the spec. `03-messages.md` is the source of truth for the\n"
        ."action set. If the SPEC is what is wrong, fix it there first and re-pin .spec-ref — do not\n"
        ."\"correct\" it here.\n");
    exit(1);
}

echo 'OK — all '.count($spec)." MQTT actions agree with spec {$refLabel}, and the ".count($apiOnly)
    ." API-only actions appear nowhere in its catalogue\n";
