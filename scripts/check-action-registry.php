<?php

declare(strict_types=1);

/**
 * Gate: the SDK action registry vs the SPEC message catalog.
 *
 * Compares `OsppAction` against the MQTT Quick Reference table in the spec's
 * `spec/03-messages.md` — the action names in both directions, the Direction and
 * Type cell of every row in both directions, the count three ways, and the 27/3
 * partition this SDK draws and the spec does not.
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
 * **The Direction and Type columns were read by nothing.** Until this change the
 * parser took the Action cell and discarded the rest of the row, which left four
 * public accessors — `stationToServer()`, `serverToStation()`, `events()` and
 * `requests()` — with no upstream whatsoever. What stood in for one was
 * `OsppActionTest`: `assertCount()` calls stating 13, 15, 7 and 20 against the
 * very arrays being counted, plus a disjointness and a cover assertion drawn
 * between those same arrays. That is the `5004 ELECTRICAL_SYSTEM` shape a third
 * time — the four numbers were right, and nothing but transcription made them
 * right, so they were green for any spec including one that re-routed an action
 * last week. Both columns are now parsed for all 27 rows and compared in both
 * directions, spec to SDK and SDK to spec.
 *
 * **Four Direction literals, four accessors, one to one.** Measured at
 * `v0.42.0`, the Direction column holds exactly four distinct strings across its
 * 27 rows: `Station → Server` (11 rows), `Server → Station` (14),
 * `Bidirectional` (1 — `DataTransfer`) and
 * `Broker → Server, or Station → Server` (1 — `ConnectionLost`). Each maps to
 * one accessor and no accessor answers to two literals, so the comparison below
 * runs at the catalogue's own resolution.
 *
 * It did not, until 0.40.0. This class exposed two direction accessors, holding
 * 13 and 15 against the catalogue's 11 and 14, because the two rows with nowhere
 * to go were absorbed: `ConnectionLost` into the station list, `DataTransfer`
 * into BOTH. `DIRECTION_BUCKETS` reproduced that as a projection, four literals
 * onto two buckets, and the projection was lossy in the direction that matters —
 * it could not tell `Bidirectional` from a row written down twice, nor the
 * broker row from a plain station row, so neither mistake could ever be reported.
 * `brokerToServer()` and `bidirectional()` are what removed the need for it. The
 * sibling TypeScript SDK had already made the same four lists disjoint; both
 * SDKs now answer the Direction question at the same resolution and with the
 * same membership.
 *
 * **Type maps one to one.** `REQ/RES` (20 rows) is `requests()`, `EVENT` (7) is
 * `events()`. That was always true; the directions have caught up with it.
 *
 * **An unknown literal is refused, never skipped.** A Direction or Type string
 * the tables below have no case for would otherwise fall out of both sides of
 * the comparison and be reported as agreement — the precise way a gate goes
 * quietly blind. So would a row whose Direction or Type cell has gone missing.
 * Both exit non-zero naming the row, and so does a parse in which no row carried
 * either cell at all.
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
 * Column offsets into a Quick Reference row once it is split on the pipe.
 *
 * `| MSG | Action | Direction | Type | ... |` splits to `['', ' MSG ', ' Action ',
 * ' Direction ', ' Type ', ..., '']`: the empty leading element is the text
 * before the first pipe, so the first real cell is at 1 and MSG occupies it.
 *
 * The Direction and Type cells are taken by SPLITTING the row rather than by
 * widening `ROW`. A wider regex that required four cells would simply not match
 * a row that had lost one, the row would vanish from the parse, and the loss
 * would surface as a count disagreement — a rename or a deletion, which it is
 * not. Splitting keeps the row and lets the completeness check below name it.
 */
const COL_ACTION = 2;
const COL_DIRECTION = 3;
const COL_TYPE = 4;

/**
 * The four Direction literals of the catalogue, one literal to one accessor.
 *
 * A literal absent from this table is refused, not ignored.
 *
 * The value is a STRING and not a list, and that is the whole repair. While it
 * was a list, `Bidirectional` could name two buckets and the broker literal
 * could name the station bucket, and the comparison below could not tell either
 * one from a plain row. A string cannot express that, so the projection cannot
 * come back without changing the type.
 *
 * @var array<string, string>
 */
