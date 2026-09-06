<?php

declare(strict_types=1);

/**
 * Gate: every number this package ASSERTS about itself, against the thing it is
 * an assertion about — plus every method its README demonstrates, against
 * whether that method exists.
 *
 * Prose is not executable, so "78 schema files" or "668 tests" is a claim no
 * test makes and no build breaks on. This script is the one that reads them.
 * Each entry in the claim table names a file, the exact sentence, and where the
 * true value is DERIVED from — the enums, the transition tables, the schema
 * directory, `phpunit.xml`, `phpstan.neon` — and the run fails if the two
 * disagree.
 *
 * ---
 *
 * **Why this gate exists.** `README.md` — the file Packagist renders — was
 * measured against the package it describes. Of seventeen checkable claims,
 * SEVEN were false:
 *
 * | claim | said | was |
 * |---|--:|--:|
 * | schema files | 78 | 86 |
 * | total tests | 668 | 1294 |
 * | Unit tests | 478 | 482 |
 * | Contract tests | 153 | 776 |
 * | Integration tests | 27 | 26 |
 * | crypto module | `critical message registry (20 actions)` | the class was retired |
 * | state machines | five, named | six exist; `Station` was never listed |
 *
 * and the usage example did not run at all: `SessionTransitions::canTransition()`
 * was shown as a static call taking strings when it is an instance method taking
 * `SessionStatus` enums, and `SessionTransitions::timeout()` has never existed —
 * the method is `getTimeout()`. Seven CI jobs were green throughout, because not
 * one of them read `README.md`.
 *
 * **This is NOT the shape the TypeScript sibling had.** That README asserted a
 * protocol version twenty-six minors stale; this one asserts no version at all —
 * measured, 0 semver-shaped strings in 128 lines — and rots on counts and on API
 * drift instead. The two gates therefore check different things, and porting
 * either one would have checked the wrong file for the wrong property.
 *
 * **Why the test counts are gone rather than gated.** They changed on every
 * commit that added a test. A number that has to be re-derived by hand on every
 * push is not documentation, and a gate demanding it is a tax people route
 * around. The suite NAMES and their count are structural, come from
 * `phpunit.xml`, and are gated. That is the whole difference: gate what changes
 * when the spec or the design changes, delete what changes when anyone writes a
 * test.
 *
 * **A claim that matches zero times is a FAILURE, not a pass.** A pattern that
 * stops matching means the sentence was reworded or deleted, and a gate that
 * silently checks nothing is the exact failure this repository has shipped
 * before: `0.14.0` released with two gate scripts that had never once executed,
 * and the conformance-corpus gate reported `OK` through twelve minors of drift.
 * Exactly-once is the assertion.
 *
 * Usage: php scripts/check-doc-claims.php
 *        (no spec checkout, no network — every value is derived from this repo)
 */

require __DIR__.'/../vendor/autoload.php';

use Ospp\Protocol\Actions\OsppAction;
use Ospp\Protocol\Crypto\MessageSigningRegistry;
use Ospp\Protocol\Enums\ConfigurationKey;
use Ospp\Protocol\Enums\OsppErrorCode;
use Ospp\Protocol\Enums\SessionStatus;
use Ospp\Protocol\StateMachines\SessionTransitions;

$root = dirname(__DIR__);

// ── derivations ────────────────────────────────────────────────────────────

/** Every `*.json` under a directory, recursively. */
function countJson(string $dir): int
{
    $n = 0;
    /** @var SplFileInfo $f */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->isFile() && str_ends_with($f->getFilename(), '.json')) {
            ++$n;
        }
    }

    return $n;
}

/** Every `*.json` under schemas/, recursively. */
function countSchemas(string $dir): int
{
    return countJson($dir);
}

/**
 * The places schemas actually live, as the README names them: each immediate
 * subdirectory of schemas/ that contains one, plus `the root` when loose files
 * sit directly in schemas/.
 *
 * The root entry is not decoration. `provisioning-request` and
 * `provisioning-response` sit there and in no subdirectory, so a description
 * reading "(ble, common, mqtt)" omits two files while its count includes them —
 * which is how a total and a list can both be present and still disagree.
 *
 * @return list<string>
 */
