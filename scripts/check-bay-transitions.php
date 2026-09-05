<?php

declare(strict_types=1);

/**
 * Derive the bay transition table from spec 05-state-machines.md §2.3 and compare it
 * to BayTransitions.
 *
 * WHY THIS EXISTS
 *
 * `BayCanonicalTableContractTest` calls itself the canonical table and TRANSCRIBES it:
 * twenty-one `(from, to)` pairs written out by hand, with a docblock quoting the spec.
 * A transcription is not a comparison. Spec `0.30.0` added `Unknown -> Reserved` — a
 * station that rebooted holding a `Confirmed` reservation reports `Reserved`, and the
 * spec made persisting that reservation a station-side MUST — and the row reached the
 * canonical table and NOTHING else. Both SDKs refused the transition for three releases,
 * the reference server delegates to them, and every artefact involved was green.
 *
 * The six state machines are the only registries in this ecosystem with no parity gate.
 * The error registry, the config registry, the action registry, the recommended actions,
 * the schemas and the vectors all have one. This closes the bay half.
 *
 * Usage: php scripts/check-bay-transitions.php <spec-root> [ref-label]
 */

const STATES = ['Unknown', 'Available', 'Reserved', 'Occupied', 'Finishing', 'Faulted', 'Unavailable'];

$specRoot = $argv[1] ?? null;
$refLabel = $argv[2] ?? 'local checkout';

if ($specRoot === null || ! is_dir($specRoot)) {
    fwrite(STDERR, "usage: php scripts/check-bay-transitions.php <spec-root> [ref-label]\n");
    exit(2);
}

$path = $specRoot.'/spec/05-state-machines.md';
if (! is_file($path)) {
    fwrite(STDERR, "ERROR: {$path} not found\n");
    exit(2);
}

$lines = file($path, FILE_IGNORE_NEW_LINES);

// Locate §2.3 and the heading that ends it. Bounded rather than global: the file holds
// six transition tables and a global scan would merge them.
$start = null;
$end = count($lines);
foreach ($lines as $i => $l) {
    if ($start === null && str_starts_with($l, '### 2.3')) {
        $start = $i;
        continue;
    }
    if ($start !== null && $i > $start && (str_starts_with($l, '### ') || str_starts_with($l, '## '))) {
        $end = $i;
        break;
    }
}
if ($start === null) {
    fwrite(STDERR, "ERROR: §2.3 not found in {$path} — the section matcher is broken, not the table\n");
    exit(2);
}

/** @var array<string, array<string, true>> $spec */
$spec = ['Station' => [], 'Server' => []];

for ($i = $start; $i < $end; $i++) {
    $l = $lines[$i];
    if (! str_starts_with($l, '|') || preg_match('/^\|[\s:|-]+\|$/', $l) === 1) {
        continue;
    }
    $cells = array_map('trim', explode('|', trim($l, '|')));
    if (count($cells) < 5 || stripos($cells[0], 'trigger') === 0) {
        continue;
    }
    $actor = str_contains($cells[3], 'Station') ? 'Station'
        : (str_contains($cells[3], 'Server') ? 'Server' : null);
    if ($actor === null) {
        continue;
    }
    $to = trim($cells[2], '`* ');
    if (! in_array($to, STATES, true)) {
        continue;
    }
    // A row may name several source states; each is its own transition.
    foreach (explode(',', $cells[1]) as $from) {
        $from = trim($from, '`* ');
        if (in_array($from, STATES, true)) {
            $spec[$actor]["{$from}->{$to}"] = true;
        }
    }
}

if ($spec['Station'] === [] || $spec['Server'] === []) {
    fwrite(STDERR, "ERROR: parsed 0 rows for one of the parties — the row matcher is broken, not the SDK\n");
    exit(2);
}

require __DIR__.'/../vendor/autoload.php';

use Ospp\Protocol\Enums\BayStatus;
use Ospp\Protocol\Enums\EffectedBy;
use Ospp\Protocol\StateMachines\BayTransitions;

$machine = new BayTransitions();

/** @var array<string, array<string, true>> $sdk */
$sdk = ['Station' => [], 'Server' => []];
foreach ([['Station', EffectedBy::STATION], ['Server', EffectedBy::SERVER]] as [$label, $party]) {
    foreach (BayStatus::cases() as $from) {
        foreach (BayStatus::cases() as $to) {
            if ($machine->canTransition($from, $to, $party)) {
                $sdk[$label][ucfirst($from->value).'->'.ucfirst($to->value)] = true;
            }
        }
    }
}

// §2.3: "A station implements the `Station` rows. A server implements all of them."
// So the SERVER party is the UNION, and comparing it to the six Server-effected rows
// alone reports every Station row as an extra. That is what this gate did on its first
// run -- one true finding and twenty false ones -- and the false ones are what said the
// instrument was wrong rather than the SDK.
$expected = [
    'Station' => $spec['Station'],
    'Server' => $spec['Station'] + $spec['Server'],
];

$failures = [];
foreach (['Station', 'Server'] as $party) {
    $missing = array_diff_key($expected[$party], $sdk[$party]);
    $extra = array_diff_key($sdk[$party], $expected[$party]);
    foreach (array_keys($missing) as $k) {
        $failures[] = "{$party}: {$k} is in the spec and REFUSED by this SDK";
    }
    foreach (array_keys($extra) as $k) {
        $failures[] = "{$party}: {$k} is allowed by this SDK and in NO spec row";
    }
}

printf(
    "bay transitions, derived from %s §2.3 (%s): spec Station=%d Server=%d | SDK Station=%d Server=%d\n",
    '05-state-machines.md',
    $refLabel,
    count($spec['Station']),
    count($spec['Station']) + count($spec['Server']),
    count($sdk['Station']),
    count($sdk['Server']),
);

if ($failures !== []) {
    fwrite(STDERR, "\nFAIL — ".count($failures)." disagreement(s):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  {$f}\n");
    }
    fwrite(STDERR, "\nFix the SDK to match §2.3. If the SPEC is what is wrong, fix it there and\n");
    fwrite(STDERR, "re-pin .spec-ref — do not 'correct' the table here.\n");
    exit(1);
}

echo "OK — the bay table and this SDK agree on every transition, both directions\n";
exit(0);