const DIRECTION_BUCKETS = [
    'Station → Server' => 'stationToServer',
    'Server → Station' => 'serverToStation',
    'Bidirectional' => 'bidirectional',
    'Broker → Server, or Station → Server' => 'brokerToServer',
];

/**
 * The two Type literals, one to one onto the two type accessors. These were
 * never projected; they are spelled the same way as the directions now are.
 *
 * @var array<string, string>
 */
const TYPE_BUCKETS = [
    'REQ/RES' => 'requests',
    'EVENT' => 'events',
];

/**
 * Trim a table cell and collapse its internal whitespace.
 *
 * A Markdown table is free to pad its columns, and a re-flow that changes the
 * padding is a formatting change. This gate must not report one as a routing
 * change, so the cell is compared after normalisation and never raw.
 */
function normalizeCell(string $cell): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $cell));
}

/**
 * Extract the MQTT action names from the Quick Reference section.
 *
 * Returns the heading's declared count alongside the rows so the caller can
 * compare the spec against itself. Scanning stops at the next `###` — the very
 * next section is `### BLE Messages (13 message types)`, whose thirteen rows are
 * not MQTT actions and whose inclusion would put this gate at 40 and make every
 * later comparison meaningless.
 *
 * `rows` carries the Direction and Type cell of each row alongside its action,
 * with `null` where the cell is absent entirely — the caller refuses a verdict
 * on a null rather than treating the row as unclassified, because an
 * unclassified row is invisible to a comparison that only reports differences.
 *
 * @return array{declared: int|null, actions: list<string>, rows: list<array{action: string, actionCell: string, direction: string|null, type: string|null}>}
 */
function parseQuickReference(string $md): array
{
    $declared = null;
    $actions = [];
    $rows = [];
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

        $cells = explode('|', rtrim($line));
        $rows[] = [
            'action' => $name,
            // The row is read twice — once by ROW, once by the split — and the
            // two must land on the same column. Recorded so a column inserted
            // before Action is caught against the REAL table; the synthetic
            // control below has a fixed layout and cannot see that happen.
            'actionCell' => isset($cells[COL_ACTION]) ? normalizeCell($cells[COL_ACTION]) : '',
            'direction' => isset($cells[COL_DIRECTION]) ? (normalizeCell($cells[COL_DIRECTION]) ?: null) : null,
            'type' => isset($cells[COL_TYPE]) ? (normalizeCell($cells[COL_TYPE]) ?: null) : null,
        ];
    }

    return ['declared' => $declared, 'actions' => $actions, 'rows' => $rows];
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

// The control table carries all four Direction literals and both Type literals,
// because a control that exercises one case per column proves only that the
// column is reachable, not that its VALUE is read. Charlie is bare rather than
// linked, and the BLE section below the blank line is the leak canary.

$control = implode("\n", [
    '### MQTT Messages (4 actions)',
    '',
    '| MSG | Action | Direction | Type | Category | Timeout |',
    '|--:|--------|-----------|------|----------|--------:|',
    '| 1 | [Alpha](#1-alpha) | Station → Server | REQ/RES | Core | 30s |',
    '| 40 | [Bravo](#2-bravo) | Broker → Server, or Station → Server | EVENT | Core | — |',
    '| 2 | Charlie | Server → Station | REQ/RES | Core | 5s |',
    '| 3 | [Delta](#3-delta) | Bidirectional | REQ/RES | Core | 30s |',
    '',
    '### BLE Messages (2 message types)',
    '',
    '| MSG | Message | Direction | Characteristic | Category |',
    '|--:|---------|-----------|----------------|----------|',
    '| 27 | [Echo](#4-echo) | Station → App | Notify | Core |',
]);

$c = parseQuickReference($control);
$cDirections = implode('; ', array_map(
    static fn (array $r): string => $r['action'].'='.($r['direction'] ?? '(absent)'),
    $c['rows']
));
$cTypes = implode('; ', array_map(
    static fn (array $r): string => $r['action'].'='.($r['type'] ?? '(absent)'),
    $c['rows']
));

$expectDirections = 'Alpha=Station → Server; Bravo=Broker → Server, or Station → Server; '
    .'Charlie=Server → Station; Delta=Bidirectional';