function schemaLocations(string $dir): array
{
    $out = [];
    if (glob($dir.'/*.json') !== []) {
        $out[] = 'the root';
    }
    foreach (glob($dir.'/*', GLOB_ONLYDIR) ?: [] as $sub) {
        if (countSchemas($sub) > 0) {
            $out[] = basename($sub);
        }
    }
    sort($out);

    return $out;
}

/**
 * Every state machine, by short name, with the size of its state set.
 *
 * Read off `src/StateMachines/` rather than from a list here, so a machine
 * ADDED to the package cannot be missing from the README without this failing —
 * which is precisely what happened to `Station`.
 *
 * @return array<string, int>
 */
function stateMachines(string $root): array
{
    $out = [];
    foreach (glob($root.'/src/StateMachines/*Transitions.php') ?: [] as $file) {
        $short = str_replace('Transitions', '', basename($file, '.php'));
        $class = 'Ospp\\Protocol\\StateMachines\\'.$short.'Transitions';
        if (! class_exists($class)) {
            continue;
        }
        $states = [];
        foreach ((new ReflectionClass($class))->getConstants() as $name => $table) {
            if (! str_contains($name, 'TRANSITIONS') || ! is_array($table)) {
                continue;
            }
            // Array keys are int|string by construction, and every transition
            // target in this package is the backed string value, not the enum
            // case — measured, not assumed. A table that starts storing cases
            // would make these casts wrong rather than merely redundant, so the
            // state counts this feeds are what would catch it.
            foreach ($table as $from => $tos) {
                $states[(string) $from] = true;
                foreach ((array) $tos as $to) {
                    if ($to instanceof BackedEnum) {
                        $states[(string) $to->value] = true;
                    } elseif (is_scalar($to)) {
                        $states[(string) $to] = true;
                    }
                }
            }
        }
        $out[$short] = count($states);
    }
    ksort($out);

    return $out;
}

/** @return list<string> the testsuite names declared in phpunit.xml, in file order */
function testSuites(string $root): array
{
    $xml = simplexml_load_file($root.'/phpunit.xml');
    if ($xml === false) {
        fwrite(STDERR, "ERROR: cannot parse phpunit.xml\n");
        exit(1);
    }
    $names = [];
    foreach ($xml->testsuites->testsuite as $s) {
        $names[] = (string) $s['name'];
    }

    return $names;
}

/** The longest corrective-action cell, in code points — Appendix C bounds it at 500. */
function longestAction(): int
{
    $max = 0;
    foreach (OsppErrorCode::cases() as $case) {
        $max = max($max, mb_strlen($case->recommendedAction()));
    }

    return $max;
}

/** How many registry codes fall in the `Nxxx` band. */
function codesInBand(int $band): int
{
    $n = 0;
    foreach (OsppErrorCode::cases() as $case) {
        if (intdiv((int) $case->value, 1000) === $band) {
            ++$n;
        }
    }

    return $n;
}

// ── the comparator ─────────────────────────────────────────────────────────

/**
 * Check one claim against one file's text. Returns null when the claim holds.
 *
 * `$asSet` compares order-free, for claims whose prose is a list.
 */
function checkClaim(string $pattern, string $text, string $expected, string $label, string $from, bool $asSet = false): ?string
{
    $n = preg_match_all($pattern, $text, $all);

    if ($n === false) {
        return "{$label}: the pattern {$pattern} is not a valid regex — this claim is unchecked";
    }
    if ($n !== 1) {
        return "{$label}: expected exactly one match for {$pattern} — found {$n}. ".($n === 0
            ? 'The sentence was reworded or removed; this gate can no longer see it. Restore the wording '
                .'or update the pattern in scripts/check-doc-claims.php — do not leave the claim unchecked.'
            : 'Ambiguous: the same claim is made in more than one place and this gate cannot tell which '
                .'is authoritative.');
    }

    $found = $all[1][0];
    $norm = static function (string $s): string {
        $parts = array_values(array_filter(array_map('trim', explode(',', $s)), static fn ($x) => $x !== ''));
        sort($parts);

        return implode(', ', $parts);
    };
    [$a, $b] = $asSet ? [$norm($found), $norm($expected)] : [$found, $expected];

    if ($a !== $b) {
        return "{$label}: says \"{$found}\", derived value is \"{$expected}\"  (from {$from})";
    }

    return null;
}

