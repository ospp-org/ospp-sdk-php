<?php

declare(strict_types=1);

/**
 * Gate: no assertion in the PHPUnit corpus may be inert.
 *
 * WHY THIS EXISTS
 * ---------------
 * The sibling TypeScript SDK ran this census first and found fifteen assertions
 * whose two sides both fold at compile time -- thirteen more than were known.
 * The shape that prompted it there was
 *
 *     it('15 + 20 + 20 + 20 + 34 + 9 = 118', () => {
 *       expect(15 + 20 + 20 + 20 + 34 + 9).toBe(118);
 *     });
 *
 * green on every tag ever cut, reading nothing, and wrong for eight releases
 * because the two neighbouring assertions that DO read the registry were
 * updated and it was not. None of that family can be found by grepping for a
 * number: the numbers are usually right. What is wrong is that nothing computes
 * them. This repository carries the mirrored gate tests and had never been
 * inspected.
 *
 * WHAT IT CHECKS
 * --------------
 * Every `assert*` call under tests/, on the PHP AST (nikic/php-parser, already
 * present as a PHPStan dependency). An assertion is INERT when EVERY argument
 * folds to a compile-time constant, so that no change anywhere in src/ can move
 * any side of it.
 *
 * Folding goes through local assignments -- `$a = 20; $b = 7;
 * self::assertSame(27, $a + $b);` is the same defect written at one remove --
 * but stops at:
 *   - any variable assigned more than once, or assigned from a non-constant,
 *     or taken by reference, or written inside a loop or a closure;
 *   - anything reaching a class constant, enum case, static call, method call,
 *     function call, property fetch, `$this`, or a parameter.
 * A `match`/ternary is constant only when every arm is.
 *
 * WHAT IT CANNOT SEE, STATED PLAINLY
 * ----------------------------------
 *   - An assertion whose expected side is a literal and whose actual side is a
 *     hand-written array in the test file. That cannot be moved by src/ either,
 *     but it also cannot be told apart from a deliberate transcription pin
 *     without knowing intent. They are not reported; the count is printed so
 *     the blind spot has a size.
 *   - A data provider feeding literals into a test that then asserts on them.
 *     The assertion reads its parameters, so it is not constant-folded here.
 *   - An assertion carried by a type declaration rather than by the call. That
 *     reader is PHPStan, which CI runs as its own step.
 *   - A test with no assertion in it at all, and a matcher used wrongly.
 *
 * EXEMPTIONS
 * ----------
 * `.inert-assertions.json`, the same shape and the same discipline the
 * TypeScript SDK uses: an entry is a written statement that the assertion is
 * deliberately about arithmetic or about PHP and makes no claim about this
 * repository. It is NOT a way to stop the gate asking. An exemption that
 * matches nothing FAILS, so a site that is fixed or deleted cannot leave a
 * silent licence behind it.
 *
 * The key is the file path plus the assertion source with whitespace collapsed,
 * so it survives the assertion moving lines and stops matching the moment the
 * assertion itself changes.
 *
 * Usage:  php scripts/check-inert-assertions.php [--json] [--root <dir>]
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

$root = dirname(__DIR__);
$asJson = false;
$argvRest = array_slice($argv, 1);
for ($i = 0; $i < count($argvRest); $i++) {
    if ($argvRest[$i] === '--json') {
        $asJson = true;
    } elseif ($argvRest[$i] === '--root' && isset($argvRest[$i + 1])) {
        $root = rtrim($argvRest[++$i], '/');
    }
}

$testsDir = $root . '/tests';
if (!is_dir($testsDir)) {
    fwrite(STDERR, "no tests/ directory under {$root}\n");
    exit(2);
}

$parser = (new ParserFactory())->createForHostVersion();
$finder = new NodeFinder();

/** Collect every .php file under tests/. */
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsDir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && $f->getExtension() === 'php') {
        $files[] = $f->getPathname();
    }
}
sort($files);

/**
 * Names that make a call site an assertion.
 * PHPUnit's own assert* plus the two negative helpers that take a callable.
 */
$isAssertName = static fn (string $n): bool => (bool) preg_match('/^assert[A-Z]/', $n);

/** A variable that must never be folded through. */
final class Scope
{
    /** @var array<string, Node\Expr|false> value, or false when poisoned */
    public array $vars = [];
}

/**
 * Fold an expression to a constant when every leaf is one.
 * Returns [true, description] or [false, reason].
 *
 * @return array{0: bool, 1: string}
 */
