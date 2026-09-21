<?php

declare(strict_types=1);

/**
 * Gate: a quotation this repository attributes to the spec must be IN the spec.
 *
 * WHY THIS EXISTS
 * ---------------
 * Comments in this repository argue from the specification constantly, and they
 * argue by quoting it. A quotation is the strongest form a comment can take: it
 * reads as evidence rather than as opinion, and it is also the only form that
 * can rot invisibly. An out-of-date PARAPHRASE still reads as a summary. An
 * out-of-date QUOTATION reads as the spec's own words, in quotation marks, with
 * a section number beside it, long after the spec stopped saying them.
 *
 * Nothing in either SDK could tell the difference. The registry gates compare
 * tables, the schema gates compare vendored JSON, and the doc-claims gate checks
 * claims written into a claim table. Free prose in a doc block, which is where
 * the reasoning actually lives, was unread by every one of them.
 *
 * WHAT IS PARSED RATHER THAN MATCHED
 * ----------------------------------
 * 1. COMMENTS. A source scanner tracks string, template and heredoc literals, so
 *    a double quote inside code never opens a quotation. Only comments are
 *    searched (and, for Markdown, prose outside fenced code).
 *
 *    The scanner is hand written rather than built on nikic/php-parser, which
 *    this repository already carries. Two reasons, both measured rather than
 *    preferred: the corpus is not PHP -- it is PHP, TypeScript, shell, YAML and
 *    Markdown, and a PHP parser reads one of those five -- and the reference
 *    instrument this file is a port of was validated as a character scanner, so
 *    a second implementation shape would be a second thing to trust.
 *
 * 2. LOGICAL BLOCKS. A doc block is rejoined with its leading continuation
 *    markers removed; a run of line comments is joined the same way. A quotation
 *    that wraps across comment lines is ONE quotation. Matching line by line is
 *    what produced the previous probe's 53 not-founds.
 *
 * 3. CITABLE DOCUMENT NAMES are derived from the spec tree itself, not guessed:
 *    every Markdown basename under the pinned checkout. A name the spec does not
 *    carry is not a citation.
 *
 * 4. ATTRIBUTION IS A BINDING, NOT CO-OCCURRENCE. A quotation counts as
 *    spec-attributed only when a citation token stands within GAP characters of
 *    the quotation's edge, with no other quotation and no sentence boundary
 *    between them. Citations are searched with every quotation MASKED, so a
 *    phrase ending in the word spec cannot cite itself.
 *
 *    That binding is what separates
 *
 *        (Section 5.1): "A rule requiring per-message judgement ..."      cited
 *        ... row reads "This mode exists for development ..."             cited
 *        ... turn off" (Section 5.6).                                     cited
 *
 *    from
 *
 *        reported "recoverable: identical, 0 diffs" while both were wrong  not
 *        proves "everything we vendored matches the spec"                  not
 *
 *    both of which sit in blocks that mention the spec elsewhere.
 *
 * 5. CONFORMANCE STEP NUMBERS ARE NOT SECTIONS. A conformance step is written
 *    with the section sign in these repositories exactly as a spec section is,
 *    and a step number is not a section number. A section sign carrying a
 *    conformance-case prefix is not a citation.
 *
 * WHAT IS DELIBERATELY NOT A CITATION
 * -----------------------------------
 * The words spec, specification and verbatim are NOT citations. Measured on the
 * sibling SDK, they were the whole binding for three quotations that turned out
 * to be that repository quoting its OWN console output and its own comments: a
 * workflow quoting itself, a crypto-vector script quoting its own OK line, and a
 * state-machine gate quoting a comment it had disowned. A section sign, or a
 * document name the corpus actually carries, is the only marker strong enough to
 * hold a verdict, and every true positive already has one.
 *
 * DEPENDENCIES
 * ------------
 * ext-intl, for NFKC. It is NOT in composer.json's require block because it is
 * not needed by the library, only by this gate, and this gate REFUSES a verdict
 * when it is absent rather than quietly comparing un-normalised text. ext-mbstring
 * is used for the character-count arithmetic the reference does on Python strings;
 * every offset here is a byte offset, and only the lengths are counted in
 * characters, which is what makes the GAP measurement agree with the reference on
 * prose full of em dashes. Nothing here needs vendor/autoload.php, so the gate
 * runs on a checkout that has not been composer-installed.
 *
 * This file is the examination only. scripts/check-spec-quotations.sh resolves the
 * spec checkout (clone at .spec-ref, or SPEC_REPO) and calls it.
 *
 * Usage: php scripts/check-spec-quotations.php <spec-root> [<ref-label>] [--json]
 *
 * Exit: 0 every spec-attributed quotation is in the spec; 1 at least one is not;
 *       2 the scan examined too little to return a verdict, or the built-in
 *         control did not fire.
 */