// ── POSITIVE CONTROL, before any negative result is believed ────────────────
//
// Every result below is a report of ABSENCE — "nothing disagrees" — and a
// comparator that has stopped comparing produces that report, in the same
// words, with exit 0. So the comparator is run first over synthetic text whose
// answer is known: a claim that must pass, a wrong value that must be caught, a
// sentence that is missing, one that is duplicated, and a list compared as a
// set in both directions. If the instrument cannot fail on demand, its silence
// about the real files means nothing.

$controlFailures = [];
$P = '/\*\*(\d+) widgets\*\*/';
if (checkClaim($P, 'it has **7 widgets** in total', '7', 'c', 'x') !== null) {
    $controlFailures[] = 'a claim that AGREES was reported as a problem';
}
if (checkClaim($P, 'it has **6 widgets** in total', '7', 'c', 'x') === null) {
    $controlFailures[] = 'a planted wrong number was NOT caught';
}
if (checkClaim($P, 'the sentence is gone', '7', 'c', 'x') === null) {
    $controlFailures[] = 'a MISSING claim was treated as passing';
}
if (checkClaim($P, '**7 widgets** and again **7 widgets**', '7', 'c', 'x') === null) {
    $controlFailures[] = 'a DUPLICATED claim was treated as passing';
}
if (checkClaim('/\(([^)]*)\)/', '(b, a)', 'a, b', 'c', 'x', true) !== null) {
    $controlFailures[] = 'an order-free list comparison rejected a matching set';
}
if (checkClaim('/\(([^)]*)\)/', '(a, c)', 'a, b', 'c', 'x', true) === null) {
    $controlFailures[] = 'an order-free list comparison accepted a differing set';
}

if ($controlFailures !== []) {
    fwrite(STDERR, "ERROR: positive control FAILED — the comparator does not work:\n");
    foreach ($controlFailures as $f) {
        fwrite(STDERR, "  - {$f}\n");
    }
    fwrite(STDERR, "\nRefusing to report anything about the real files. A gate that cannot fail on a planted\n"
        ."defect proves nothing when it passes.\n");
    exit(1);
}
echo "positive control: comparator caught a wrong value, a missing claim, a duplicated claim\n";
echo "                  and a differing set, and passed a correct one — OK\n";

// ── the claims ─────────────────────────────────────────────────────────────

$readme = (string) file_get_contents($root.'/README.md');
$errorSrc = (string) file_get_contents($root.'/src/Enums/OsppErrorCode.php');

$machines = stateMachines($root);
$suites = testSuites($root);
$schemaDir = $root.'/schemas';

$phpstan = (string) file_get_contents($root.'/phpstan.neon');
preg_match('/^\s*level:\s*(\d+)\s*$/m', $phpstan, $lvl);
$phpstanLevel = $lvl[1] ?? '(phpstan.neon has no `level:` line)';

$sessions = new SessionTransitions();