$expectTypes = 'Alpha=REQ/RES; Bravo=EVENT; Charlie=REQ/RES; Delta=REQ/RES';

if ($c['declared'] !== 4
    || implode(',', $c['actions']) !== 'Alpha,Bravo,Charlie,Delta'  // linked and bare forms both read
    || in_array('Echo', $c['actions'], true)                        // the BLE section did not leak in
    || $cDirections !== $expectDirections
    || $cTypes !== $expectTypes) {
    fwrite(STDERR, "ERROR: positive control FAILED — the Quick Reference parser did not read a table\n"
        ."handed to it with the answer known.\n"
        .'  expected declared=4 actions=Alpha,Bravo,Charlie,Delta'."\n"
        .'           directions '.$expectDirections."\n"
        .'           types      '.$expectTypes."\n"
        .'  got      declared='.var_export($c['declared'], true).' actions='.(implode(',', $c['actions']) ?: '(none)')."\n"
        .'           directions '.($cDirections ?: '(none)')."\n"
        .'           types      '.($cTypes ?: '(none)')."\n"
        ."Refusing to report anything about the real spec.\n");
    exit(1);
}

// Three planted drifts, one per cell that is read. A parser can read a column
// and still be pinned to a constant; only a planted change proves the value on
// the page is what reaches the comparison.

$planted = parseQuickReference(str_replace('[Bravo](#2-bravo)', '[Bravni](#2-bravo)', $control));
if (in_array('Bravo', $planted['actions'], true) || ! in_array('Bravni', $planted['actions'], true)) {
    fwrite(STDERR, "ERROR: positive control FAILED — a planted one-character drift in an action name\n"
        ."was not observed by the parser. The comparison below would be vacuous.\n");
    exit(1);
}

$flippedDir = parseQuickReference(str_replace(
    '| 2 | Charlie | Server → Station |',
    '| 2 | Charlie | Station → Server |',
    $control
));
$charlieDir = null;
foreach ($flippedDir['rows'] as $r) {
    if ($r['action'] === 'Charlie') {
        $charlieDir = $r['direction'];
    }
}
if ($charlieDir !== 'Station → Server') {
    fwrite(STDERR, "ERROR: positive control FAILED — a planted flip of a row's Direction cell was not\n"
        .'observed by the parser (read '.var_export($charlieDir, true)." where 'Station → Server' was planted).\n"
        ."The Direction comparison below would be vacuous.\n");
    exit(1);
}

$flippedType = parseQuickReference(str_replace(
    '| 1 | [Alpha](#1-alpha) | Station → Server | REQ/RES |',
    '| 1 | [Alpha](#1-alpha) | Station → Server | EVENT |',
    $control
));
$alphaType = null;
foreach ($flippedType['rows'] as $r) {
    if ($r['action'] === 'Alpha') {
        $alphaType = $r['type'];
    }
}
if ($alphaType !== 'EVENT') {
    fwrite(STDERR, "ERROR: positive control FAILED — a planted flip of a row's Type cell was not observed\n"
        .'by the parser (read '.var_export($alphaType, true)." where 'EVENT' was planted).\n"
        ."The Type comparison below would be vacuous.\n");
    exit(1);
}

// And the refusal arm itself: a row that has LOST a cell must come back null,
// or the completeness check further down can never fire and the promise to
// refuse rather than pass is a promise nothing keeps.
$stripped = parseQuickReference(str_replace(
    '| 3 | [Delta](#3-delta) | Bidirectional | REQ/RES | Core | 30s |',
    '| 3 | [Delta](#3-delta) |',
    $control
));
$deltaRow = null;
foreach ($stripped['rows'] as $r) {
    if ($r['action'] === 'Delta') {
        $deltaRow = $r;
    }
}
if ($deltaRow === null || $deltaRow['direction'] !== null || $deltaRow['type'] !== null) {
    fwrite(STDERR, "ERROR: positive control FAILED — a row stripped of its Direction and Type cells was\n"
        ."not reported as missing them. The completeness check below cannot fire, so this gate\n"
        ."cannot keep its promise to refuse a verdict rather than pass on a table it stopped reading.\n");
    exit(1);
}

echo "positive control: parser read a known table, observed planted drifts in the Action, Direction\n"
    ."and Type cells, and reported a stripped row as incomplete — OK\n";

