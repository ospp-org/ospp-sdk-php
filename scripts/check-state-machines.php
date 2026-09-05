<?php

declare(strict_types=1);

/**
 * Derive every state machine from spec 05-state-machines.md and compare it to this SDK.
 *
 * WHY THIS EXISTS
 *
 * The six transition tables were the ONLY registries in this ecosystem with no gate
 * deriving them from the specification. The error registry, the config registry, the
 * action registry, the recommended actions, the schemas and the vector corpus each have
 * one; the state machines had a CONTRACT TEST that TRANSCRIBED the pairs by hand, under
 * a docblock quoting the section it was copying. A transcription is not a comparison.
 *
 * Two rows drifted through that gap and were found by reading, not by any gate:
 *
 *   Bay `Unknown -> Reserved`     added at spec 0.30.0, refused by BOTH SDKs for three
 *                                 releases; the server delegates to them, so a station
 *                                 that rebooted holding a reservation had its one
 *                                 truthful post-boot report rejected in all three.
 *   Session `Active -> Completed` asserted in FOUR spec places for three autonomous stop
 *                                 reasons -- the physical Stop button among them --
 *                                 refused by both SDKs, and the reference server reached
 *                                 the right end state only by writing an intermediate
 *                                 STOPPING, which asserts an outstanding stop that does
 *                                 not exist.
 *
 * A gate over one machine would have caught the first and not the second. This covers all
 * of them, and it compares against the SPEC rather than against the sibling SDK: two
 * transcriptions that are identically wrong pass a cross-SDK comparison and fail this one.
 *
 * Usage: php scripts/check-state-machines.php <spec-root> [ref-label]
 */

use Ospp\Protocol\Enums\BayStatus;
use Ospp\Protocol\Enums\DiagnosticsState;
use Ospp\Protocol\Enums\EffectedBy;
use Ospp\Protocol\Enums\FirmwareUpdateStatus;
use Ospp\Protocol\Enums\ReservationStatus;
use Ospp\Protocol\Enums\SessionStatus;
use Ospp\Protocol\Enums\StationState;
use Ospp\Protocol\StateMachines\BayTransitions;
use Ospp\Protocol\StateMachines\DiagnosticsTransitions;
use Ospp\Protocol\StateMachines\FirmwareTransitions;
use Ospp\Protocol\StateMachines\ReservationTransitions;
use Ospp\Protocol\StateMachines\SessionTransitions;
use Ospp\Protocol\StateMachines\StationTransitions;

require __DIR__.'/../vendor/autoload.php';

$specRoot = $argv[1] ?? null;
$refLabel = $argv[2] ?? 'local checkout';
$selfTest = in_array('--self-test', $argv, true);

if ($specRoot === null || ! is_dir($specRoot)) {
    fwrite(STDERR, "usage: php scripts/check-state-machines.php <spec-root> [ref-label]\n");
    exit(2);
}

$path = $specRoot.'/spec/05-state-machines.md';
if (! is_file($path)) {
    fwrite(STDERR, "ERROR: {$path} not found\n");
    exit(2);
}

/**
 * The six machines this SDK implements, and the spec section each is derived from.
 *
 * BLE (§5.3) is deliberately absent and named here rather than silently skipped: this SDK
 * has no BLE state machine, the profile is EXPERIMENTAL, and a gate that quietly covered
 * six of seven tables would report a coverage it does not have.
 */
const MACHINES = [
    ['section' => '1.3', 'name' => 'Station', 'enum' => StationState::class, 'class' => StationTransitions::class],
    ['section' => '2.3', 'name' => 'Bay', 'enum' => BayStatus::class, 'class' => BayTransitions::class, 'party' => true],
    ['section' => '3.3', 'name' => 'Session', 'enum' => SessionStatus::class, 'class' => SessionTransitions::class],
    ['section' => '4.3', 'name' => 'Reservation', 'enum' => ReservationStatus::class, 'class' => ReservationTransitions::class],
    ['section' => '6.3', 'name' => 'Firmware', 'enum' => FirmwareUpdateStatus::class, 'class' => FirmwareTransitions::class],
    ['section' => '8.3', 'name' => 'Diagnostics', 'enum' => DiagnosticsState::class, 'class' => DiagnosticsTransitions::class],
];

const UNIMPLEMENTED = [['section' => '5.3', 'name' => 'BLE Connection', 'why' => 'no BLE state machine in this SDK; the profile is EXPERIMENTAL']];