function foldsConstant(Node $e, Scope $scope, int $depth = 0): array
{
    if ($depth > 40) {
        return [false, 'too deep'];
    }
    switch (true) {
        case $e instanceof Node\Scalar\String_:
        case $e instanceof Node\Scalar\Int_:
        case $e instanceof Node\Scalar\Float_:
            return [true, 'literal'];

        case $e instanceof Node\Scalar\InterpolatedString:
            foreach ($e->parts as $p) {
                if ($p instanceof Node\InterpolatedStringPart) {
                    continue;
                }
                [$ok, $why] = foldsConstant($p, $scope, $depth + 1);
                if (!$ok) {
                    return [false, $why];
                }
            }
            return [true, 'interpolated literal'];

        case $e instanceof Node\Expr\ConstFetch:
            // true / false / null only. A global constant could come from anywhere.
            $n = strtolower($e->name->toString());
            return in_array($n, ['true', 'false', 'null'], true)
                ? [true, 'bool/null']
                : [false, 'global constant ' . $e->name->toString()];

        case $e instanceof Node\Expr\UnaryMinus:
        case $e instanceof Node\Expr\UnaryPlus:
        case $e instanceof Node\Expr\BooleanNot:
        case $e instanceof Node\Expr\BitwiseNot:
            return foldsConstant($e->expr, $scope, $depth + 1);

        case $e instanceof Node\Expr\BinaryOp:
            [$a, $wa] = foldsConstant($e->left, $scope, $depth + 1);
            if (!$a) {
                return [false, $wa];
            }
            return foldsConstant($e->right, $scope, $depth + 1);

        case $e instanceof Node\Expr\Ternary:
            foreach ([$e->cond, $e->if, $e->else] as $arm) {
                if ($arm === null) {
                    continue;
                }
                [$ok, $why] = foldsConstant($arm, $scope, $depth + 1);
                if (!$ok) {
                    return [false, $why];
                }
            }
            return [true, 'ternary of constants'];

        case $e instanceof Node\Expr\Array_:
            foreach ($e->items as $item) {
                if ($item === null) {
                    continue;
                }
                if ($item->unpack) {
                    return [false, 'array unpack'];
                }
                if ($item->key !== null) {
                    [$ok, $why] = foldsConstant($item->key, $scope, $depth + 1);
                    if (!$ok) {
                        return [false, $why];
                    }
                }
                [$ok, $why] = foldsConstant($item->value, $scope, $depth + 1);
                if (!$ok) {
                    return [false, $why];
                }
            }
            return [true, 'array of constants'];

        case $e instanceof Node\Expr\Variable:
            if (!is_string($e->name)) {
                return [false, 'variable variable'];
            }
            if (!array_key_exists($e->name, $scope->vars)) {
                return [false, '$' . $e->name . ' not a local constant'];
            }
            $bound = $scope->vars[$e->name];
            if ($bound === false) {
                return [false, '$' . $e->name . ' is reassigned or non-constant'];
            }
            return foldsConstant($bound, $scope, $depth + 1);

        default:
            // Everything else -- ClassConstFetch (enum cases, ::class),
            // StaticCall, MethodCall, FuncCall, PropertyFetch, New_, Closure,
            // Match_, Cast of a non-constant -- can be moved by src/.
            $cls = (new ReflectionClass($e))->getShortName();
            return [false, strtolower($cls)];
    }
}