/** @var list<array{0:string,1:string,2:string,3:string,4:string,5?:bool}> $claims */
$claims = [
    ['README.md', 'error codes', '/OsppErrorCode \((\d+) codes\)/',
        (string) count(OsppErrorCode::cases()), 'count(OsppErrorCode::cases())'],
    ['README.md', 'configuration keys', '/ConfigurationKey \((\d+) keys with metadata\)/',
        (string) count(ConfigurationKey::cases()), 'count(ConfigurationKey::cases())'],
    ['README.md', 'structural exemptions', '/MessageSigningRegistry \((\d+) structural signing exemptions\)/',
        (string) count(MessageSigningRegistry::allStructuralExemptions()), 'MessageSigningRegistry::allStructuralExemptions()'],
    ['README.md', 'schema files', '/(\d+) schema files \(/',
        (string) countSchemas($schemaDir), 'schemas/**/*.json'],
    ['README.md', 'schema locations', '/\d+ schema files \(([^)]*)\)/',
        implode(', ', schemaLocations($schemaDir)), 'directories under schemas/ that contain one', true],
    ['README.md', 'actions (total)', '/all (\d+) protocol actions/',
        (string) count(OsppAction::all()), 'count(OsppAction::all())'],
    ['README.md', 'actions (MQTT)', '/all \d+ protocol actions \((\d+) MQTT/',
        (string) count(OsppAction::mqttActions()), 'count(OsppAction::mqttActions())'],
    ['README.md', 'actions (API-only)', '/all \d+ protocol actions \(\d+ MQTT \+ (\d+) API-only\)/',
        (string) count(OsppAction::apiOnlyActions()), 'count(OsppAction::apiOnlyActions())'],
    ['README.md', 'test suites (count)', '/^\s*(\d+) test suites:/m',
        (string) count($suites), 'phpunit.xml <testsuite> elements'],
    ['README.md', 'phpstan level', '/phpstan analyse --level=(\d+)/',
        (string) $phpstanLevel, 'phpstan.neon `level:`'],
    ['README.md', 'session timeout example', '/getTimeout\(SessionStatus::ACTIVE\); \/\/ (\d+)/',
        (string) $sessions->getTimeout(SessionStatus::ACTIVE), 'SessionTransitions::getTimeout(ACTIVE)'],

    // The corpus gate's own scope note. It justifies diffing the whole directory
    // by asserting the vendored set is complete, and it carried a spec version
    // four minors stale next to these two numbers. The numbers are derivable
    // here without a spec checkout — the count of what IS vendored — so they
    // stay and are checked. The spec-side count is not derivable without a
    // clone, and was dropped rather than restated.
    ['scripts/check-vector-corpus.sh', 'vendored valid vectors', '/\((\d+) valid \+ \d+ invalid vendored here\)/',
        (string) countJson($root.'/tests/Fixtures/test-vectors/valid'), 'tests/Fixtures/test-vectors/valid/**/*.json'],
    ['scripts/check-vector-corpus.sh', 'vendored invalid vectors', '/\(\d+ valid \+ (\d+) invalid vendored here\)/',
        (string) countJson($root.'/tests/Fixtures/test-vectors/invalid'), 'tests/Fixtures/test-vectors/invalid/**/*.json'],

    ['src/Enums/OsppErrorCode.php', 'longest action cell', '/longest (\d+) of 500/',
        (string) longestAction(), 'max mb_strlen over recommendedAction()'],
    ['src/Enums/OsppErrorCode.php', '1xxx band size', '/1xxx - Transport Errors \((\d+) codes\)/',
        (string) codesInBand(1), 'codes with 1000 <= value < 2000'],
    ['src/Enums/OsppErrorCode.php', '5xxx band size', '/5xxx - Station Hardware & Software Errors \((\d+) codes\)/',
        (string) codesInBand(5), 'codes with 5000 <= value < 6000'],

    // The registry's own total, in the three places this file states it. It went
    // stale by one the day 5113 OUTCOME_INDETERMINATE landed, and the header
    // docblock had said in the SAME SENTENCE that the total 'is now asserted
    // against the spec ... rather than restated here' while restating it. The band
    // sizes above were gated and correct; the total was not gated and was wrong.
    // The history in that docblock -- '114 -> 116 ... then -> 118' -- is a record of
    // moves and is deliberately NOT matched by these patterns.
    ['src/Enums/OsppErrorCode.php', 'registry total (header)', '/(\d+) standard error codes across 6 categories/',
        (string) count(OsppErrorCode::cases()), 'count(OsppErrorCode::cases())'],
    ['src/Enums/OsppErrorCode.php', 'registry total (transcribed)', '/All (\d+) registry codes are transcribed/',
        (string) count(OsppErrorCode::cases()), 'count(OsppErrorCode::cases())'],
    ['src/Enums/OsppErrorCode.php', 'registry total (actions numerator)', '/Recommended Action for (\d+) of \d+ rows/',
        (string) count(OsppErrorCode::cases()), 'count(OsppErrorCode::cases())'],
    ['src/Enums/OsppErrorCode.php', 'registry total (actions denominator)', '/Recommended Action for \d+ of (\d+) rows/',
        (string) count(OsppErrorCode::cases()), 'count(OsppErrorCode::cases())'],
];