$lines = file($path, FILE_IGNORE_NEW_LINES);

/**
 * Every transition of one section, as "From->To" strings.
 *
 * Bounded to its own section: the file holds seven transition tables and a global scan
 * merges them. Column positions are read from the header rather than assumed, because the
 * Bay table carries an extra `Effected by` column the other six do not.
 *
 * @return array{0: array<string, array<string,true>>, 1: int}
 */
function parseSection(array $lines, string $section, bool $party): array
{
    $start = null;
    $end = count($lines);
    foreach ($lines as $i => $l) {
        if ($start === null && str_starts_with($l, '### '.$section.' ')) {
            $start = $i;

            continue;
        }
        if ($start !== null && $i > $start && (str_starts_with($l, '### ') || str_starts_with($l, '## '))) {
            $end = $i;
            break;
        }
    }
    if ($start === null) {
        fwrite(STDERR, "ERROR: §{$section} not found — the section matcher is broken, not the table\n");
        exit(2);
    }

    $fromIdx = $toIdx = $partyIdx = null;
    $out = $party ? ['Station' => [], 'Server' => []] : ['*' => []];
    $rows = 0;

    for ($i = $start; $i < $end; $i++) {
        $l = $lines[$i];
        if (! str_starts_with($l, '|') || preg_match('/^\|[\s:|-]+\|$/', $l) === 1) {
            continue;
        }
        $cells = array_map('trim', explode('|', trim($l, '|')));

        if ($fromIdx === null) {
            foreach ($cells as $k => $c) {
                if (strcasecmp($c, 'From') === 0) {
                    $fromIdx = $k;
                }
                if (strcasecmp($c, 'To') === 0) {
                    $toIdx = $k;
                }
                if (strcasecmp($c, 'Effected by') === 0) {
                    $partyIdx = $k;
                }
            }

            continue;
        }

        if (! isset($cells[$toIdx], $cells[$fromIdx])) {
            continue;
        }
        $to = trim($cells[$toIdx], '`* ');
        if (preg_match('/^[A-Z][A-Za-z]+$/', $to) !== 1) {
            continue;   // `--` initial rows are not state-to-state
        }

        $bucket = '*';
        if ($party) {
            $actor = $cells[$partyIdx] ?? '';
            $bucket = str_contains($actor, 'Station') ? 'Station' : (str_contains($actor, 'Server') ? 'Server' : null);
            if ($bucket === null) {
                continue;
            }
        }

        foreach (explode(',', $cells[$fromIdx]) as $from) {
            $from = trim($from, '`* ');
            if (preg_match('/^[A-Z][A-Za-z]+$/', $from) === 1) {
                $out[$bucket][$from.'->'.$to] = true;
                $rows++;
            }
        }
    }

    return [$out, $rows];
}

$failures = [];
$summary = [];

foreach (MACHINES as $m) {
    [$spec, $rows] = parseSection($lines, $m['section'], $m['party'] ?? false);

    if ($rows === 0) {
        fwrite(STDERR, "ERROR: §{$m['section']} ({$m['name']}) parsed 0 transitions — the row matcher is broken, not the SDK\n");
        exit(2);
    }

    // A state named by the spec that this SDK's enum does not carry is reported, never dropped.
    $enum = $m['enum'];
    $known = [];
    foreach ($enum::cases() as $c) {
        $known[strtolower($c->value)] = $c;
    }

    $machine = new $m['class'];
    $party = $m['party'] ?? false;

    // Key BOTH sides by the enum CASE NAME, never by a re-cased string.
    //
    // The first run of this gate reported `Station: NotProvisioned->Booting` as refused
    // when it is allowed. The cause was here: the comparison did ucfirst(strtolower($s)),
    // which turns `NotProvisioned` into `Notprovisioned`. The enums do not share a casing
    // convention -- StationState is PascalCase (`NotProvisioned`), SessionStatus is
    // lowercase (`active`) -- so any re-casing is a guess. Resolving the spec's spelling to
    // an enum case case-insensitively, then keying on ->name, removes the whole class.
    $canon = static function (string $name) use ($known): ?string {
        return isset($known[strtolower($name)]) ? $known[strtolower($name)]->name : null;
    };

    // §2.3: "A station implements the Station rows. A server implements all of them."
    $expected = $party ? ['Station' => $spec['Station'], 'Server' => $spec['Station'] + $spec['Server']] : ['*' => $spec['*']];

    foreach ($expected as $bucket => $pairs) {
        $sdk = [];
        foreach ($enum::cases() as $f) {
            foreach ($enum::cases() as $t) {
                $ok = $party
                    ? $machine->canTransition($f, $t, $bucket === 'Station' ? EffectedBy::STATION : EffectedBy::SERVER)
                    : $machine->canTransition($f, $t);
                if ($ok) {
                    $sdk[$f->name.'->'.$t->name] = true;
                }
            }
        }

        $label = $m['name'].($party ? " ({$bucket})" : '');
        $summary[] = sprintf('  %-22s spec %2d | SDK %2d', $label, count($pairs), count($sdk));

        $specCanon = [];
        foreach (array_keys($pairs) as $k) {
            [$f, $t] = explode('->', $k);
            $cf = $canon($f);
            $ct = $canon($t);
            if ($cf === null || $ct === null) {
                $failures[] = "{$label}: {$k} names a state this SDK's enum does not carry";

                continue;
            }
            $specCanon[$cf.'->'.$ct] = $k;
            if (! isset($sdk[$cf.'->'.$ct])) {
                $failures[] = "{$label}: {$k} is in the spec and REFUSED by this SDK";
            }
        }
        foreach (array_keys($sdk) as $k) {
            if (! isset($specCanon[$k])) {
                $failures[] = "{$label}: {$k} is allowed by this SDK and in NO spec row";
            }
        }
    }
}