/** Build the constant-binding scope of one function body. */
function scopeOf(array $stmts, NodeFinder $finder): Scope
{
    $scope = new Scope();

    // Poison anything assigned inside a loop, closure, or by reference, and
    // anything assigned more than once, before folding through any of it.
    $assignCount = [];
    /** @var Node\Expr\Assign[] $assigns */
    $assigns = $finder->findInstanceOf($stmts, Node\Expr\Assign::class);
    foreach ($assigns as $a) {
        if ($a->var instanceof Node\Expr\Variable && is_string($a->var->name)) {
            $assignCount[$a->var->name] = ($assignCount[$a->var->name] ?? 0) + 1;
        }
    }
    foreach ($finder->findInstanceOf($stmts, Node\Expr\AssignRef::class) as $a) {
        if ($a->var instanceof Node\Expr\Variable && is_string($a->var->name)) {
            $assignCount[$a->var->name] = 99;
        }
    }
    foreach ([Node\Expr\AssignOp::class, Node\Expr\PostInc::class, Node\Expr\PreInc::class,
              Node\Expr\PostDec::class, Node\Expr\PreDec::class] as $mut) {
        foreach ($finder->findInstanceOf($stmts, $mut) as $m) {
            $v = $m->var ?? null;
            if ($v instanceof Node\Expr\Variable && is_string($v->name)) {
                $assignCount[$v->name] = 99;
            }
        }
    }
    // foreach targets are never constant
    foreach ($finder->findInstanceOf($stmts, Node\Stmt\Foreach_::class) as $fe) {
        foreach ([$fe->keyVar, $fe->valueVar] as $v) {
            if ($v instanceof Node\Expr\Variable && is_string($v->name)) {
                $assignCount[$v->name] = 99;
            }
        }
    }

    // An append or an index write moves the value without being an assignment
    // TO the variable: `$expected = []; $expected[] = ...;` is one Assign whose
    // target is an ArrayDimFetch, so a naive count sees `$expected` assigned
    // once and folds it to the empty array it was declared with.
    // Destructuring targets move the same way.
    foreach ($assigns as $a) {
        $t = $a->var;
        while ($t instanceof Node\Expr\ArrayDimFetch || $t instanceof Node\Expr\PropertyFetch) {
            $t = $t->var;
        }
        if ($t instanceof Node\Expr\Variable && is_string($t->name) && $t !== $a->var) {
            $assignCount[$t->name] = 99;
        }
        if ($a->var instanceof Node\Expr\List_ || $a->var instanceof Node\Expr\Array_) {
            foreach ($finder->findInstanceOf([$a->var], Node\Expr\Variable::class) as $v) {
                if (is_string($v->name)) {
                    $assignCount[$v->name] = 99;
                }
            }
        }
    }

    // PHP cannot tell a by-reference parameter from a by-value one without the
    // callee's signature, and the out-parameter idiom is everywhere:
    // `$exitCode = 0; exec($cmd, $output, $exitCode);` assigns the literal once
    // and then has it overwritten by the call. Any bare variable handed to a
    // call is therefore poisoned -- EXCEPT one handed to an assert* call, which
    // is the site being judged and takes nothing by reference.
    $everyCall = array_merge(
        $finder->findInstanceOf($stmts, Node\Expr\FuncCall::class),
        $finder->findInstanceOf($stmts, Node\Expr\MethodCall::class),
        $finder->findInstanceOf($stmts, Node\Expr\StaticCall::class),
        $finder->findInstanceOf($stmts, Node\Expr\New_::class),
    );
    foreach ($everyCall as $call) {
        $name = $call->name ?? null;
        if ($name instanceof Node\Identifier && preg_match('/^assert[A-Z]/', $name->toString())) {
            continue;
        }
        foreach ($call->args as $arg) {
            if ($arg instanceof Node\Arg
                && $arg->value instanceof Node\Expr\Variable
                && is_string($arg->value->name)) {
                $assignCount[$arg->value->name] = 99;
            }
        }
    }

    foreach ($assigns as $a) {
        if (!($a->var instanceof Node\Expr\Variable) || !is_string($a->var->name)) {
            continue;
        }
        $name = $a->var->name;
        $scope->vars[$name] = ($assignCount[$name] ?? 0) === 1 ? $a->expr : false;
    }
    return $scope;
}

$exemptPath = $root . '/.inert-assertions.json';
$exempt = [];
$exemptNote = [];
if (is_file($exemptPath)) {
    $decoded = json_decode((string) file_get_contents($exemptPath), true);
    if (!is_array($decoded) || !isset($decoded['exempt']) || !is_array($decoded['exempt'])) {
        fwrite(STDERR, ".inert-assertions.json is not readable as {\"exempt\": {...}}\n");
        exit(2);
    }
    $exemptNote = $decoded['exempt'];
    $exempt = array_fill_keys(array_keys($decoded['exempt']), false);
}

$inert = [];
$stats = ['files' => 0, 'assertions' => 0, 'literalPinned' => 0];