const GAP_DEFAULT = 60;      // chars allowed between citation and quote
const MINWORDS_DEFAULT = 4;  // a value like Rejected is not a quotation

$gapEnv = getenv('GAP');
$minEnv = getenv('MINWORDS');
$GAP = is_string($gapEnv) && $gapEnv !== '' ? (int) $gapEnv : GAP_DEFAULT;
$MINWORDS = is_string($minEnv) && $minEnv !== '' ? (int) $minEnv : MINWORDS_DEFAULT;

const NORMATIVE = 'spec/';

// A quotation may wrap across comment lines but not across a blank line.
const QUOTE_RE = '/"((?:[^"\n]|\n(?![ \t]*\n))*?)"|\x{201C}((?:[^\x{201D}\n]|\n(?![ \t]*\n))*?)\x{201D}/u';

const ELISION_RE = '/(*UCP)\[\s*(?:\.\.\.|\x{2026})\s*\]|\.\.\.|\x{2026}/u';
const SENTENCE_END_RE = '/(*UCP)[.!?]["\')\]]*\s/u';
const EMPHASIS_RE = '/(*UCP)\*\*(?=\S)|(?<=\S)\*\*|(?<![\w*])\*(?=\S)|(?<=\S)\*(?![\w*])/u';
const MD_LINK_RE = '/\[([^\]]+)\]\([^)]*\)/u';
const BLOCKQUOTE_RE = '/^[ \t]*>[ \t]?/mu';
const MD_ESCAPE_RE = '/\\\\([<>\[\]()*_\x{0060}#|-])/u';
const WS_RE = '/(*UCP)\s+/u';
const BLANK_RE = '/(*UCP)\A\s*\z/u';
const CONT_MARKER_RE = '/^[ \t]*\*+ ?/mu';
const LINE_MARKER_RE = '~^[ \t]*(//+|#)[ \t]?~u';
const FENCE_RE = '/^\x{60}{3}.*?^\x{60}{3}/msu';
const PARA_SEP_RE = '/(\n[ \t]*\n)/u';
const HEREDOC_OPEN_RE = '/(*UCP)<<<\s*[\'"]?([A-Za-z_]\w*)[\'"]?\r?\n/Au';
// The section sign carrying a conformance-case prefix is a step number. The
// anchor tolerates one trailing newline, which is what the reference's own
// end-of-string anchor does.
const TC_STEP_RE = '/(*UCP)TC-[A-Z]+-\d+[^.\n]{0,20}\n?$/u';

// ── comment scanning ────────────────────────────────────────────────────────

/**
 * @return list<array{kind: string, off: int, text: string}>
 */
function scanCLike(string $src, bool $php = false): array
{
    $out = [];
    $n = strlen($src);
    $i = 0;
    while ($i < $n) {
        $c = $src[$i];
        if ($c === '/' && $i + 1 < $n && $src[$i + 1] === '*') {
            $j = strpos($src, '*/', $i + 2);
            $j = $j === false ? $n : $j + 2;
            $out[] = ['kind' => 'block', 'off' => $i, 'text' => substr($src, $i, $j - $i)];
            $i = $j;
        } elseif ($c === '/' && $i + 1 < $n && $src[$i + 1] === '/') {
            $j = strpos($src, "\n", $i);
            $j = $j === false ? $n : $j;
            $out[] = ['kind' => 'line', 'off' => $i, 'text' => substr($src, $i, $j - $i)];
            $i = $j;
        } elseif ($php && $c === '#' && substr($src, $i, 2) !== '#[') {
            $j = strpos($src, "\n", $i);
            $j = $j === false ? $n : $j;
            $out[] = ['kind' => 'line', 'off' => $i, 'text' => substr($src, $i, $j - $i)];
            $i = $j;
        } elseif ($c === "'" || $c === '"' || $c === "\x60") {
            $q = $c;
            $i++;
            while ($i < $n) {
                if ($src[$i] === '\\') {
                    $i += 2;

                    continue;
                }
                if ($src[$i] === $q) {
                    $i++;

                    break;
                }
                $i++;
            }
        } elseif ($php && substr($src, $i, 3) === '<<<') {
            $m = [];
            if (preg_match(HEREDOC_OPEN_RE, $src, $m, 0, $i) === 1) {
                $after = $i + strlen($m[0]);
                $e = [];
                $endRe = '/(*UCP)^\s*'.preg_quote($m[1], '/').'\b/mu';
                if (preg_match($endRe, $src, $e, PREG_OFFSET_CAPTURE, $after) === 1) {
                    $i = $e[0][1] + strlen($e[0][0]);
                } else {
                    $i = $after;
                }
            } else {
                $i += 3;
            }
        } else {
            $i++;
        }
    }

    return $out;
}

