<?php

declare(strict_types=1);

/**
 * Gate: every registry code has a corrective action, and the action is not a placeholder.
 *
 * `OsppErrorCode::recommendedAction()` answered ELEVEN of the 118 registry codes
 * before 0.28.0 and null for the other 107. That was once read as the registry
 * being incomplete. It is not: `07-errors.md` §3 gives a *Recommended Action* for
 * 118 of 118 rows and no cell is empty. The hole was on this side, and nothing
 * could see it — `check-error-registry.php` compares `errorText`, `severity` and
 * `recoverable` and stops there, so 107 nulls sat behind a green gate.
 *
 * ---
 *
 * **WHY THIS GATE IS NOT A DIFF, AND MUST NOT BE ONE.**
 *
 * The obvious check — compare each transcription to its registry cell — is the one
 * §1.4 expressly forbids:
 *
 * > That equality is on the **corrective action, not on the bytes** […] Byte-identity
 * > is not achievable in any case, since translation is expressly permitted, so a
 * > conformance test **MUST NOT** assert it.
 *
 * A gate built as a diff would therefore violate the section it exists to enforce,
 * and it would be wrong in practice as well as on paper. Both halves of that were
 * measured on the eleven arms that already existed, and they came out opposite ways:
 *
 *   - `4020` had been reworded to fit the 500-char bound and still said exactly what
 *     the cell says. §1.4 permits precisely that. A diff would have failed it — a
 *     false red on a conforming value.
 *   - `4010` had NOT stayed equivalent. The cell says an absent `details.phase` means
 *     `retry` on REST but `renewal` on SignCertificate [MSG-022]; this SDK said
 *     `retry` unconditionally, which is the opposite recovery on the renewal path
 *     (`renewal` regenerates the keypair, `retry` must not). A diff would have caught
 *     it — for the wrong reason, indistinguishably from the false red above.
 *
 * A gate that cannot tell those two apart is not a gate. So this one asserts only
 * properties that SURVIVE A TRANSLATION, which is the question §1.4 leaves open:
 * what is mechanically checkable, if not identity?
 *
 *   1. COVERAGE       — every §3 code has a non-empty action. The load-bearing one.
 *   2. NO ORPHANS     — no action for a code §3 does not list.
 *   3. WIRE BOUND     — 1..500, the `recommendedAction` bound of Appendix C.
 *   4. DISTINCTNESS   — no two codes share one string. This is how the substitution
 *                       §1.4 forbids actually presents: a generic value derived from
 *                       `severity` or `recoverable` collapses many codes onto few
 *                       strings. Distinctness catches it without reading the prose.
 *   5. NO PLACEHOLDER — the exact form §1.4 names as non-conforming.
 *   6. DISCRIMINATOR  — where the cell names a `details.<member>`, the action names
 *                       the same member. §1.4 requires a branching entry to name the
 *                       member that selects the branch and be "emitted in full". A
 *                       JSON member name is not translatable, so requiring it is not
 *                       requiring content.
 *   7. CODE REFS      — a four-digit code the cell cites is a normative cross-
 *                       reference to another registry row; it survives translation
 *                       for the same reason.
 *   8. PARTIES        — where the cell addresses N parties (`Station: … Server: …`),
 *                       the action still addresses N. §1.4: the value "MUST preserve
 *                       the part addressed to the receiver and MAY carry the rest". A
 *                       library cannot know which party is the receiver, so the only
 *                       way it can guarantee the receiver's part is present is to
 *                       carry every part.
 *
 *                       This counts ADDRESSED SEGMENTS and does not read the labels,
 *                       and the difference is not pedantry — it was measured. The
 *                       first draft required the literal word: `Station:` in the cell
 *                       had to be `Station:` in the action. Run against a Romanian
 *                       rendering of `4018` that keeps every protocol token, that
 *                       draft FAILED it — which is a conformance test rejecting a
 *                       translation, exactly the thing §1.4 forbids, arriving through
 *                       a check that never mentions bytes. Counting segments passes
 *                       the same translation and still fails when a part is dropped.
 *                       A label is prose; that a part is addressed at all is structure.
 *
 * None of the eight compares an action to a cell. Proven, not asserted: rewriting an
 * arm's prose end to end while keeping its tokens and party labels leaves this gate
 * green — see `tests/Contract/RecommendedActionGateTest.php`, which performs exactly
 * that rewrite and would fail if this gate had a diff hidden in it.
 *
 * This file is the comparison only. `scripts/check-recommended-action.sh` resolves
 * the spec checkout (clone at `.spec-ref`, or `SPEC_REPO`) and calls it.
 *
 * Usage: php scripts/check-recommended-action.php <spec-root> [<ref-label>]
 */

require __DIR__.'/../vendor/autoload.php';

use Ospp\Protocol\Enums\OsppErrorCode;

$specRoot = $argv[1] ?? null;
$refLabel = $argv[2] ?? 'local checkout';