foreach ($files as $path) {
    $src = (string) file_get_contents($path);
    $stats['files']++;
    try {
        $ast = $parser->parse($src);
    } catch (Throwable $t) {
        fwrite(STDERR, "parse error in {$path}: {$t->getMessage()}\n");
        exit(2);
    }
    if ($ast === null) {
        fwrite(STDERR, "empty parse for {$path}\n");
        exit(2);
    }
    $rel = ltrim(str_replace($root, '', $path), '/');

    /** @var Node\Stmt\ClassMethod[] $methods */
    $methods = $finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class);
    foreach ($methods as $m) {
        if ($m->stmts === null) {
            continue;
        }
        $scope = scopeOf($m->stmts, $finder);

        $calls = array_merge(
            $finder->findInstanceOf($m->stmts, Node\Expr\StaticCall::class),
            $finder->findInstanceOf($m->stmts, Node\Expr\MethodCall::class),
        );
        foreach ($calls as $c) {
            if (!($c->name instanceof Node\Identifier) || !$isAssertName($c->name->toString())) {
                continue;
            }
            $stats['assertions']++;
            $args = [];
            foreach ($c->args as $a) {
                if ($a instanceof Node\Arg) {
                    $args[] = $a->value;
                }
            }
            if ($args === []) {
                continue;
            }
            $allConst = true;
            $sawNonLiteralArray = false;
            foreach ($args as $a) {
                [$ok] = foldsConstant($a, $scope);
                if (!$ok) {
                    $allConst = false;
                    if ($a instanceof Node\Expr\Array_) {
                        $sawNonLiteralArray = true;
                    }
                }
            }
            if (!$allConst) {
                if (!$sawNonLiteralArray) {
                    // count the blind spot: a literal array pinned against a literal
                    $anyArray = false;
                    foreach ($args as $a) {
                        if ($a instanceof Node\Expr\Array_) {
                            $anyArray = true;
                        }
                    }
                    if ($anyArray) {
                        $stats['literalPinned']++;
                    }
                }
                continue;
            }

            $start = $c->getStartFilePos();
            $end = $c->getEndFilePos();
            $text = substr($src, $start, $end - $start + 1);
            $text = trim((string) preg_replace('/\s+/', ' ', $text));
            $key = $rel . ' :: ' . $text;
            $rec = [
                'file' => $rel,
                'line' => $c->getStartLine(),
                'method' => $m->name->toString(),
                'text' => $text,
                'key' => $key,
            ];
            if (array_key_exists($key, $exempt)) {
                $exempt[$key] = true;
                $rec['exempt'] = true;
            }
            $inert[] = $rec;
        }
    }
}

$undeclared = array_values(array_filter($inert, static fn (array $r): bool => !isset($r['exempt'])));
$staleExemptions = array_keys(array_filter($exempt, static fn (bool $hit): bool => !$hit));

if ($asJson) {
    echo json_encode([
        'stats' => $stats,
        'inert' => $inert,
        'undeclared' => $undeclared,
        'staleExemptions' => $staleExemptions,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
    exit($undeclared === [] && $staleExemptions === [] ? 0 : 1);
}

printf("test files scanned      : %d\n", $stats['files']);
printf("assert* call sites      : %d\n", $stats['assertions']);
printf("inert (both sides const): %d\n", count($inert));
printf("  declared in .inert-assertions.json : %d\n", count($inert) - count($undeclared));
printf("  undeclared                         : %d\n", count($undeclared));
printf("literal array pinned against a literal (NOT reported, blind spot) : %d\n", $stats['literalPinned']);

// Non-vacuity: a run that found no assertions at all inspected nothing.
if ($stats['assertions'] < 100) {
    fwrite(STDERR, "\nREFUSING A VERDICT: only {$stats['assertions']} assert* sites found — the walk inspected too little.\n");
    exit(2);
}

$bad = false;
if ($undeclared !== []) {
    $bad = true;
    echo "\nINERT — " . count($undeclared) . " assertion(s) cannot fail and are not declared:\n";
    foreach ($undeclared as $r) {
        printf("  x %s:%d  %s()\n      %s\n", $r['file'], $r['line'], $r['method'], $r['text']);
    }
    echo "\nEither make a side read the repository, or declare it in .inert-assertions.json\n";
    echo "with a written reason. A declaration is a statement that the assertion makes no\n";
    echo "claim about this repository -- not a way to stop this gate asking.\n";
}
if ($staleExemptions !== []) {
    $bad = true;
    echo "\nSTALE EXEMPTION — " . count($staleExemptions) . " entry in .inert-assertions.json matches nothing:\n";
    foreach ($staleExemptions as $k) {
        echo "  x {$k}\n";
    }
    echo "\nThe assertion it excused was changed or deleted. Remove the entry.\n";
}

if (!$bad) {
    echo "\nEvery inert assertion is declared, and every declaration still matches one.\n";
}
exit($bad ? 1 : 0);