printf("state machines, derived from 05-state-machines.md (%s):\n", $refLabel);
foreach ($summary as $s) {
    echo $s."\n";
}
foreach (UNIMPLEMENTED as $u) {
    printf("  %-22s §%s NOT COVERED — %s\n", $u['name'], $u['section'], $u['why']);
}
printf("  machines compared: %d of %d tables in the chapter\n", count(MACHINES), count(MACHINES) + count(UNIMPLEMENTED));

// SELF-CONTROL. Plant a defect through this file's OWN parser and require it to be caught.
// A gate that reports on someone else's table must first prove it can see a change in one.
if ($selfTest) {
    // The control must prove the parser SEES the table, and it must not assume any single
    // row is the only producer of its pair. The first version of this control mutated
    // "Station confirms stop | Stopping | Completed" and asserted the pair vanished -- it
    // did not, because "Timer elapsed" produces the same pair, so the control reported a
    // blind parser when the parser was fine. It was the control that was blind.
    //
    // So: mutate EVERY data row's To cell in one section to a sentinel, and require the
    // parsed set to change. That claim holds whatever the table contains.
    [$before] = parseSection($lines, '3.3', false);

    $mutated = $lines;
    $changed = 0;
    $inSection = false;
    foreach ($mutated as $i => $l) {
        if (str_starts_with($l, '### 3.3 ')) {
            $inSection = true;

            continue;
        }
        if ($inSection && (str_starts_with($l, '### ') || str_starts_with($l, '## '))) {
            break;
        }
        if ($inSection && str_starts_with($l, '|') && preg_match('/^\|[^|]*\|([^|]*)\|([^|]*)\|/', $l, $mm) === 1) {
            $to = trim($mm[2], '`* ');
            if (preg_match('/^[A-Z][A-Za-z]+$/', $to) === 1) {
                $mutated[$i] = preg_replace('/\|([^|]*)\|([^|]*)\|/', '|$1| ZzzSentinel |', $l, 1);
                $changed++;
            }
        }
    }

    if ($changed === 0) {
        fwrite(STDERR, "SELF-TEST: nothing to mutate in §3.3 — the control is blind. Fix the control.\n");
        exit(3);
    }

    [$after] = parseSection($mutated, '3.3', false);
    if ($before['*'] == $after['*']) {
        fwrite(STDERR, "SELF-TEST: {$changed} row(s) rewritten and the parsed set did NOT change — the parser is not reading the table it claims to.\n");
        exit(3);
    }
    printf("  self-test: %d rewritten row(s) changed the parsed set — the parser reads the table  OK\n", $changed);
}

if ($failures !== []) {
    fwrite(STDERR, "\nFAIL — ".count($failures)." disagreement(s):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  {$f}\n");
    }
    fwrite(STDERR, "\nFix the SDK to match the chapter. If the SPEC is what is wrong, fix it there and\n");
    fwrite(STDERR, "re-pin .spec-ref — do not 'correct' the table here.\n");
    exit(1);
}

echo "OK — every machine agrees with its section, in both directions\n";
exit(0);