if ($specRoot === null || ! is_dir($specRoot)) {
    fwrite(STDERR, "Usage: php scripts/check-recommended-action.php <spec-root> [<ref-label>]\n");
    exit(1);
}

$registryPath = $specRoot.'/spec/07-errors.md';
$md = @file_get_contents($registryPath);

if ($md === false) {
    fwrite(STDERR, "ERROR: cannot read {$registryPath}\n");
    exit(1);
}

/**
 * A §3 registry row, captured through the *Recommended Action* column.
 *
 * The first four columns are matched exactly as `check-error-registry.php` matches
 * them — the `true|false` in column 4 is what tells a §3 row from Appendix A's
 * quick-reference row, whose fourth column is a category letter. The greedy `(.*)`
 * then runs to the LAST pipe on the line, so the trailing field is the action.
 *
 * That is deliberate rather than an `explode('|')`: a *Description* cell may contain
 * a pipe inside a code span, which would shift every field after it. No *Recommended
 * Action* cell contains one — verified across all 118 at the pinned ref — so anchoring on
 * the last pipe is correct whatever the earlier columns hold.
 */
const ROW = '/^\|\s*(\d{4})\s*\|\s*`([A-Z_]+)`\s*\|\s*(\w+)\s*\|\s*(?:true|false)\s*\|(.*)\|\s*$/';

/** The floor below which a parse is treated as broken rather than as a small registry. */
const MIN_ROWS = 100;

/** Appendix C — `recommendedAction`: `"minLength": 1, "maxLength": 500`. */
const MIN_LEN = 1;
const MAX_LEN = 500;

/**
 * The party vocabulary §1.4 has in mind when it says an entry may address "more than
 * one party". Enumerated from the registry rather than guessed: these are every
 * `Word:` label that occurs in an action cell at the pinned ref.
 *
 * Used on the CELL side only, where the language is known to be the spec's own.
 */
const PARTY_VOCAB = 'Station|Server|Operator|Sender|Receiver|App|Web app|Web|Browser|User|Client';

/**
 * One ADDRESSED SEGMENT in a registry cell. `Server/Operator:` is one segment naming
 * two parties, not two segments — 5017 and 5024 are the rows that make the difference,
 * and counting names there would demand a part the cell never separated.
 */
const CELL_SEGMENT = '/\*{0,2}(?:'.PARTY_VOCAB.')(?:\s*\/\s*(?:'.PARTY_VOCAB.'))*\*{0,2}\s*:/';

/**
 * The SHAPE of an addressed segment, for the action side, which may be in any
 * language: a capitalised label (optionally slash-joined, optionally two words) and a
 * colon. Code spans are removed first so `type: "OfflinePassRejected"` is not read as
 * an address.
 */
const ACTION_SEGMENT = '/(?:^|(?<=[.!?;)\s]))\*{0,2}[A-Z][\w-]*(?:\s*\/\s*[A-Z][\w-]*)*(?: [a-z][\w-]*)?\*{0,2}\s*:/';

/** @var array<int, array{text: string, action: string}> $spec */
$spec = [];

foreach (preg_split('/\r?\n/', $md) as $line) {
    if (preg_match(ROW, $line, $m) !== 1) {
        continue;
    }

    $code = (int) $m[1];
    $rest = $m[4];

    // Everything after the last remaining pipe is the action cell.
    $cut = strrpos($rest, '|');
    $action = trim($cut === false ? $rest : substr($rest, $cut + 1));

    if (isset($spec[$code])) {
        fwrite(STDERR, "ERROR: spec 07-errors.md lists code {$code} more than once\n");
        exit(1);
    }

    $spec[$code] = ['text' => $m[2], 'action' => $action];
}

// ---------------------------------------------------------------------------
// ANTI-VACUITY. Before any comparison, because every one of the eight checks
// below passes trivially over an empty set. A reformatted table, a moved file or
// a tightened regex must land as a failure, never as "all 0 codes agree".
// ---------------------------------------------------------------------------

if (count($spec) < MIN_ROWS) {
    fwrite(STDERR, 'ERROR: parsed only '.count($spec)." rows from §3 of {$registryPath}; expected at least ".MIN_ROWS."\n");
    fwrite(STDERR, "This is a broken parse, not a small registry. The gate reports nothing rather than a pass.\n");
    exit(1);
}

$empty = array_keys(array_filter($spec, static fn (array $r): bool => $r['action'] === ''));
if ($empty !== []) {
    fwrite(STDERR, 'ERROR: §3 has an EMPTY Recommended Action cell for: '.implode(', ', $empty)."\n");
    exit(1);
}

// recommendedAction() returns a non-nullable string: its match is exhaustive over
// the enum, so a case with no arm raises \UnhandledMatchError here rather than
// returning null. That is the intended failure — it names the case — but it must
// not read as the gate itself crashing, so it is caught and reported as COVERAGE
// like any other missing action.
$sdk = [];
foreach (OsppErrorCode::cases() as $case) {
    try {
        $sdk[$case->value] = $case->recommendedAction();
    } catch (\UnhandledMatchError) {
        // Left out of $sdk; the coverage loop below reports it against the spec row.
    }
}