// ── the comparison ─────────────────────────────────────────────────────────

$catalogPath = $specRoot.'/spec/03-messages.md';
$md = @file_get_contents($catalogPath);

if ($md === false) {
    fwrite(STDERR, "ERROR: cannot read {$catalogPath}\n");
    exit(1);
}

['declared' => $declared, 'actions' => $actions, 'rows' => $rows] = parseQuickReference($md);

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

// ── the Direction and Type columns must actually be there ──────────────────
//
// Non-vacuity for the two new columns, in the same shape as the row floor
// above. A table whose Direction column has been renamed, moved or dropped
// would otherwise leave every row unclassified, every expected bucket empty,
// and every comparison below reporting no difference — a green run over a
// column nobody is reading any more. That is the exact failure the Action cell
// was given a floor to prevent, so the two new cells get the same treatment.

$misaligned = array_values(array_filter(
    $rows,
    static fn (array $r): bool => ! str_contains($r['actionCell'], $r['action'])
));
if ($misaligned !== []) {
    fwrite(STDERR, 'ERROR: '.count($misaligned).' of '.count($rows)." rows disagree about which column holds\n"
        ."the action — the regex and the cell split landed on different columns, so Direction and Type are\n"
        ."being read from the wrong cells:\n");
    foreach ($misaligned as $r) {
        fwrite(STDERR, "  matched '{$r['action']}' but column ".COL_ACTION." holds '{$r['actionCell']}'\n");
    }
    fwrite(STDERR, "Refusing to report a pass; fix the COL_* offsets in scripts/check-action-registry.php.\n");
    exit(1);
}

$withDirection = array_values(array_filter($rows, static fn (array $r): bool => $r['direction'] !== null));
$withType = array_values(array_filter($rows, static fn (array $r): bool => $r['type'] !== null));

if ($withDirection === [] || $withType === []) {
    fwrite(STDERR, 'ERROR: parsed '.count($rows).' rows from the Quick Reference table but '
        .count($withDirection)." carried a Direction cell and ".count($withType)." carried a Type cell.\n"
        ."The column layout of spec/03-messages.md has changed. Refusing to report a pass: a comparison\n"
        ."against columns that are no longer being read reports agreement for every row.\n");
    exit(1);
}

$incomplete = array_values(array_filter(
    $rows,
    static fn (array $r): bool => $r['direction'] === null || $r['type'] === null
));
if ($incomplete !== []) {
    fwrite(STDERR, 'ERROR: '.count($incomplete).' of '.count($rows)." Quick Reference rows are missing a\n"
        ."Direction or a Type cell:\n");
    foreach ($incomplete as $r) {
        fwrite(STDERR, '  '.$r['action'].': direction='.($r['direction'] ?? '(absent)')
            .' type='.($r['type'] ?? '(absent)')."\n");
    }
    fwrite(STDERR, "Refusing to report a pass; fix the parser in scripts/check-action-registry.php.\n");
    exit(1);
}