/**
 * @return list<array{kind: string, off: int, text: string}>
 */
function scanHash(string $src): array
{
    $out = [];
    $n = strlen($src);
    $i = 0;
    while ($i < $n) {
        $c = $src[$i];
        if ($c === '#') {
            $j = strpos($src, "\n", $i);
            $j = $j === false ? $n : $j;
            $out[] = ['kind' => 'line', 'off' => $i, 'text' => substr($src, $i, $j - $i)];
            $i = $j;
        } elseif ($c === "'" || $c === '"') {
            $q = $c;
            $i++;
            while ($i < $n) {
                if ($src[$i] === '\\') {
                    $i += 2;

                    continue;
                }
                if ($src[$i] === $q) {
                    $i++;

                    break;
                }
                if ($src[$i] === "\n") {
                    break;
                }
                $i++;
            }
        } else {
            $i++;
        }
    }

    return $out;
}

/**
 * A doc block is one block; a RUN of adjacent line comments is one block.
 *
 * A run ends at the first line comment separated from the previous one by a
 * blank line or by any code. Without this, a quotation wrapped across two
 * comment lines is two half-quotations, and neither half is in the spec.
 *
 * @param  list<array{kind: string, off: int, text: string}>  $comments
 * @return list<array{off: int, body: string}>
 */
function commentBlocks(string $src, array $comments): array
{
    $out = [];
    $i = 0;
    $prevEnd = 0;
    $count = count($comments);
    while ($i < $count) {
        $head = $comments[$i];
        if ($head['kind'] === 'block') {
            $text = $head['text'];
            $body = str_ends_with($text, '*/') ? substr($text, 2, -2) : substr($text, 2);
            $out[] = ['off' => $head['off'], 'body' => (string) preg_replace(CONT_MARKER_RE, '', $body)];
            $i++;
        } else {
            $start = $head['off'];
            $parts = [];
            while ($i < $count && $comments[$i]['kind'] === 'line') {
                if ($parts !== []) {
                    $gap = substr($src, $prevEnd, $comments[$i]['off'] - $prevEnd);
                    if (substr_count($gap, "\n") > 1 || preg_match(BLANK_RE, $gap) !== 1) {
                        break;
                    }
                }
                $t = $comments[$i]['text'];
                $parts[] = (string) preg_replace(LINE_MARKER_RE, '', $t);
                $prevEnd = $comments[$i]['off'] + strlen($t);
                $i++;
            }
            $out[] = ['off' => $start, 'body' => implode("\n", $parts)];
        }
    }

    return $out;
}

/**
 * Markdown prose outside fenced code, one paragraph per block.
 *
 * @return list<array{off: int, body: string}>
 */
function markdownBlocks(string $src): array
{
    $stripped = (string) preg_replace_callback(
        FENCE_RE,
        static fn (array $m): string => str_repeat("\n", substr_count((string) $m[0], "\n")),
        $src,
    );
    $parts = preg_split(PARA_SEP_RE, $stripped, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return [];
    }
    $out = [];
    $off = 0;
    foreach ($parts as $para) {
        if (str_starts_with($para, "\n")) {
            $off += strlen($para);

            continue;
        }
        $out[] = ['off' => $off, 'body' => $para];
        $off += strlen($para);
    }

    return $out;
}

// ── normalisation ───────────────────────────────────────────────────────────

/**
 * Typography is levelled; words are not.
 *
 * Every rule here was isolated as the decisive cause of a miss on a quotation
 * that IS in the spec, by checking the span with and without it:
 *
 *   blockquote markers   a sentence wrapped inside a blockquote carries its
 *                        marker on the continuation line, so collapsing
 *                        whitespace alone yields a stray marker mid-sentence.
 *   markdown links       the spec writes a section reference as a link; prose
 *                        quoting it writes the label only.
 *   backticks            a code identifier quoted bare is the same words.
 *   double hyphen        an ASCII double hyphen standing in for an em dash.
 *   apostrophes, quotes  a span living inside a double-quoted comment must
 *                        downgrade the spec's double quotes to single ones.
 *   arrows               ASCII arrows for the Unicode ones.
 *   backslash escapes    the spec escapes angle brackets inside table cells.
 *   emphasis             a sentence quoted out of a table into running prose
 *                        legitimately drops its emphasis markers.
 *
 * Words, numbers, and every other character are untouched, so an altered or
 * invented quotation is still reported. Case is NOT folded here: MUST and must
 * are different claims; only a fragment's first character is treated leniently,
 * and that is done in the matcher, not here.
 */