if ($sdk === []) {
    fwrite(STDERR, "ERROR: the SDK produced no recommendedAction at all — refusing to report a pass over an empty set\n");
    exit(1);
}

ksort($spec);

$fail = [];

/** @param non-empty-string $rule */
$report = static function (string $rule, int $code, string $detail) use (&$fail): void {
    $fail[] = sprintf('%-14s %4d  %s', $rule, $code, $detail);
};

// ---------------------------------------------------------------------------
// 1 COVERAGE · 3 WIRE BOUND · 5 NO PLACEHOLDER · 6 DISCRIMINATOR · 7 CODE REFS · 8 PARTIES
// ---------------------------------------------------------------------------

$covered = 0;

foreach ($spec as $code => $row) {
    $action = $sdk[$code] ?? null;

    if ($action === null || trim($action) === '') {
        $report('COVERAGE', $code, $row['text'].' — §3 gives an action, this SDK gives none');

        continue;
    }
    $covered++;

    $len = mb_strlen($action);
    if ($len < MIN_LEN || $len > MAX_LEN) {
        $report('WIRE-BOUND', $code, $row['text']." — {$len} characters, outside Appendix C's ".MIN_LEN.'..'.MAX_LEN);
    }

    // The exact string §1.4 names as non-conforming, and anything that is only it.
    if (preg_match('/^\W*review the error details and take corrective action\W*$/i', $action) === 1) {
        $report('PLACEHOLDER', $code, $row['text'].' — §1.4 names this exact string as not conforming');
    }

    // 6 — every `details.<member>` the cell names must survive into the action.
    preg_match_all('/`(details\.[A-Za-z][A-Za-z0-9_]*)`/', $row['action'], $dm);
    foreach (array_unique($dm[1]) as $member) {
        if (! str_contains($action, $member)) {
            $report('DISCRIMINATOR', $code, $row['text']." — cell selects a branch on `{$member}`; the action does not name it");
        }
    }

    // 7 — a four-digit code the cell cites is a cross-reference to another row.
    preg_match_all('/`(\d{4})`/', $row['action'], $cm);
    foreach (array_unique($cm[1]) as $ref) {
        if (! str_contains($action, $ref)) {
            $report('CODE-REF', $code, $row['text']." — cell cites code `{$ref}`; the action does not");
        }
    }

    // 8 — the addressing structure, counted rather than named, so a translated label
    // still counts and a dropped part still does not.
    $wantSegments = preg_match_all(CELL_SEGMENT, $row['action']);
    if ($wantSegments > 0) {
        $haveSegments = preg_match_all(ACTION_SEGMENT, preg_replace('/`[^`]*`/', ' ', $action) ?? '');
        if ($haveSegments < $wantSegments) {
            $report('PARTY', $code, $row['text']." — cell addresses {$wantSegments} part(s), the action addresses {$haveSegments}");
        }
    }
}

// ---------------------------------------------------------------------------
// 2 NO ORPHANS
// ---------------------------------------------------------------------------

foreach (array_keys($sdk) as $code) {
    if (! isset($spec[$code])) {
        $report('ORPHAN', $code, 'this SDK carries an action for a code §3 does not list');
    }
}

// ---------------------------------------------------------------------------
// 4 DISTINCTNESS — how a generic substitution presents, without reading the prose.
// ---------------------------------------------------------------------------

$seen = [];
foreach ($sdk as $code => $action) {
    $seen[$action][] = $code;
}
foreach ($seen as $action => $codes) {
    if (count($codes) > 1) {
        $report('DISTINCT', $codes[0], 'shares one action string with '.implode(', ', \array_slice($codes, 1)).' — §1.4 forbids a value derived from severity/recoverable, and that is how one presents');
    }
}

// ---------------------------------------------------------------------------

$total = count($spec);

if ($fail !== []) {
    fwrite(STDERR, "spec {$refLabel}: {$total} codes    SDK: covered {$covered}/{$total}\n\n");
    fwrite(STDERR, 'FAIL — '.count($fail)." finding(s):\n");
    foreach ($fail as $f) {
        fwrite(STDERR, "  {$f}\n");
    }
    fwrite(STDERR, "\nThis gate never compares an action to its registry cell — §1.4 forbids asserting\n");
    fwrite(STDERR, "byte-identity, because translation and shortening are both permitted. Fix a\n");
    fwrite(STDERR, "COVERAGE finding by transcribing the cell; fix the others by keeping the tokens\n");
    fwrite(STDERR, "and the addressing the cell carries. Rewording is allowed and always was.\n");
    exit(1);
}

echo "spec {$refLabel}: {$total} codes    SDK: covered {$covered}/{$total}\n";
echo "OK — all {$total} codes carry a distinct, bounded, structure-preserving recommendedAction\n";
exit(0);