$unknownDirections = [];
$unknownTypes = [];
foreach ($rows as $r) {
    if (! array_key_exists($r['direction'], DIRECTION_BUCKETS)) {
        $unknownDirections[$r['direction']][] = $r['action'];
    }
    if (! array_key_exists($r['type'], TYPE_BUCKETS)) {
        $unknownTypes[$r['type']][] = $r['action'];
    }
}
if ($unknownDirections !== [] || $unknownTypes !== []) {
    fwrite(STDERR, "ERROR: the Quick Reference uses a Direction or Type literal this gate has no case for.\n");
    foreach ($unknownDirections as $literal => $who) {
        fwrite(STDERR, "  Direction '{$literal}' (".implode(', ', $who).') is not in DIRECTION_BUCKETS'."\n");
    }
    foreach ($unknownTypes as $literal => $who) {
        fwrite(STDERR, "  Type '{$literal}' (".implode(', ', $who).') is not in TYPE_BUCKETS'."\n");
    }
    fwrite(STDERR, "Refusing to report a pass. An unmapped literal drops its rows out of BOTH sides of the\n"
        ."comparison, which reads as agreement. Decide which accessor the new literal belongs to, add the\n"
        ."case, and re-run — do not let it fall through.\n");
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

// ── the Direction and Type columns vs the four routing accessors ───────────
//
// The expected membership of each accessor is BUILT from the catalogue rows, so
// there is no hand-written list on this side of the comparison to fall out of
// date. Both directions are reported, and separately, because they mean
// different things: a name the spec routes one way and the SDK does not carry
// there is an SDK that would publish on the wrong topic, while a name the SDK
// routes and the spec does not list at all is a bucket that has outlived its
// row.

/** @var array<string, list<string>> $expectedBuckets */
$expectedBuckets = [
    'stationToServer' => [],
    'serverToStation' => [],
    'brokerToServer' => [],
    'bidirectional' => [],
    'requests' => [],
    'events' => [],
];

foreach ($rows as $r) {
    $expectedBuckets[DIRECTION_BUCKETS[$r['direction']]][] = $r['action'];
    $expectedBuckets[TYPE_BUCKETS[$r['type']]][] = $r['action'];
}

/** @var array<string, list<string>> $actualBuckets */
$actualBuckets = [
    'stationToServer' => OsppAction::stationToServer(),
    'serverToStation' => OsppAction::serverToStation(),
    'brokerToServer' => OsppAction::brokerToServer(),
    'bidirectional' => OsppAction::bidirectional(),
    'requests' => OsppAction::requests(),
    'events' => OsppAction::events(),
];

/** @var array<string, string> $bucketColumn */
$bucketColumn = [
    'stationToServer' => 'Direction',
    'serverToStation' => 'Direction',
    'brokerToServer' => 'Direction',
    'bidirectional' => 'Direction',
    'requests' => 'Type',
    'events' => 'Type',
];

foreach ($expectedBuckets as $bucket => $expected) {
    $actual = $actualBuckets[$bucket];
    $column = $bucketColumn[$bucket];

    // A name listed twice inside one accessor would make the two count
    // comparisons below disagree with the membership ones for no stated reason.
    $inner = array_values(array_unique(array_diff_assoc($actual, array_unique($actual))));
    if ($inner !== []) {
        $problems[] = "OsppAction::{$bucket}() lists ".implode(', ', $inner).' more than once';
    }

    foreach ($expected as $name) {
        if (! in_array($name, $actual, true)) {
            $problems[] = "{$column}: spec {$refLabel} routes {$name} into {$bucket}, "
                ."MISSING from OsppAction::{$bucket}()";
        }
    }
    foreach ($actual as $name) {
        if (! in_array($name, $expected, true)) {
            $problems[] = "{$column}: OsppAction::{$bucket}() lists {$name}, but spec {$refLabel} does not "
                ."route it there";
        }
    }
    if (count(array_unique($expected)) !== count(array_unique($actual))) {
        $problems[] = "count: spec {$refLabel} routes ".count(array_unique($expected))
            ." actions into {$bucket}, OsppAction::{$bucket}() has ".count(array_unique($actual));
    }
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

$directionTally = [];
$typeTally = [];
foreach ($rows as $r) {
    $directionTally[$r['direction']] = ($directionTally[$r['direction']] ?? 0) + 1;
    $typeTally[$r['type']] = ($typeTally[$r['type']] ?? 0) + 1;
}

echo "spec {$refLabel}: heading declares {$declared}, table has ".count($spec).' rows    '
    .'OsppAction: '.count($all).' constants = '.count($mqtt).' MQTT + '.count($apiOnly)." API-only\n";
echo '  Direction over '.count($rows).' rows: '.implode(', ', array_map(
    static fn (string $k, int $v): string => "{$k}={$v}",
    array_keys($directionTally),
    $directionTally
))."\n";
echo '  Type over '.count($rows).' rows: '.implode(', ', array_map(
    static fn (string $k, int $v): string => "{$k}={$v}",
    array_keys($typeTally),
    $typeTally
))."\n";
echo '  routed onto the accessors: '.implode(', ', array_map(
    static fn (string $k, array $v): string => $k.'='.count(array_unique($v)),
    array_keys($expectedBuckets),
    $expectedBuckets
))."\n";

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

echo 'OK — all '.count($spec)." MQTT actions agree with spec {$refLabel} by name, Direction and Type; the "
    .count($apiOnly)." API-only actions appear nowhere in its catalogue\n";