function normalise(string $s): string
{
    $nf = \Normalizer::normalize($s, \Normalizer::FORM_KC);
    if ($nf !== false) {
        $s = $nf;
    }
    $s = (string) preg_replace(BLOCKQUOTE_RE, '', $s);
    $s = (string) preg_replace(MD_LINK_RE, '$1', $s);
    $s = str_replace(["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", '"'], "'", $s);
    $s = str_replace(["\u{2014}", "\u{2013}", "\u{2212}"], '-', $s);
    $s = str_replace('--', '-', $s);
    $s = str_replace("\u{2192}", '->', $s);
    $s = str_replace("\u{2190}", '<-', $s);
    $s = str_replace("\u{00A0}", ' ', $s);
    $s = str_replace("\x60", '', $s);
    $s = (string) preg_replace(MD_ESCAPE_RE, '$1', $s);
    $s = (string) preg_replace(EMPHASIS_RE, '', $s);

    return trim((string) preg_replace(WS_RE, ' ', $s));
}

/**
 * An elided quotation is fragments that must appear in order.
 *
 * Edge punctuation is trimmed from each fragment: a table row quoted as a
 * sentence ends in a full stop where the spec's own cell ends in a pipe, and
 * that is punctuation, not a different claim.
 *
 * @return list<string>
 */
function fragments(string $q): array
{
    $parts = preg_split(ELISION_RE, $q);
    if ($parts === false) {
        return [];
    }
    $out = [];
    foreach ($parts as $p) {
        $f = trim($p, " -,;:.|");
        if ($f !== '') {
            $out[] = $f;
        }
    }

    return $out;
}

/**
 * Find a fragment, forgiving ONLY its first character's case.
 *
 * A quotation spliced after a colon lowercases the sentence-initial capital:
 * where the spec opens a sentence with There, the comment that splices it after
 * a colon opens with there. That is the splice, not a different claim. Nothing
 * else is case-folded: MUST and must stay different words.
 */
function findLenient(string $text, string $frag, int $start): int
{
    if ($start > strlen($text)) {
        return -1;
    }
    $k = strpos($text, $frag, $start);
    if ($k !== false || $frag === '') {
        return $k === false ? $start : $k;
    }
    $head = mb_substr($frag, 0, 1, 'UTF-8');
    $lower = mb_strtolower($head, 'UTF-8');
    $upper = mb_strtoupper($head, 'UTF-8');
    $other = ($lower === $head && $upper !== $head) ? $upper : $lower;
    if ($other === $head) {
        return -1;
    }
    $k = strpos($text, $other.substr($frag, strlen($head)), $start);

    return $k === false ? -1 : $k;
}

/**
 * Cited document first, then the normative chapters, then everything else.
 *
 * @param  array<string, string>  $texts
 * @return list<array{0: string, 1: string}>
 */
function corpusOrder(array $texts, string $cite): array
{
    $needle = mb_strtolower($cite, 'UTF-8');
    $named = [];
    $spec = [];
    $other = [];
    foreach ($texts as $n => $t) {
        if ($needle !== '' && str_contains('/'.mb_strtolower($n, 'UTF-8'), $needle)) {
            $named[] = [$n, $t];
        } elseif (str_starts_with($n, NORMATIVE)) {
            $spec[] = [$n, $t];
        } else {
            $other[] = [$n, $t];
        }
    }

    return array_merge($named, $spec, $other);
}

/** cited | normative | elsewhere -- elsewhere means the SECTION does not carry it. */
function sourceClass(?string $where, string $cite): ?string
{
    if ($where === null) {
        return null;
    }
    if ($cite !== '' && str_contains('/'.mb_strtolower($where, 'UTF-8'), mb_strtolower($cite, 'UTF-8'))) {
        return 'cited';
    }

    return str_starts_with($where, NORMATIVE) ? 'normative' : 'elsewhere';
}

/**
 * The 40 characters standing before a citation, taken as CHARACTERS.
 *
 * Every offset in this file is a byte offset, which is what the regex engine
 * hands back. This one window is measured the way the reference measures it,
 * because the text in front of a citation is full of em dashes and a byte
 * window would be a third of the length on exactly the lines that matter.
 */