// Every state machine on disk must be named in the README with its state count.
// Generated from the derivation rather than listed, so a machine ADDED to the
// package cannot go unmentioned — `Station` had been exported for some time and
// the sentence still named five.
foreach ($machines as $name => $states) {
    $claims[] = ['README.md', "state machine {$name}", '/'.preg_quote($name, '/').' \((\d+) states\)/',
        (string) $states, "state set of {$name}Transitions"];
}

// Every suite in phpunit.xml must appear as a row in the README's suite table.
foreach ($suites as $suite) {
    $claims[] = ['README.md', "suite row {$suite}", '/│ ('.preg_quote($suite, '/').')\s*│/',
        $suite, 'phpunit.xml <testsuite name>'];
}

$texts = [
    'README.md' => $readme,
    'src/Enums/OsppErrorCode.php' => $errorSrc,
    'scripts/check-vector-corpus.sh' => (string) file_get_contents($root.'/scripts/check-vector-corpus.sh'),
];

/** @var list<string> $problems */
$problems = [];
foreach ($claims as $c) {
    $p = checkClaim($c[2], $texts[$c[0]], $c[3], $c[0].' — '.$c[1], $c[4], $c[5] ?? false);
    if ($p !== null) {
        $problems[] = $p;
    }
}

// ── the README's worked example must be callable ───────────────────────────
//
// The numbers above would all have passed on a README whose example fatals, and
// that is the state this one was in: two calls shown static-with-strings against
// an instance API taking enums, one of them naming a method that does not exist.
// Reflection is the derivation — the same one a reader performs by running it.

preg_match_all('/([A-Z][A-Za-z0-9]*)::([a-z][A-Za-z0-9]*)\(/', $readme, $calls, PREG_SET_ORDER);
$seen = [];
$resolved = 0;
foreach ($calls as [, $class, $method]) {
    if (isset($seen["{$class}::{$method}"])) {
        continue;
    }
    $seen["{$class}::{$method}"] = true;

    // Resolve the short name through the README's own `use` lines — the same
    // information a reader copying the snippet has.
    if (preg_match('/^\s*use\s+([A-Za-z0-9_\\\\]*\\\\'.preg_quote($class, '/').');\s*$/m', $readme, $u) !== 1) {
        $problems[] = "README.md — example: `{$class}::{$method}()` is shown but no `use` line in the README "
            .'names the class, so a reader cannot resolve it';

        continue;
    }
    $fqcn = $u[1];
    ++$resolved;

    if (! class_exists($fqcn) && ! enum_exists($fqcn)) {
        $problems[] = "README.md — example: class {$fqcn} does not exist";

        continue;
    }
    if (! method_exists($fqcn, $method)) {
        $problems[] = "README.md — example: {$fqcn}::{$method}() does not exist; the example fatals";

        continue;
    }
    if (! (new ReflectionMethod($fqcn, $method))->isStatic()) {
        $problems[] = "README.md — example: {$fqcn}::{$method}() is shown as a static call but is an "
            .'instance method; the example fatals';
    }
}

// A zero here would mean the call-site scanner matched nothing and the whole
// check above was vacuous — the same silent pass the claim table refuses.
if ($resolved === 0) {
    $problems[] = 'README.md — example: no `Class::method()` call site resolved through a `use` line. '
        .'The scanner in scripts/check-doc-claims.php matched nothing and checked nothing.';
}

// ── report ─────────────────────────────────────────────────────────────────

$files = array_values(array_unique(array_map(static fn ($c) => $c[0], $claims)));
echo 'checked '.count($claims).' claims across '.count($files).' files: '.implode(', ', $files)."\n";
echo '  plus '.$resolved." resolved `Class::method()` call sites in the README example\n";
foreach ($claims as $c) {
    printf("  %-34s derived = %s\n", $c[1], $c[3]);
}

if ($problems !== []) {
    fwrite(STDERR, "\nFALSE CLAIMS — ".count($problems).' of '.(count($claims) + $resolved).":\n\n");
    foreach ($problems as $p) {
        fwrite(STDERR, "  {$p}\n");
    }
    fwrite(STDERR, "\nFix: change the prose to the derived value. If the DERIVED value is what is wrong, the\n"
        ."defect is in the code, not in the sentence — fix it there.\n");
    exit(1);
}

echo 'OK — all '.count($claims).' documented claims and '.$resolved
    ." example call sites agree with what this package actually contains\n";