function precedingWindow(string $masked, int $start): string
{
    $from = max(0, $start - 200);
    $w = substr($masked, $from, $start - $from);
    $k = 0;
    $len = strlen($w);
    while ($k < $len && (ord($w[$k]) & 0xC0) === 0x80) {
        $k++;
    }

    return mb_substr(substr($w, $k), -40, null, 'UTF-8');
}

// ── the examination ─────────────────────────────────────────────────────────

/**
 * One block of prose, examined end to end.
 *
 * This is the whole judgement: quotations, masking, the citation binding, the
 * normalisation and the corpus search. The built-in control calls exactly this
 * function, so a control that fires is evidence about the real path.
 *
 * @param  array<string, string>  $texts
 * @return array{quotes: int, attributed: int, found: list<array{file: string, line: int, quote: string, cite: string, where: string|null, order: string|null, source: string|null}>,
 *                missing: list<array{file: string, line: int, quote: string, cite: string, where: string|null, order: string|null, source: string|null}>}
 */
function examineBlock(
    array $texts,
    string $citationRe,
    string $body,
    string $file,
    int $lineBase,
    int $gapLimit,
    int $minWords,
): array {
    $res = ['quotes' => 0, 'attributed' => 0, 'found' => [], 'missing' => []];

    $qs = [];
    $n = preg_match_all(QUOTE_RE, $body, $qs, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);
    if ($n === false || $n === 0) {
        return $res;
    }
    $res['quotes'] = $n;

    // citations are searched with every quotation masked, so a quotation can
    // never cite itself
    $masked = $body;
    foreach ($qs as $m) {
        $off = (int) $m[0][1];
        $len = strlen((string) $m[0][0]);
        $masked = substr_replace($masked, str_repeat("\0", $len), $off, $len);
    }

    $cs = [];
    $cn = preg_match_all($citationRe, $masked, $cs, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    if ($cn === false || $cn === 0) {
        return $res;
    }
    $cites = [];
    foreach ($cs as $c) {
        $cStart = (int) $c[0][1];
        if (preg_match(TC_STEP_RE, precedingWindow($masked, $cStart)) === 1) {
            continue;
        }
        $cites[] = ['text' => (string) $c[0][0], 'start' => $cStart, 'end' => $cStart + strlen((string) $c[0][0])];
    }
    if ($cites === []) {
        return $res;
    }

    foreach ($qs as $m) {
        $raw = $m[1][0] !== null ? (string) $m[1][0] : (string) $m[2][0];
        $q = normalise($raw);
        $words = $q === '' ? [] : explode(' ', $q);
        if (count($words) < $minWords) {
            continue;
        }
        $mStart = (int) $m[0][1];
        $mEnd = $mStart + strlen((string) $m[0][0]);

        $bound = null;
        foreach ($cites as $c) {
            if ($c['end'] <= $mStart) {
                $gap = substr($masked, $c['end'], $mStart - $c['end']);
            } elseif ($c['start'] >= $mEnd) {
                $gap = substr($masked, $mEnd, $c['start'] - $mEnd);
            } else {
                continue;
            }
            if (mb_strlen($gap, 'UTF-8') > $gapLimit || str_contains($gap, "\0") || preg_match(SENTENCE_END_RE, $gap) === 1) {
                continue;
            }
            $bound = trim($c['text']);

            break;
        }
        if ($bound === null) {
            continue;
        }
        $res['attributed']++;

        $line = $lineBase + substr_count(substr($body, 0, $mStart), "\n");
        $frs = array_map(normalise(...), fragments($q));
        $where = null;
        $order = null;

        // SEARCH ORDER MATTERS FOR THE ANSWER, not for the verdict. A plain sort
        // put the changelog first, so a sentence present in BOTH a chapter and
        // the changelog was reported as living in the changelog, which made 24 of
        // 146 accepted quotations look as though only a release note carried
        // them. The cited document is tried first, then the normative chapters,
        // then the rest, and the class is recorded. A quotation satisfied ONLY by
        // the third class is attributed to a section that does not contain it,
        // even though the words exist somewhere in the repository.
        $ordered = corpusOrder($texts, $bound);
        foreach ($ordered as [$name, $text]) {
            // IN ORDER first: an elision means and then, later.
            $pos = 0;
            $good = true;
            foreach ($frs as $f) {
                $k = findLenient($text, $f, $pos);
                if ($k < 0) {
                    $good = false;

                    break;
                }
                $pos = $k + strlen($f);
            }
            if ($good) {
                $where = $name;
                $order = 'in order';

                break;
            }
        }
        if ($where === null) {
            // A quotation may legitimately join two passages the chapter states
            // in the OPPOSITE order: one canonical-table test puts a counts
            // paragraph first and a preamble sentence second, and the chapter
            // carries them the other way round. Both halves are verbatim; only
            // the reading order differs. That is a hit, recorded as one found out
            // of order rather than counted silently.
            foreach ($ordered as [$name, $text]) {
                $all = true;
                foreach ($frs as $f) {
                    if (findLenient($text, $f, 0) < 0) {
                        $all = false;

                        break;
                    }
                }
                if ($all) {
                    $where = $name;
                    $order = 'out of order';

                    break;
                }
            }
        }

        $rec = [
            'file' => $file,
            'line' => $line,
            'quote' => $q,
            'cite' => $bound,
            'where' => $where,
            'order' => $order,
            'source' => sourceClass($where, $bound),
        ];
        if ($where !== null) {
            $res['found'][] = $rec;
        } else {
            $res['missing'][] = $rec;
        }
    }

    return $res;
}

// ── corpus and repository ───────────────────────────────────────────────────

/**
 * @param  list<string>  $acc
 * @return list<string>
 */
function walkMarkdown(string $root, string $rel, array $acc): array
{
    $dir = $rel === '' ? $root : $root.'/'.$rel;
    $entries = scandir($dir);
    if ($entries === false) {
        return $acc;
    }
    foreach ($entries as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $child = $rel === '' ? $name : $rel.'/'.$name;
        $full = $root.'/'.$child;
        if (is_dir($full)) {
            $acc = walkMarkdown($root, $child, $acc);
        } elseif (is_file($full) && str_ends_with(strtolower($name), '.md')) {
            $acc[] = $child;
        }
    }

    return $acc;
}

/** utf-8 only, and a file that is not utf-8 is skipped rather than mangled. */
function readUtf8(string $path): ?string
{
    if (! is_file($path)) {
        return null;
    }
    $s = @file_get_contents($path);
    if ($s === false || ! mb_check_encoding($s, 'UTF-8')) {
        return null;
    }

    return $s;
}

// ── entry point ─────────────────────────────────────────────────────────────

if (! class_exists(\Normalizer::class)) {
    fwrite(STDERR,
        "ERROR: ext-intl is not loaded, so NFKC normalisation is unavailable.\n"
        ."Comparing un-normalised text would report quotations the spec DOES carry as missing,\n"
        ."which is worse than no gate at all. Refusing a verdict; install php-intl.\n");
    exit(2);
}

$args = array_slice($argv, 1);
$asJson = in_array('--json', $args, true);
$args = array_values(array_filter($args, static fn (string $a): bool => $a !== '--json'));

$specRoot = $args[0] ?? '';
$refLabel = $args[1] ?? 'local checkout';

if ($specRoot === '' || ! is_dir($specRoot)) {
    fwrite(STDERR, "Usage: php scripts/check-spec-quotations.php <spec-root> [<ref-label>] [--json]\n");
    exit(2);
}

$repoRoot = dirname(__DIR__);

$mdPaths = walkMarkdown($specRoot, '', []);
sort($mdPaths, SORT_STRING);

// A corpus that parsed to a handful of documents is not the spec, and every
// quotation in the repository would then be not-found: a wall of false findings
// that reads exactly like a real regression. v0.42.0 carries 121.
if (count($mdPaths) < 50) {
    fwrite(STDERR, sprintf(
        "ERROR: the spec checkout at %s yielded only %d Markdown document(s).\n"
        ."That is not the specification corpus. Refusing a verdict rather than reporting every\n"
        ."quotation in this repository as missing.\n",
        $specRoot,
        count($mdPaths),
    ));
    exit(2);
}

/** @var array<string, string> $texts */
$texts = [];
foreach ($mdPaths as $rel) {
    $raw = readUtf8($specRoot.'/'.$rel);
    if ($raw === null) {
        continue;
    }
    $texts[$rel] = normalise($raw);
}

// citable document names, derived from the corpus
$docs = [];
foreach ($mdPaths as $p) {
    $docs[basename($p)] = true;
}
$docNames = array_keys($docs);
sort($docNames, SORT_STRING);
$citationRe = '/(*UCP)(?<!TC-)\x{00A7}\s*\d+(?:\.\d+)*|\b(?:'
    .implode('|', array_map(static fn (string $d): string => preg_quote($d, '/'), $docNames))
    .')\b/iu';

// ── positive control ────────────────────────────────────────────────────────
//
// A finder that finds nothing proves nothing. Before the repository is touched,
// the same examination runs over two synthetic blocks built from the corpus
// itself: one quoting a passage verbatim, one quoting the same passage with an
// alien word appended. The gate refuses a verdict unless the first is accepted
// and the second is reported. It is derived from the corpus rather than written
// down here, so it cannot go stale against a spec release.
$controlDoc = $mdPaths[0];
foreach ($mdPaths as $p) {
    if (str_starts_with($p, NORMATIVE)) {
        $controlDoc = $p;

        break;
    }
}
$controlWords = explode(' ', $texts[$controlDoc] ?? '');
$sentence = '';
for ($w = 40; $w + 12 <= count($controlWords) && $w < 540; $w++) {
    $cand = implode(' ', array_slice($controlWords, $w, 12));
    if (str_contains($cand, '|') || str_contains($cand, "\0")
        || str_contains($cand, '...') || str_contains($cand, "\u{2026}")) {
        continue;
    }
    $sentence = $cand;

    break;
}
if ($sentence === '') {
    fwrite(STDERR, "ERROR: no control passage could be taken from {$controlDoc}. Refusing a verdict.\n");
    exit(2);
}
$genuine = examineBlock($texts, $citationRe, "\u{00A7} 1.1: \"".$sentence.'"', '<control>', 1, $GAP, $MINWORDS);
$planted = examineBlock($texts, $citationRe, "\u{00A7} 1.1: \"".$sentence.' zzqxvvz"', '<control>', 1, $GAP, $MINWORDS);
if ($genuine['attributed'] !== 1 || count($genuine['found']) !== 1) {
    fwrite(STDERR, sprintf(
        "ERROR: positive control FAILED — a passage taken verbatim from %s was not accepted\n"
        ."(attributed=%d, found=%d). The scanner, the citation binding or the normaliser is\n"
        ."broken. Refusing a verdict.\n",
        $controlDoc,
        $genuine['attributed'],
        count($genuine['found']),
    ));
    exit(2);
}
if ($planted['attributed'] !== 1 || count($planted['missing']) !== 1) {
    fwrite(STDERR, sprintf(
        "ERROR: positive control FAILED — the same passage with an alien word appended was NOT\n"
        ."reported (attributed=%d, missing=%d). This gate cannot fail, so it cannot pass.\n"
        ."Refusing a verdict.\n",
        $planted['attributed'],
        count($planted['missing']),
    ));
    exit(2);
}
printf("positive control: a fabricated variant of a passage in %s was caught — OK\n", $controlDoc);

// ── the repository ──────────────────────────────────────────────────────────

$excludeEnv = getenv('EXCLUDE');
$excludeBody = is_string($excludeEnv) && $excludeEnv !== ''
    ? $excludeEnv
    : '^(dist|node_modules|vendor|src/schemas|src/test-vectors|schemas|'
        .'tests/Fixtures/test-vectors|tests/crypto/fixtures|'
        .'tests/Contract/Crypto/fixtures)/|(^|/)CHANGELOG\.md$';
// Anchored at offset zero, which is what the reference's match (rather than
// search) does: a CHANGELOG below the root is NOT excluded by it.
$excludeRe = '#^(?:'.$excludeBody.')#';

$lsFiles = shell_exec('git -C '.escapeshellarg($repoRoot).' ls-files -z');
if (! is_string($lsFiles)) {
    fwrite(STDERR, "ERROR: `git ls-files` produced nothing. Refusing a verdict over an unreadable tree.\n");
    exit(2);
}
$files = [];
foreach (explode("\0", $lsFiles) as $f) {
    if ($f !== '' && preg_match($excludeRe, $f) !== 1) {
        $files[] = $f;
    }
}

/** @var list<array{file: string, line: int, quote: string, cite: string, where: string|null, order: string|null, source: string|null}> $found */
$found = [];
/** @var list<array{file: string, line: int, quote: string, cite: string, where: string|null, order: string|null, source: string|null}> $missing */
$missing = [];
$stats = ['files' => count($files), 'blocks' => 0, 'quotes' => 0, 'attributed' => 0, 'verbatim' => 0, 'notfound' => 0];

foreach ($files as $rel) {
    $src = readUtf8($repoRoot.'/'.$rel);
    if ($src === null) {
        continue;
    }
    $dot = strrpos($rel, '.');
    $ext = $dot === false ? '' : strtolower(substr($rel, $dot));
    if (in_array($ext, ['.ts', '.mjs', '.js', '.tsx'], true)) {
        $bs = commentBlocks($src, scanCLike($src));
    } elseif ($ext === '.php') {
        $bs = commentBlocks($src, scanCLike($src, true));
    } elseif (in_array($ext, ['.yml', '.yaml', '.sh', '.bash'], true)) {
        $bs = commentBlocks($src, scanHash($src));
    } elseif ($ext === '.md') {
        $bs = markdownBlocks($src);
    } else {
        continue;
    }

    foreach ($bs as $b) {
        $stats['blocks']++;
        $r = examineBlock(
            $texts,
            $citationRe,
            $b['body'],
            $rel,
            substr_count(substr($src, 0, $b['off']), "\n") + 1,
            $GAP,
            $MINWORDS,
        );
        $stats['quotes'] += $r['quotes'];
        $stats['attributed'] += $r['attributed'];
        foreach ($r['found'] as $rec) {
            $found[] = $rec;
        }
        foreach ($r['missing'] as $rec) {
            $missing[] = $rec;
        }
    }
}

$stats['verbatim'] = count($found);
$stats['notfound'] = count($missing);

if ($asJson) {
    echo json_encode([
        'repo' => $repoRoot,
        'spec' => $specRoot,
        'gap' => $GAP,
        'minwords' => $MINWORDS,
        'stats' => $stats,
        'found' => $found,
        'missing' => $missing,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
}

$byClass = ['cited' => 0, 'normative' => 0, 'elsewhere' => 0];
foreach ($found as $f) {
    $cls = $f['source'] ?? 'elsewhere';
    if ($cls === 'cited') {
        $byClass['cited']++;
    } elseif ($cls === 'normative') {
        $byClass['normative']++;
    } else {
        $byClass['elsewhere']++;
    }
}

printf("spec %s: %d documents, %d citable names\n", $refLabel, count($mdPaths), count($docNames));
printf("  tracked files scanned   %d\n", $stats['files']);
printf("  comment blocks          %d\n", $stats['blocks']);
printf("  quotations              %d\n", $stats['quotes']);
printf("  spec-attributed         %d   (gap %d, minwords %d)\n", $stats['attributed'], $GAP, $MINWORDS);
printf("    in the cited document %d\n", $byClass['cited']);
printf("    in another chapter    %d\n", $byClass['normative']);
printf("    outside spec/ only    %d\n", $byClass['elsewhere']);
printf("  found verbatim          %d\n", $stats['verbatim']);
printf("  NOT FOUND               %d\n", $stats['notfound']);
echo "\n";

// ── non-vacuity ─────────────────────────────────────────────────────────────
//
// The verdict's denominator is the number of ATTRIBUTED quotations, not the
// number of files: a scanner that stops recognising comments, a citation regex
// that stops matching, or a masking bug that swallows every binding all leave
// this gate reporting a clean pass over nothing at all. Measured at spec
// v0.42.0: 80 attributed here, 69 in the TypeScript SDK. The floor is 40,
// roughly 60% of the smaller of the two, which leaves room for comments to be
// deleted in the ordinary course of work while still catching a collapse. Raise
// it deliberately if the corpus grows; do not lower it to make a red gate green.
if ($stats['attributed'] < 40) {
    fwrite(STDERR, sprintf(
        "ERROR: only %d spec-attributed quotation(s) were found in %d tracked file(s).\n"
        ."The comment scanner or the citation binding has probably stopped working. Refusing to\n"
        ."report a pass over a denominator this small; fix the parser in\n"
        ."scripts/check-spec-quotations.php.\n",
        $stats['attributed'],
        $stats['files'],
    ));
    exit(2);
}

if ($missing !== []) {
    fwrite(STDERR, sprintf("%d spec-attributed quotation(s) are NOT in spec %s:\n\n", count($missing), $refLabel));
    foreach ($missing as $r) {
        fwrite(STDERR, sprintf("  %s:%d\n", $r['file'], $r['line']));
        fwrite(STDERR, sprintf("    cited as  %s\n", $r['cite']));
        fwrite(STDERR, sprintf("    quoted    %s\n\n", $r['quote']));
    }
    fwrite(STDERR,
        "A quotation in quotation marks next to a section number reads as the spec's own words.\n"
        ."Either fix the text to what the spec says at this ref, or stop presenting it as a\n"
        ."quotation. If the SPEC is what changed, re-pin .spec-ref and update the comment with it.\n");
    exit(1);
}

printf("OK — all %d spec-attributed quotation(s) are in spec %s\n", $stats['attributed'], $refLabel);
exit(0);
